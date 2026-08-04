<?php

namespace App\Jobs;

use App\Models\Video;
use App\Services\GeminiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateQuizJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Video $video)
    {
    }

    public function handle(GeminiService $geminiService): void
    {
        $this->video->update(['quiz_status' => 'proses']);

        try {
            // ── Delete existing questions BEFORE calling AI ──────────────────
            // This guarantees the page shows fresh content even if the model
            // returns identical text to the previous generation.
            $oldCount = $this->video->questions()->count();
            if ($oldCount > 0) {
                $this->video->questions()->delete();
                Log::info("GenerateQuizJob: Deleted {$oldCount} old questions for video [{$this->video->id}] before regeneration.");
            }

            $questionsData = $geminiService->generateQuizForVideo($this->video);

            if (!empty($questionsData)) {
                foreach ($questionsData as $index => $q) {
                    $this->video->questions()->create([
                        'question_text'           => $q['question_text'],
                        'options'                 => $q['options'],
                        'correct_answer'          => strtoupper($q['correct_answer']),
                        'order_number'            => $index + 1,
                        'video_timestamp_seconds' => $q['video_timestamp_seconds'] ?? null,
                    ]);
                }

                $this->video->update(['quiz_status' => 'aktif']);
                Log::info("GenerateQuizJob: Successfully generated " . count($questionsData) . " questions for video [{$this->video->id}].");
                return;
            }

            $this->video->update(['quiz_status' => 'gagal']);

        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'RATE_LIMIT_EXCEEDED') {
                // Gemini free tier rate limit — restore previous status so admin can try again
                $this->video->update(['quiz_status' => 'belum']);
                Log::warning("GenerateQuizJob: Gemini rate limited for video [{$this->video->id}]. Admin should try again after 1 minute.");
                // Re-throw so the controller can show a specific message
                throw $e;
            }
            Log::error("GenerateQuizJob RuntimeException for video {$this->video->id}: " . $e->getMessage());
            $this->video->update(['quiz_status' => 'gagal']);
        } catch (\Throwable $e) {
            Log::error("GenerateQuizJob error for video {$this->video->id}: " . $e->getMessage());
            $this->video->update(['quiz_status' => 'gagal']);
        }
    }
}
