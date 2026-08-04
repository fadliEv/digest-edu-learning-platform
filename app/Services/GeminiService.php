<?php

namespace App\Services;

use App\Models\Video;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    // Gemini 2.0 Flash — free tier, latest stable model (as of 2025)
    protected const GEMINI_MODEL  = 'gemini-2.0-flash';
    protected const API_BASE      = 'https://generativelanguage.googleapis.com/v1beta/models';
    protected const QUESTION_COUNT = 6;

    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key', env('GEMINI_API_KEY', ''));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Generate multiple-choice quiz questions for a given video.
     *
     * Strategy:
     *   1. Build a rich educational prompt from the video's title, category, and description.
     *   2. Request structured JSON output via `responseSchema` (Gemini 2.0 native feature).
     *   3. Parse + validate the response — fall back to sample questions on failure.
     *
     * @param  Video  $video
     * @return array<int, array{question_text: string, options: array<string, string>, correct_answer: string}>
     */
    public function generateQuizForVideo(Video $video): array
    {
        // Extend PHP maximum execution time to 3 minutes for AI generation
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        if (empty($this->apiKey)) {
            Log::warning('GeminiService: API key not configured — using fallback questions.');
            return $this->getFallbackQuestions($video);
        }

        $prompt = $this->buildPrompt($video);
        $schema = $this->buildResponseSchema();
        $url    = self::API_BASE . '/' . self::GEMINI_MODEL . ':generateContent?key=' . $this->apiKey;

        $maxAttempts = 3;
        $baseDelay   = 3; // seconds per retry delay

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = Http::withHeaders(['Content-Type' => 'application/json'])
                    ->timeout(25)
                    ->post($url, [
                        'contents' => [
                            [
                                'role'  => 'user',
                                'parts' => [['text' => $prompt]],
                            ]
                        ],
                        'generationConfig' => [
                            'temperature'      => 1.0,
                            'topP'             => 0.95,
                            'maxOutputTokens'  => 4096,
                            'responseMimeType' => 'application/json',
                            'responseSchema'   => $schema,
                        ],
                        'safetySettings' => [
                            ['category' => 'HARM_CATEGORY_HARASSMENT',        'threshold' => 'BLOCK_NONE'],
                            ['category' => 'HARM_CATEGORY_HATE_SPEECH',       'threshold' => 'BLOCK_NONE'],
                            ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
                            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
                        ],
                    ]);

                if ($response->successful()) {
                    $parsed = $this->parseResponse($response);

                    if (!empty($parsed)) {
                        Log::info("GeminiService: Generated " . count($parsed) . " questions for video [{$video->id}] '{$video->title}' (attempt {$attempt})");
                        return $parsed;
                    }

                    Log::warning("GeminiService: Empty/invalid structure on attempt {$attempt}. Body: " . substr($response->body(), 0, 500));
                    if ($attempt >= 2) break;

                } elseif ($response->status() === 429) {
                    $retryAfter = (int) ($response->header('Retry-After') ?: ($baseDelay * $attempt));
                    $sleepSecs = min($retryAfter, 5);
                    Log::warning("GeminiService: Rate limited (429) on attempt {$attempt} for video [{$video->id}]. Waiting {$sleepSecs}s...");

                    if ($attempt < $maxAttempts) {
                        sleep($sleepSecs);
                        continue;
                    }

                    Log::warning("GeminiService: Rate limit not cleared after {$maxAttempts} attempts for video [{$video->id}]. Using fallback questions.");
                    break;

                } else {
                    Log::error("GeminiService: HTTP {$response->status()} on attempt {$attempt} — " . substr($response->body(), 0, 500));
                    break;
                }

            } catch (\Throwable $e) {
                Log::error("GeminiService exception on attempt {$attempt} for video [{$video->id}]: " . $e->getMessage());
                break;
            }
        }

        return $this->getFallbackQuestions($video);
    }

    /**
     * Automatically transcribe a video file using Gemini 2.0 Flash Multimodal API.
     *
     * @param string $filePath
     * @param Video $video
     * @return string
     */
    public function transcribeVideoFile(string $filePath, Video $video): string
    {
        if (empty($this->apiKey) || !file_exists($filePath)) {
            return '';
        }

        $fileSize = filesize($filePath);
        // Only use inline data if file size <= 15MB to avoid HTTP payload limits
        if ($fileSize > 15 * 1024 * 1024) {
            Log::info("GeminiService: Video file size (" . round($fileSize / 1048576, 1) . "MB) exceeds 15MB inline limit.");
            return '';
        }

        $mimeType = mime_content_type($filePath) ?: 'video/mp4';
        $base64   = base64_encode(file_get_contents($filePath));

        $prompt = <<<PROMPT
Kamu adalah sistem transkripsi otomatis profesional.
Tugasmu: Buatkan naskah/transkrip teks lengkap dalam Bahasa Indonesia berdasarkan audio/video berikut.

Judul Video: {$video->title}

ATURAN TRANSKRIP:
1. Tuliskan teks ucapan atau penjelasan materi secara lengkap dan kronologis.
2. Sertakan penanda waktu / timestamp format [MM:SS] di setiap awal kalimat atau topik baru (misal: 00:15 - Penjelasan usus buntu...).
3. Bahasa yang digunakan harus Bahasa Indonesia yang baik dan benar.
4. Output langsung berupa teks transkrip murni tanpa intro/outro salam dari AI.
PROMPT;

        $url = self::API_BASE . '/' . self::GEMINI_MODEL . ':generateContent?key=' . $this->apiKey;

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(60)
                ->post($url, [
                    'contents' => [
                        [
                            'role'  => 'user',
                            'parts' => [
                                [
                                    'inlineData' => [
                                        'mimeType' => $mimeType,
                                        'data'     => $base64,
                                    ]
                                ],
                                ['text' => $prompt]
                            ],
                        ]
                    ],
                    'generationConfig' => [
                        'temperature'     => 0.2,
                        'maxOutputTokens' => 2048,
                    ],
                ]);

            if ($response->successful()) {
                $text = $response->json('candidates.0.content.parts.0.text') ?? '';
                if (!empty(trim($text))) {
                    Log::info("GeminiService: Successfully transcribed video [{$video->id}] via API (" . strlen($text) . " chars)");
                    return trim($text);
                }
            }
        } catch (\Throwable $e) {
            Log::error("GeminiService transcribeVideoFile error: " . $e->getMessage());
        }

        return '';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build the educational prompt in English for better AI comprehension.
     * All output (questions, options) must remain in Bahasa Indonesia.
     */
    protected function buildPrompt(Video $video): string
    {
        $count      = self::QUESTION_COUNT;
        $title      = $video->title;
        $category   = $video->category?->name ?? 'Digestive Health';
        $desc       = trim($video->description ?? '');
        $transcript = trim($video->transcript ?? '');
        $duration   = $video->duration_seconds ?? 300;

        // Unique variation tag — forces Gemini to treat every request as a fresh generation.
        // Without this, low-entropy prompts can produce identical outputs across calls.
        $variationTag = strtoupper(substr(md5(uniqid((string) $video->id, true)), 0, 8));

        $descSection        = $desc !== ''
            ? "Video Description:\n{$desc}\n\n"
            : '';

        $transcriptSection  = $transcript !== ''
            ? "Full Video Transcript / Timeline (PRIMARY source for content accuracy and timestamps):\n{$transcript}\n\n"
            : '';

        $timestampGuidance = $transcript !== ''
            ? "TIMESTAMP RULE: You MUST derive the value of \"video_timestamp_seconds\" directly from the timestamp markers present in the transcript above (convert MM:SS or minute notation to total seconds, e.g. 01:30 = 90). Each question MUST be grounded in the content discussed at that specific timestamp."
            : "TIMESTAMP RULE: For each question, provide \"video_timestamp_seconds\" as an integer estimating the second in the video where the question's topic is discussed. Values must be between 0 and {$duration}. Spread timestamps evenly across the full duration to cover all content.";

        return <<<PROMPT
You are a professional digestive health education expert at DigestEdu, an Indonesian e-learning platform.
[Generation ID: {$variationTag}] — This is a unique request. Do NOT reuse or repeat any questions from previous responses.

YOUR TASK:
Generate exactly {$count} BRAND NEW multiple-choice quiz questions based STRICTLY on the following educational video. All questions, answer choices, and content MUST be written in Bahasa Indonesia (Indonesian language).

--- VIDEO METADATA ---
Title    : {$title}
Category : {$category}
Duration : {$duration} seconds

{$descSection}{$transcriptSection}--- STRICT RULES ---

1. RELEVANCE & ACCURACY (CRITICAL): Every single question MUST be 100% specific to the transcript and content of "{$title}". Extract specific terms, concepts, symptoms, and facts mentioned in the transcript.

2. RANDOM CORRECT ANSWER POSITIONS (MANDATORY): You MUST distribute the correct answers randomly across option keys A, B, C, and D across the 6 questions. Do NOT put the correct answer under 'A' for all questions!

3. DIFFICULTY DISTRIBUTION:
   - 2 easy questions   → basic knowledge / definitions about "{$title}"
   - 3 medium questions → mechanisms, causes, risk factors, clinical signs
   - 1 hard question    → clinical analysis, application, or management scenario

4. ANSWER CHOICES: Each question must have exactly 4 options (A, B, C, D) with only ONE correct answer.

5. DISTRACTORS: Wrong answer choices must be plausible but scientifically/medically incorrect — avoid obviously absurd options.

6. QUESTION QUALITY: Questions must be clear, professional, and appropriate for Indonesian health education students.

7. {$timestampGuidance}

8. LANGUAGE OUTPUT: ALL text in the response (question_text, options A/B/C/D) MUST be in Bahasa Indonesia. Do NOT use English in the output.

Return a raw JSON array of exactly {$count} objects. No markdown, no code fences.
PROMPT;
    }

    /**
     * Define the response JSON schema for Gemini 2.0's structured output feature.
     */
    protected function buildResponseSchema(): array
    {
        return [
            'type' => 'ARRAY',
            'items' => [
                'type' => 'OBJECT',
                'properties' => [
                    'question_text' => [
                        'type'        => 'STRING',
                        'description' => 'The quiz question text, written in Bahasa Indonesia.',
                    ],
                    'options' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'A' => ['type' => 'STRING', 'description' => 'Answer choice A, in Bahasa Indonesia.'],
                            'B' => ['type' => 'STRING', 'description' => 'Answer choice B, in Bahasa Indonesia.'],
                            'C' => ['type' => 'STRING', 'description' => 'Answer choice C, in Bahasa Indonesia.'],
                            'D' => ['type' => 'STRING', 'description' => 'Answer choice D, in Bahasa Indonesia.'],
                        ],
                        'required' => ['A', 'B', 'C', 'D'],
                    ],
                    'correct_answer' => [
                        'type'        => 'STRING',
                        'enum'        => ['A', 'B', 'C', 'D'],
                        'description' => 'The letter of the correct answer: A, B, C, or D.',
                    ],
                    'video_timestamp_seconds' => [
                        'type'        => 'INTEGER',
                        'description' => 'The estimated second in the video where this question\'s topic is discussed. Must be a non-negative integer.',
                    ],
                ],
                'required' => ['question_text', 'options', 'correct_answer', 'video_timestamp_seconds'],
            ],
        ];
    }

    /**
     * Parse and validate the Gemini API response.
     */
    protected function parseResponse(\Illuminate\Http\Client\Response $response): array
    {
        $rawText = $response->json('candidates.0.content.parts.0.text') ?? '';

        if (empty($rawText)) {
            $direct = $response->json();
            if (is_array($direct)) {
                $rawText = json_encode($direct);
            }
        }

        $cleaned = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($rawText));
        $data    = json_decode($cleaned, true);

        if (!is_array($data) || empty($data)) {
            return [];
        }

        $valid = [];
        foreach ($data as $item) {
            if (!is_array($item))                               continue;
            if (empty($item['question_text']))                  continue;
            if (!isset($item['options']) || !is_array($item['options'])) continue;
            if (empty($item['correct_answer']))                 continue;

            foreach (['A', 'B', 'C', 'D'] as $opt) {
                if (empty($item['options'][$opt])) continue 2;
            }

            $ts = isset($item['video_timestamp_seconds']) && is_numeric($item['video_timestamp_seconds'])
                ? (int) $item['video_timestamp_seconds']
                : null;

            $question = [
                'question_text'           => $item['question_text'],
                'options'                 => [
                    'A' => $item['options']['A'],
                    'B' => $item['options']['B'],
                    'C' => $item['options']['C'],
                    'D' => $item['options']['D'],
                ],
                'correct_answer'          => strtoupper($item['correct_answer']),
                'video_timestamp_seconds' => $ts,
            ];

            // Shuffle options to guarantee random distribution of correct answer position (A, B, C, D)
            $valid[] = $this->shuffleQuestionOptions($question);
        }

        return $valid;
    }

    /**
     * Randomly shuffle options and update correct_answer key accordingly.
     */
    protected function shuffleQuestionOptions(array $question): array
    {
        $originalOptions = $question['options'];
        $correctKey      = $question['correct_answer'];
        $correctText     = $originalOptions[$correctKey] ?? reset($originalOptions);

        $optionTexts = array_values($originalOptions);
        shuffle($optionTexts);

        $keys = ['A', 'B', 'C', 'D'];
        $newOptions = [];
        $newCorrectKey = 'A';

        foreach ($keys as $idx => $key) {
            $newOptions[$key] = $optionTexts[$idx];
            if ($optionTexts[$idx] === $correctText) {
                $newCorrectKey = $key;
            }
        }

        $question['options']        = $newOptions;
        $question['correct_answer'] = $newCorrectKey;

        return $question;
    }

    /**
     * Dynamic fallback questions customized to the specific video title.
     * Guaranteed to produce 6 questions with shuffled options.
     */
    protected function getFallbackQuestions(Video $video): array
    {
        $title = $video->title;
        $dur   = $video->duration_seconds ?? 180;

        $rawFallback = [
            [
                'question_text' => "Apa topik utama yang dijelaskan dalam video \"{$title}\"?",
                'options' => [
                    'A' => "Memahami konsep dasar, penyebab, dan penanganan terkait materi {$title}",
                    'B' => 'Mengabaikan pentingnya pola hidup sehat harian',
                    'C' => 'Mengurangi konsumsi nutrisi seimbang secara drastis',
                    'D' => 'Menghindari konsultasi kesehatan dengan tenaga medis',
                ],
                'correct_answer'          => 'A',
                'video_timestamp_seconds' => (int) round($dur * 0.1),
            ],
            [
                'question_text' => "Faktor risiko manakah yang paling relevan dengan pembahasan \"{$title}\"?",
                'options' => [
                    'A' => "Pola makan tidak seimbang dan gaya hidup yang memicu keluhan {$title}",
                    'B' => 'Penggunaan perangkat elektronik di malam hari',
                    'C' => 'Kebiasaan membaca buku saat perjalanan',
                    'D' => 'Frekuensi penggunaan alas kaki outdoor',
                ],
                'correct_answer'          => 'A',
                'video_timestamp_seconds' => (int) round($dur * 0.25),
            ],
            [
                'question_text' => "Mengapa pemahaman tentang \"{$title}\" penting bagi kesehatan sistem pencernaan?",
                'options' => [
                    'A' => 'Dapat mencegah komplikasi penyakit dan menjaga fungsi optimal organ pencernaan',
                    'B' => 'Menghambat penyerapan vitamin di usus halus',
                    'C' => 'Menghentikan proses sirkulasi darah ke ginjal',
                    'D' => 'Tidak memberikan dampak kesehatan apapun bagi tubuh',
                ],
                'correct_answer'          => 'A',
                'video_timestamp_seconds' => (int) round($dur * 0.4),
            ],
            [
                'question_text' => "Gejala klinis awal apakah yang umumnya menandakan gangguan berkaitan dengan \"{$title}\"?",
                'options' => [
                    'A' => 'Rasa tidak nyaman pada perut, nyeri lokal, atau gangguan proses pencernaan',
                    'B' => 'Penurunan ketajaman penglihatan warna secara mendadak',
                    'C' => 'Kerontokan rambut ekstrem dalam waktu singkat',
                    'D' => 'Peningkatan frekuensi denyut nadi tanpa sebab',
                ],
                'correct_answer'          => 'A',
                'video_timestamp_seconds' => (int) round($dur * 0.6),
            ],
            [
                'question_text' => "Langkah pencegahan utama manakah yang tepat sesuai penjelasan \"{$title}\"?",
                'options' => [
                    'A' => 'Menjaga asupan serat, hidrasi cukup, dan menerapkan pola makan sehat',
                    'B' => 'Mengkonsumsi makanan tinggi asam dan lemak sebelum tidur',
                    'C' => 'Menunda jadwal makan harian secara berkala',
                    'D' => 'Menghentikan konsumsi air putih hangat',
                ],
                'correct_answer'          => 'A',
                'video_timestamp_seconds' => (int) round($dur * 0.75),
            ],
            [
                'question_text' => "Tindakan medis terbaik manakah yang disarankan jika terjadi keparahan kondisi \"{$title}\"?",
                'options' => [
                    'A' => 'Melakukan konsultasi dan pemeriksaan langsung ke fasilitas kesehatan profesional',
                    'B' => 'Mengonsumsi suplemen tanpa aturan dosis dokter',
                    'C' => 'Melakukan olahraga fisik berbeban sangat berat',
                    'D' => 'Membiarkan kondisi berlanjut tanpa penanganan',
                ],
                'correct_answer'          => 'A',
                'video_timestamp_seconds' => (int) round($dur * 0.9),
            ],
        ];

        return array_map([$this, 'shuffleQuestionOptions'], $rawFallback);
    }
}
