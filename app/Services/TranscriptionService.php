<?php

namespace App\Services;

use App\Models\Video;
use App\Models\VideoObjective;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class TranscriptionService
{
    public function __construct(
        protected GeminiService $geminiService,
        protected DyntubeService $dyntubeService
    ) {}

    /**
     * Generate and store the exact spoken transcript from a video's audio.
     *
     * Pipeline:
     *   1. Get HLS stream URL from Dyntube API
     *   2. FFMPEG: HLS stream → MP3 audio file
     *   3. Whisper (Python library, 100% local): MP3 → exact spoken text
     *   4. Save spoken text to videos.transcript in DB
     *
     * Gemini AI is NOT used here — it is only used for quiz generation.
     *
     * @param  UploadedFile|string|null  $videoFile  Local MP4 file path (fallback)
     * @return string The exact spoken transcript
     */
    public function generateAndStoreTranscript(Video $video, UploadedFile|string|null $videoFile = null): string
    {
        $transcript = '';

        // === Primary: Dyntube HLS → FFMPEG → Whisper STT ===
        if (! empty($video->dyntube_video_id)) {
            $transcript = $this->transcribeFromDyntubeHls($video);
        }

        // === Fallback: Local uploaded MP4 file → FFMPEG → Whisper STT ===
        if (empty(trim($transcript)) && $videoFile) {
            $filePath = $videoFile instanceof UploadedFile ? $videoFile->getRealPath() : $videoFile;
            if ($filePath && file_exists($filePath)) {
                $transcript = $this->runFfmpegAndWhisper($filePath, $video);
            }
        }

        // Save exact spoken transcript to DB
        if (! empty(trim($transcript))) {
            $video->update(['transcript' => $transcript]);
            Log::info("TranscriptionService: Saved spoken transcript for video [{$video->id}] ({$video->title}) — ".strlen($transcript).' chars');
        } else {
            Log::warning("TranscriptionService: Could not extract transcript for video [{$video->id}] ({$video->title})");
        }

        // Ensure learning objectives (kisi-kisi) exist — these are SEPARATE from the transcript
        $this->ensureVideoObjectives($video);

        return $transcript;
    }

    /**
     * Get HLS audio from Dyntube, extract MP3 via FFMPEG, transcribe via Whisper.
     */
    public function transcribeFromDyntubeHls(Video $video): string
    {
        $apiKey = env('DYNTUBE_API_KEY');
        $dynApiUrl = env('DYNTUBE_API_URL', 'https://api.dyntube.com/v1');

        // Step 1: Fetch HLS URL from Dyntube API
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "{$dynApiUrl}/videos/{$video->dyntube_video_id}",
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$apiKey}", 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($body, true);
        $hlsUrl = $data['hlsUrlWeb'] ?? null;

        if (empty($hlsUrl)) {
            // HLS not ready yet on Dyntube's side (their transcode hasn't finished).
            // Signal the caller to RETRY instead of giving up — otherwise the
            // transcript stays empty and the quiz can never be generated.
            Log::warning("TranscriptionService: HLS URL not ready yet for video [{$video->id}] (Dyntube status=".($data['status'] ?? '?').")");
            throw new \App\Exceptions\TranscriptNotReadyException("HLS not ready for video {$video->id}");
        }

        Log::info("TranscriptionService: Got HLS URL for video [{$video->id}]: {$hlsUrl}");

        // Step 2 & 3: FFMPEG → MP3, then Whisper → Text
        return $this->runFfmpegAndWhisper($hlsUrl, $video);
    }

    /**
     * Extract audio from a source (URL or local path) via FFMPEG, then transcribe with Whisper.
     *
     * @param  string  $source  Local MP4/video path OR HLS URL (https://)
     * @return string Exact spoken transcript
     */
    protected function runFfmpegAndWhisper(string $source, Video $video): string
    {
        $ffmpeg = storage_path('app/bin/ffmpeg.exe');
        $whisper = base_path('scripts/whisper_transcribe.py');
        $tempDir = storage_path('app/temp');
        @mkdir($tempDir, 0777, true);

        $mp3Path = $tempDir.'/audio_v'.$video->id.'_'.time().'.mp3';

        // === STEP 1: FFMPEG — extract audio → MP3 ===
        if (! file_exists($ffmpeg)) {
            Log::error("TranscriptionService: FFMPEG not found at {$ffmpeg}");

            return '';
        }

        $ffmpegCmd = sprintf(
            '%s -y -i %s -vn -ar 16000 -ac 1 -ab 64k %s 2>&1',
            escapeshellarg($ffmpeg),
            escapeshellarg($source),
            escapeshellarg($mp3Path)
        );

        $ffmpegOutput = shell_exec($ffmpegCmd);
        $mp3Size = file_exists($mp3Path) ? filesize($mp3Path) : 0;

        if ($mp3Size < 1000) {
            Log::error("TranscriptionService: FFMPEG failed for video [{$video->id}]. Output: ".substr($ffmpegOutput, -300));
            if (file_exists($mp3Path)) {
                @unlink($mp3Path);
            }

            return '';
        }

        Log::info('TranscriptionService: MP3 extracted — '.round($mp3Size / 1024)."KB for video [{$video->id}]");

        // === STEP 2: WHISPER (Python library) — MP3 → exact spoken text ===
        if (! file_exists($whisper)) {
            Log::error("TranscriptionService: Whisper script not found at {$whisper}");
            if (file_exists($mp3Path)) {
                @unlink($mp3Path);
            }

            return '';
        }

        // Add ffmpeg binary directory to PATH so the Python whisper library
        // can find ffmpeg when it internally calls it via subprocess.
        // Also export FFMPEG_BIN_DIR, which whisper_transcribe.py reads
        // explicitly to prepend the ffmpeg dir to PATH inside Python.
        $ffmpegDir = dirname($ffmpeg);
        $currentPath = getenv('PATH') ?: '';
        putenv('PATH='.$ffmpegDir.';'.$currentPath);
        putenv('FFMPEG_BIN_DIR='.$ffmpegDir);

        // Capture ONLY stdout (the transcript text); redirect stderr to
        // a log file so Python tracebacks don't pollute the transcript.
        $stdoutFile = $mp3Path.'.out';
        $stderrLog = $tempDir.'/whisper_err_v'.$video->id.'_'.time().'.log';
        $whisperCmd = sprintf(
            'python %s %s id 1>%s 2>%s',
            escapeshellarg($whisper),
            escapeshellarg($mp3Path),
            escapeshellarg($stdoutFile),
            escapeshellarg($stderrLog)
        );

        Log::info("TranscriptionService: Running Whisper STT for video [{$video->id}]...");
        shell_exec($whisperCmd);

        // Restore PATH
        putenv('PATH='.$currentPath);
        putenv('FFMPEG_BIN_DIR');

        // Cleanup temp MP3
        if (file_exists($mp3Path)) {
            @unlink($mp3Path);
        }

        // Read stdout (the actual transcript text)
        $whisperOutput = file_exists($stdoutFile) ? file_get_contents($stdoutFile) : '';
        @unlink($stdoutFile);

        // Check for errors in stderr
        $stderrContent = file_exists($stderrLog) ? file_get_contents($stderrLog) : '';
        @unlink($stderrLog);

        if ($stderrContent) {
            Log::error("TranscriptionService: Whisper stderr for video [{$video->id}]: ".substr($stderrContent, 0, 500));
        }

        $transcript = trim($whisperOutput ?? '');

        // Guard: if the output looks like a Python traceback, discard it
        if (str_contains($transcript, 'Traceback') || str_contains($transcript, 'Error:')) {
            Log::error("TranscriptionService: Whisper output looks like an error for video [{$video->id}]: ".substr($transcript, 0, 300));

            return '';
        }

        if (empty($transcript)) {
            Log::warning("TranscriptionService: Whisper returned empty result for video [{$video->id}]");

            return '';
        }

        Log::info('TranscriptionService: Whisper transcribed '.strlen($transcript)." chars for video [{$video->id}]");

        return $transcript;
    }

    /**
     * Populate 4 learning objectives (kisi-kisi / target pemahaman) into video_objectives table.
     * These are SEPARATE from the transcript — they describe learning goals shown on the watch page.
     */
    public function ensureVideoObjectives(Video $video): void
    {
        if ($video->objectives()->count() > 0) {
            return;
        }

        $title = $video->title;
        $categoryName = $video->category?->name ?? 'Kesehatan';

        $defaultObjectives = [
            "Memahami konsep dasar dan mekanisme biologis yang berkaitan dengan {$title}.",
            "Mengenali faktor penyebab, risiko, dan proses terjadinya {$title} pada organ pencernaan.",
            "Mengidentifikasi gejala klinis dan dampak {$title} terhadap kesehatan tubuh.",
            "Menerapkan langkah pencegahan dan tata laksana mandiri terkait {$title}.",
        ];

        foreach ($defaultObjectives as $index => $text) {
            VideoObjective::create([
                'video_id' => $video->id,
                'objective_text' => $text,
                'order_number' => $index + 1,
            ]);
        }

        Log::info("TranscriptionService: Created 4 objectives for video [{$video->id}] '{$title}'");
    }
}
