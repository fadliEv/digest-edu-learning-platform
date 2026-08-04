<?php

namespace App\Services;

use App\Models\Video;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class TranscriptionService
{
    public function __construct(
        protected GeminiService $geminiService
    ) {}

    /**
     * Automatically extract speech/transcript from video file or generate structured timeline transcript.
     * Stores transcript into DB during video upload.
     *
     * @param Video $video
     * @param UploadedFile|string|null $videoFile
     * @return string
     */
    public function generateAndStoreTranscript(Video $video, UploadedFile|string|null $videoFile = null): string
    {
        $transcript = '';

        // 1. If videoFile is provided, attempt automated audio-to-text extraction
        if ($videoFile) {
            $filePath = $videoFile instanceof UploadedFile ? $videoFile->getRealPath() : $videoFile;
            if ($filePath && file_exists($filePath)) {
                $transcript = $this->transcribeFromFile($filePath, $video);
            }
        }

        // 2. Fallback: Generate timestamped transcript based on video metadata
        if (empty(trim($transcript))) {
            $transcript = $this->generateFallbackTranscript($video);
        }

        // 3. Save transcript directly into DB
        $video->update(['transcript' => $transcript]);
        Log::info("TranscriptionService: Saved transcript to DB for video [{$video->id}] '{$video->title}' (" . strlen($transcript) . " chars)");

        return $transcript;
    }

    /**
     * Transcribe audio/video file using local tools or Gemini Multimodal File API.
     */
    protected function transcribeFromFile(string $filePath, Video $video): string
    {
        $binDir = storage_path('app/bin');
        $ffmpeg = $binDir . '/ffmpeg.exe';
        $whisper = $binDir . '/whisper-cli.exe';
        $model = $binDir . '/ggml-base.bin';

        // Option A: Local Whisper.cpp if installed in storage/app/bin
        if (file_exists($ffmpeg) && file_exists($whisper) && file_exists($model)) {
            try {
                $wavPath = storage_path('app/temp/' . uniqid('audio_') . '.wav');
                @mkdir(dirname($wavPath), 0777, true);

                $cmdExtract = sprintf('%s -y -i %s -vn -ar 16000 -ac 1 %s 2>&1', escapeshellarg($ffmpeg), escapeshellarg($filePath), escapeshellarg($wavPath));
                exec($cmdExtract);

                if (file_exists($wavPath)) {
                    $cmdWhisper = sprintf('%s -m %s -f %s -l id -otxt 2>&1', escapeshellarg($whisper), escapeshellarg($model), escapeshellarg($wavPath));
                    $output = shell_exec($cmdWhisper);
                    @unlink($wavPath);

                    if (!empty($output)) {
                        return trim($output);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Local whisper transcription failed: " . $e->getMessage());
            }
        }

        // Option B: Gemini API Multimodal Audio/Video Transcription
        try {
            $geminiTranscript = $this->geminiService->transcribeVideoFile($filePath, $video);
            if (!empty(trim($geminiTranscript))) {
                return $geminiTranscript;
            }
        } catch (\Throwable $e) {
            Log::warning("Gemini transcription failed: " . $e->getMessage());
        }

        return '';
    }

    /**
     * Generate fallback timestamped transcript structure so database is always populated.
     */
    protected function generateFallbackTranscript(Video $video): string
    {
        $title    = $video->title;
        $category = $video->category?->name ?? 'Kesehatan Pencernaan';
        $desc     = $video->description ?? "Materi edukasi mengenai {$title}";
        $duration = $video->duration_seconds ?? 300;

        $t1 = sprintf('%02d:%02d', 0, 15);
        $t2 = sprintf('%02d:%02d', intval($duration * 0.25) / 60, intval($duration * 0.25) % 60);
        $t3 = sprintf('%02d:%02d', intval($duration * 0.50) / 60, intval($duration * 0.50) % 60);
        $t4 = sprintf('%02d:%02d', intval($duration * 0.75) / 60, intval($duration * 0.75) % 60);

        return <<<TRANSCRIPT
{$t1} - Pengenalan konsep dasar dan pentingnya modul {$title} dalam kategori {$category}. {$desc}
{$t2} - Penjelasan penyebab utama, faktor risiko, dan mekanisme biologis yang memicu {$title}.
{$t3} - Identifikasi gejala klinis, tanda-tanda awal, serta dampak pada sistem organ pencernaan.
{$t4} - Langkah pencegahan, tata laksana mandiri, dan kapan harus melakukan konsultasi medis profesional.
TRANSCRIPT;
    }
}
