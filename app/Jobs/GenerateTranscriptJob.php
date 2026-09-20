<?php

namespace App\Jobs;

use App\Exceptions\TranscriptNotReadyException;
use App\Models\Video;
use App\Services\TranscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateTranscriptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Allow many retries — HLS on Dyntube can take a few minutes to become ready. */
    public int $tries = 15;

    /** Seconds a single attempt may run (Whisper on a long video is slow). */
    public int $timeout = 900;

    public function __construct(public Video $video) {}

    public function handle(TranscriptionService $transcriptionService): void
    {
        @set_time_limit(0); // Whisper STT can be slow; never let PHP's timer kill the worker.
        $this->video->refresh();

        // Wait for our simulated transcoding window to elapse before trying.
        if ($this->video->transcoding_status === 'transcoding') {
            $readyAt = $this->video->transcoding_ready_at;
            if ($readyAt && now()->lt($readyAt)) {
                $delaySeconds = now()->diffInSeconds($readyAt) + 5;
                Log::info("GenerateTranscriptJob: Video [{$this->video->id}] still transcoding. Releasing for {$delaySeconds}s...");
                $this->release($delaySeconds);

                return;
            }
        }

        Log::info("GenerateTranscriptJob: Starting transcription for video [{$this->video->id}] '{$this->video->title}' (attempt {$this->attempts()})");

        try {
            $transcript = $transcriptionService->generateAndStoreTranscript($this->video, null);

            if (! empty(trim($transcript))) {
                Log::info("GenerateTranscriptJob: Completed for video [{$this->video->id}] — ".strlen($transcript).' chars');
            } else {
                Log::warning("GenerateTranscriptJob: Empty transcript for video [{$this->video->id}] after successful run.");
            }
        } catch (TranscriptNotReadyException $e) {
            // Dyntube HLS not ready yet — retry with backoff instead of failing.
            if ($this->attempts() < $this->tries) {
                Log::info("GenerateTranscriptJob: HLS not ready for video [{$this->video->id}] (attempt {$this->attempts()}). Releasing for 30s...");
                $this->release(30);

                return;
            }
            Log::error("GenerateTranscriptJob: HLS never became ready for video [{$this->video->id}] after {$this->tries} attempts.");
        } catch (\Throwable $e) {
            Log::error("GenerateTranscriptJob error for video {$this->video->id}: ".$e->getMessage());
        }
    }
}
