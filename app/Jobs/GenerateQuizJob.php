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

    public int $tries = 8;

    public function __construct(public Video $video) {}

    public function handle(GeminiService $geminiService): void
    {
        @set_time_limit(0); // long-lived worker: don't let PHP's execution timer kill it.
        $this->video->update(['quiz_status' => 'proses']);

        // ── Guard: quiz MUST be based on the exact spoken transcript ─────────
        // If the transcript job hasn't finished storing the narration yet,
        // wait for it instead of generating questions from generic knowledge
        // (which is what produced off-topic, inaccurate quizzes).
        $this->video->refresh();
        if (empty(trim($this->video->transcript ?? ''))) {
            if ($this->attempts() < $this->tries) {
                Log::info("GenerateQuizJob: Transcript for video [{$this->video->id}] not ready yet (attempt {$this->attempts()}). Waiting 30s...");
                $this->release(30);

                return;
            }

            // Transcript never arrived after all retries — do NOT fabricate a
            // quiz from thin air. Mark failed so admin can retry / re-transcribe.
            Log::warning("GenerateQuizJob: Transcript never became available for video [{$this->video->id}] after {$this->tries} attempts. Marking quiz as failed.");
            $this->video->update(['quiz_status' => 'gagal']);

            return;
        }

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

            if (! empty($questionsData)) {
                foreach ($questionsData as $index => $q) {
                    $this->video->questions()->create([
                        'question_text' => $q['question_text'],
                        'options' => $q['options'],
                        'correct_answer' => strtoupper($q['correct_answer']),
                        'order_number' => $index + 1,
                        'video_timestamp_seconds' => $q['video_timestamp_seconds'] ?? null,
                    ]);
                }

                $this->video->update(['quiz_status' => 'aktif']);
                Log::info('GenerateQuizJob: Successfully generated '.count($questionsData)." questions for video [{$this->video->id}].");

                return;
            }

            $this->video->update(['quiz_status' => 'gagal']);

        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'RATE_LIMIT_EXCEEDED') {
                // Gemini free tier rate limit — requeue the job with a delay
                $this->video->update(['quiz_status' => 'proses']);
                Log::warning("GenerateQuizJob: Gemini rate limited for video [{$this->video->id}]. Requeueing with 60s delay.");
                $this->release(60);

                return;
            }
            Log::error("GenerateQuizJob RuntimeException for video {$this->video->id}: ".$e->getMessage());
            $this->video->update(['quiz_status' => 'gagal']);
        } catch (\Throwable $e) {
            Log::error("GenerateQuizJob error for video {$this->video->id}: ".$e->getMessage());
            $this->video->update(['quiz_status' => 'gagal']);
        }
    }
}
