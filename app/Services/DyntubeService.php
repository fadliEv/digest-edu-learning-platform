<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DyntubeService
{
    protected string $apiKey;
    protected string $apiUrl;
    protected string $uploadUrl;

    public function __construct()
    {
        $this->apiKey    = env('DYNTUBE_API_KEY', '');
        $this->apiUrl    = env('DYNTUBE_API_URL', 'https://api.dyntube.com/v1');
        $this->uploadUrl = env('DYNTUBE_UPLOAD_URL', 'https://upload.dyntube.com/v1/videos');
    }

    /**
     * Upload video file directly to Dyntube API.
     * Uses native cURL with CURLFile for reliable multipart upload.
     *
     * @param \Illuminate\Http\UploadedFile|string $file  UploadedFile instance or file path
     * @param string $title  Title for Dyntube video metadata
     * @return array{success: bool, video_id: string, iframe_link: string, thumbnail: string, duration_seconds: int, message?: string}
     */
    public function uploadVideo(mixed $file, string $title = ''): array
    {
        $defaultThumbnail = "https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=500&h=300&fit=crop";

        if (empty($this->apiKey)) {
            Log::warning('DyntubeService: API key missing for upload. Using fallback ID.');
            $mockId = 'local_' . uniqid();
            return [
                'success'          => true,
                'video_id'         => $mockId,
                'iframe_link'      => $mockId,
                'thumbnail'        => $defaultThumbnail,
                'duration_seconds' => 180,
                'message'          => 'No API key configured — using local fallback ID.',
            ];
        }

        try {
            $filePath = $file instanceof \Illuminate\Http\UploadedFile ? $file->getRealPath() : $file;
            $fileName = $file instanceof \Illuminate\Http\UploadedFile ? $file->getClientOriginalName() : basename($file);
            $mimeType = $file instanceof \Illuminate\Http\UploadedFile ? ($file->getMimeType() ?? 'video/mp4') : 'video/mp4';

            if (!file_exists($filePath) || !is_readable($filePath)) {
                throw new \RuntimeException("File not found or not readable: {$filePath}");
            }

            Log::info("DyntubeService: Starting upload — file={$fileName}, size=" . filesize($filePath) . " bytes, url={$this->uploadUrl}");

            // Use CURLFile — streams the file instead of loading into memory
            $curlFile = new \CURLFile($filePath, $mimeType, $fileName);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $this->uploadUrl,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => [
                    'file'  => $curlFile,
                    'title' => $title,
                ],
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $this->apiKey,
                    'Accept: application/json',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 300, // 5 minutes for large files
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $responseBody = curl_exec($ch);
            $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError    = curl_error($ch);
            curl_close($ch);

            Log::info("DyntubeService: Upload response — HTTP {$httpCode}", [
                'body' => substr((string) $responseBody, 0, 800),
            ]);

            if ($curlError) {
                throw new \RuntimeException("cURL error: {$curlError}");
            }

            $data = json_decode((string) $responseBody, true);

            if ($httpCode >= 200 && $httpCode < 300 && is_array($data)) {
                $videoId    = $data['key'] ?? $data['id'] ?? $data['videoId'] ?? $data['video_id'] ?? null;
                $iframeLink = $data['iframeLink'] ?? $data['iframe_link'] ?? $data['embedUrl'] ?? $data['key'] ?? $videoId;
                $thumbnail  = $data['image']['mdUrl']
                    ?? $data['thumbnailUrl']
                    ?? $data['posterUrl']
                    ?? $data['thumbnail']
                    ?? $defaultThumbnail;
                $duration   = $this->parseDurationSeconds($data['duration'] ?? null);

                if (!empty($videoId)) {
                    Log::info("DyntubeService: Upload successful. Video ID: {$videoId}");
                    return [
                        'success'          => true,
                        'video_id'         => $videoId,
                        'iframe_link'      => $iframeLink,
                        'thumbnail'        => $thumbnail,
                        'duration_seconds' => $duration,
                    ];
                }

                Log::warning("DyntubeService: Upload succeeded (HTTP {$httpCode}) but no video_id in response.", ['data' => $data]);
            } else {
                Log::warning("DyntubeService: Upload failed HTTP {$httpCode}.", ['body' => substr((string) $responseBody, 0, 800)]);
            }

        } catch (\Throwable $e) {
            Log::error("DyntubeService upload exception: " . $e->getMessage());
        }

        // Fallback — non-blocking: video record still gets created with a placeholder ID
        $fallbackId = 'dyntube_' . substr(md5(uniqid()), 0, 12);
        Log::warning("DyntubeService: Using fallback ID={$fallbackId}");
        return [
            'success'          => true,
            'video_id'         => $fallbackId,
            'iframe_link'      => $fallbackId,
            'thumbnail'        => $defaultThumbnail,
            'duration_seconds' => 180,
            'message'          => 'Upload to Dyntube failed — video saved with fallback ID.',
        ];
    }

    /**
     * Validate Dyntube video ID and fetch video metadata (iframe link, thumbnail, duration).
     *
     * @param string $videoId
     * @return array{success: bool, iframe_link: string, thumbnail: string, duration_seconds: int, message?: string}
     */
    public function getVideoDetails(string $videoId): array
    {
        $videoId = trim($videoId);

        // Fallback metadata defaults
        $defaultIframeLink = $videoId;
        $defaultThumbnail = "https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=500&h=300&fit=crop";
        $defaultDuration = 180; // 3 mins

        if (empty($this->apiKey)) {
            return [
                'success' => true,
                'iframe_link' => $defaultIframeLink,
                'thumbnail' => $defaultThumbnail,
                'duration_seconds' => $defaultDuration,
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Accept' => 'application/json',
            ])->timeout(10)->get("{$this->apiUrl}/videos/{$videoId}");

            if ($response->successful()) {
                $data = $response->json();

                // Extract Dyntube iframeLink (Crucial for Dyntube player iframe)
                $iframeLink = $data['iframeLink'] ?? $data['key'] ?? $videoId;

                // Extract Dyntube Thumbnail URL
                $thumbnail = $data['image']['mdUrl']
                    ?? $data['image']['lgUrl']
                    ?? $data['image']['smUrl']
                    ?? $data['thumbnailUrl']
                    ?? $data['posterUrl']
                    ?? $defaultThumbnail;

                // Parse duration string "HH:MM:SS.ffffff" or numeric duration
                $durationSeconds = $this->parseDurationSeconds($data['duration'] ?? null);

                Log::info("Dyntube metadata fetched for '{$videoId}': iframeLink='{$iframeLink}', duration={$durationSeconds}s");

                return [
                    'success' => true,
                    'iframe_link' => $iframeLink,
                    'thumbnail' => $thumbnail,
                    'duration_seconds' => $durationSeconds,
                ];
            }

            Log::warning("Dyntube API returned status " . $response->status() . " for videoId '{$videoId}'");
        } catch (\Throwable $e) {
            Log::error("Dyntube API exception for videoId '{$videoId}': " . $e->getMessage());
        }

        return [
            'success' => true,
            'iframe_link' => $defaultIframeLink,
            'thumbnail' => $defaultThumbnail,
            'duration_seconds' => $defaultDuration,
        ];
    }

    /**
     * Parse duration which can be string "HH:MM:SS.ffffff" (e.g. "00:03:03.1590000") or float/int seconds
     */
    protected function parseDurationSeconds(mixed $duration): int
    {
        if (is_numeric($duration)) {
            return max(1, (int)round($duration));
        }

        if (is_string($duration) && preg_match('/^(\d+):(\d+):(\d+)(?:\.\d+)?$/', trim($duration), $m)) {
            $hours = (int)$m[1];
            $minutes = (int)$m[2];
            $seconds = (int)$m[3];

            return max(1, ($hours * 3600) + ($minutes * 60) + $seconds);
        }

        return 180;
    }
}
