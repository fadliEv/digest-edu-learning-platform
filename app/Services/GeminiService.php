<?php

namespace App\Services;

use App\Models\Video;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected const API_BASE       = 'https://generativelanguage.googleapis.com/v1beta/models';
    protected const QUESTION_COUNT = 6;

    protected string $apiKey;

    /** Active Gemini model, resolved from config (config/services.php → env GEMINI_MODEL). */
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key', env('GEMINI_API_KEY', ''));
        $this->model  = config('services.gemini.model', 'gemini-2.0-flash');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Generate a detailed spoken narration transcript (teks ucapan/naskah) for a video.
     *
     * @param Video $video
     * @return string
     */
    public function generateDetailedTranscript(Video $video): string
    {
        if (empty($this->apiKey)) {
            return $this->getDetailedFallbackTranscript($video);
        }

        $categoryName = $video->category?->name ?? 'Kesehatan';
        $prompt = <<<PROMPT
Kamu adalah seorang penulis naskah edukasi kesehatan pencernaan profesional di DigestEdu.
Tugasmu: Buatkan NASKAH TRANSKRIP LENGKAP PENJELASAN SUARA (SPOKEN NARRATION TRANSCRIPT) dalam Bahasa Indonesia untuk video edukasi berikut.

Judul Video: {$video->title}
Kategori: {$categoryName}
Deskripsi: {$video->description}

ATURAN TRANSKRIP:
1. Naskah harus berupa teks narasi ucapan pembicara/narator secara lengkap dan mendalam (bukan daftar ringkasan 4 baris).
2. Jelaskan fakta medis ilmiah yang spesifik mengenai "{$video->title}", termasuk mekanisme biologis, gejala, organ pencernaan yang terlibat, penyebab, dan solusinya.
3. Tuliskan dalam paragraf narasi terperinci minimal 250-400 kata.
4. Output langsung berupa teks narasi transkrip murni tanpa salam pembuka atau penjelasan meta dari AI.
PROMPT;

        $url = self::API_BASE . '/' . $this->model . ':generateContent?key=' . $this->apiKey;

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post($url, [
                    'contents' => [
                        [
                            'role'  => 'user',
                            'parts' => [['text' => $prompt]],
                        ]
                    ],
                    'generationConfig' => [
                        'temperature'     => 0.7,
                        'maxOutputTokens' => 2048,
                    ],
                ]);

            if ($response->successful()) {
                $text = trim($response->json('candidates.0.content.parts.0.text') ?? '');
                if (!empty($text)) {
                    Log::info("GeminiService: Generated detailed transcript for video [{$video->id}] '{$video->title}' (" . strlen($text) . " chars)");
                    return $text;
                }
            }
        } catch (\Throwable $e) {
            Log::error("GeminiService generateDetailedTranscript error: " . $e->getMessage());
        }

        return $this->getDetailedFallbackTranscript($video);
    }

    /**
     * Generate multiple-choice quiz questions for a given video based on its transcript.
     *
     * @param  Video  $video
     * @return array<int, array{question_text: string, options: array<string, string>, correct_answer: string}>
     */
    public function generateQuizForVideo(Video $video): array
    {
        // Do NOT cap execution to a fixed number of seconds here: this runs
        // inside a long-lived queue worker, and a positive set_time_limit()
        // resets the worker's own execution timer, which then kills the worker
        // while it sleeps between jobs. 0 = unlimited for THIS request only.
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        if (empty($this->apiKey)) {
            Log::warning('GeminiService: API key not configured — using fallback questions.');
            return $this->getFallbackQuestions($video);
        }

        // ── Guard: never generate a quiz without the exact spoken transcript.
        // Generating from title/description alone yields off-topic questions.
        if (empty(trim($video->transcript ?? ''))) {
            Log::warning("GeminiService: No transcript for video [{$video->id}] — refusing to generate an off-topic quiz. Caller should retry after transcription.");
            return [];
        }

        $prompt = $this->buildPrompt($video);
        $schema = $this->buildResponseSchema();

        // Model candidates: configured model first, then resilient fallbacks.
        // Flash models occasionally return 503 ("high demand"); the lite
        // aliases are usually available, so we fall through to them.
        $models = array_values(array_unique(array_filter([
            $this->model,
            'gemini-flash-latest',
            'gemini-flash-lite-latest',
            'gemini-3.1-flash-lite',
        ])));

        $maxAttempts = 2;   // attempts PER model before moving to the next
        $baseDelay   = 2;

        foreach ($models as $modelName) {
            $url = self::API_BASE . '/' . $modelName . ':generateContent?key=' . $this->apiKey;

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                try {
                    $response = Http::withHeaders(['Content-Type' => 'application/json'])
                        ->timeout(30)
                        ->post($url, [
                            'contents' => [
                                [
                                    'role'  => 'user',
                                    'parts' => [['text' => $prompt]],
                                ]
                            ],
                            'generationConfig' => [
                                'temperature'      => 0.3,
                                'topP'             => 0.9,
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
                            Log::info("GeminiService: Generated " . count($parsed) . " questions for video [{$video->id}] '{$video->title}' via model '{$modelName}' (attempt {$attempt})");
                            return $parsed;
                        }

                        Log::warning("GeminiService: Empty/invalid structure from '{$modelName}' attempt {$attempt}. Body: " . substr($response->body(), 0, 300));
                        // Empty body — try next model rather than looping this one.
                        break;

                    } elseif ($response->status() === 429) {
                        $retryAfter = (int) ($response->header('Retry-After') ?: ($baseDelay * $attempt));
                        $sleepSecs = min($retryAfter, 5);
                        Log::warning("GeminiService: Rate limited (429) on '{$modelName}' attempt {$attempt} for video [{$video->id}]. Waiting {$sleepSecs}s...");

                        if ($attempt < $maxAttempts) {
                            sleep($sleepSecs);
                            continue;
                        }
                        break; // move to next model

                    } elseif (in_array($response->status(), [500, 502, 503, 504], true)) {
                        // Transient server-side error (model overloaded). Retry
                        // briefly, then fall through to the NEXT candidate model.
                        Log::warning("GeminiService: Transient HTTP {$response->status()} on '{$modelName}' attempt {$attempt} for video [{$video->id}].");

                        if ($attempt < $maxAttempts) {
                            sleep(min($baseDelay * $attempt, 4));
                            continue;
                        }
                        break; // move to next model

                    } else {
                        Log::error("GeminiService: HTTP {$response->status()} on '{$modelName}' — " . substr($response->body(), 0, 300));
                        break; // non-retryable for this model; try next
                    }

                } catch (\Throwable $e) {
                    Log::error("GeminiService exception on '{$modelName}' attempt {$attempt} for video [{$video->id}]: " . $e->getMessage());
                    break; // try next model
                }
            }
        }

        Log::warning("GeminiService: All models exhausted for video [{$video->id}] — using topic fallback questions.");
        return $this->getFallbackQuestions($video);
    }

    /**
     * Transcribe audio/video file (MP3/WAV/MP4) using Gemini Multimodal Audio API.
     * Returns exact spoken words verbatim from the audio — not a paraphrase or summary.
     * Retries up to 3 times on rate limit (HTTP 429).
     */
    public function transcribeVideoFile(string $filePath, Video $video): string
    {
        if (empty($this->apiKey) || !file_exists($filePath)) {
            return '';
        }

        $fileSize = filesize($filePath);
        if ($fileSize > 20 * 1024 * 1024) {
            Log::info("GeminiService: Audio file size (" . round($fileSize / 1048576, 1) . "MB) exceeds 20MB inline limit for video [{$video->id}].");
            return '';
        }

        // Detect MIME type
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeType = match($ext) {
            'mp3'  => 'audio/mp3',
            'wav'  => 'audio/wav',
            'ogg'  => 'audio/ogg',
            'mp4'  => 'video/mp4',
            default => mime_content_type($filePath) ?: 'audio/mp3',
        };

        $base64 = base64_encode(file_get_contents($filePath));

        $prompt = <<<PROMPT
Kamu adalah transkriber audio profesional.

Tugasmu: Dengarkan audio/video berikut dan tuliskan SEMUA kata yang diucapkan secara PERSIS seperti yang terdengar — kata demi kata, dari awal hingga akhir.

ATURAN PENTING:
1. Tuliskan SEMUA ucapan dari awal hingga akhir tanpa ada yang dihilangkan.
2. JANGAN parafrase, JANGAN ringkas, JANGAN tambah informasi baru.
3. Tuliskan persis seperti yang diucapkan, termasuk kata-kata informal, humor, atau kalimat tidak lengkap.
4. Jika ada beberapa pembicara, tuliskan semua.
5. Output langsung berupa teks ucapan murni, tanpa intro atau komentar dari AI.

Judul Video: {$video->title}
PROMPT;

        $url = self::API_BASE . '/' . $this->model . ':generateContent?key=' . $this->apiKey;

        $maxAttempts = 3;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = Http::withHeaders(['Content-Type' => 'application/json'])
                    ->timeout(120)
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
                            'temperature'     => 0.0,
                            'maxOutputTokens' => 8192,
                        ],
                    ]);

                if ($response->successful()) {
                    $text = trim($response->json('candidates.0.content.parts.0.text') ?? '');
                    if (!empty($text)) {
                        Log::info("GeminiService: Transcribed audio for video [{$video->id}] — " . strlen($text) . " chars (attempt {$attempt})");
                        return $text;
                    }
                } elseif ($response->status() === 429) {
                    $waitSecs = min(30 * $attempt, 60);
                    Log::warning("GeminiService: Rate limited (429) on transcription attempt {$attempt} for video [{$video->id}]. Waiting {$waitSecs}s...");
                    if ($attempt < $maxAttempts) {
                        sleep($waitSecs);
                        continue;
                    }
                } else {
                    Log::error("GeminiService: HTTP {$response->status()} transcribing video [{$video->id}] — " . substr($response->body(), 0, 300));
                }
            } catch (\Throwable $e) {
                Log::error("GeminiService transcribeVideoFile error for video [{$video->id}]: " . $e->getMessage());
            }
            break;
        }

        return '';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal Helpers
    // ─────────────────────────────────────────────────────────────────────────

    protected function buildPrompt(Video $video): string
    {
        $count      = self::QUESTION_COUNT;
        $title      = $video->title;
        $category   = $video->category?->name ?? 'Digestive Health';
        $desc       = trim($video->description ?? '');
        $transcript = trim($video->transcript ?? '');
        $duration   = $video->duration_seconds ?? 300;

        $variationTag = strtoupper(substr(md5(uniqid((string) $video->id, true)), 0, 8));

        $descSection = $desc !== '' ? "Deskripsi Video (konteks tambahan, BUKAN sumber soal):\n{$desc}\n\n" : '';

        return <<<PROMPT
Anda adalah AI penyusun soal kuis edukasi di DigestEdu, platform pembelajaran kesehatan pencernaan berbahasa Indonesia.
[Generation ID: {$variationTag}]

SUMBER SATU-SATUNYA (NASKAH UCAPAN VIDEO "{$title}"):
\"\"\"
{$transcript}
\"\"\"

{$descSection}TUGAS ANDA:
Buat TEPAT {$count} soal pilihan ganda (A, B, C, D) dalam Bahasa Indonesia, HANYA berdasarkan fakta yang BENAR-BENAR DIUCAPKAN di dalam naskah di atas.

--- ATURAN MUTLAK (WAJIB DIPATUHI) ---

1. GROUNDED KE NASKAH — INI ATURAN TERPENTING:
   - Setiap pertanyaan DAN jawaban yang benar HARUS bisa ditemukan/dibuktikan langsung dari kalimat di dalam naskah.
   - DILARANG KERAS menambahkan pengetahuan medis dari luar naskah. Jika sebuah fakta (istilah, angka, nama organ, penyebab, gejala, contoh makanan, cara pencegahan) TIDAK disebut di naskah, JANGAN jadikan soal atau jawaban benar.
   - Contoh larangan: jika naskah tidak menyebut "sembelit", "brokoli", atau "nitrogen", maka DILARANG membuat soal tentang itu — walaupun secara medis benar.
   - Jangan mengarang mekanisme klinis, manajemen penyakit, atau istilah ilmiah yang tidak muncul di naskah.

2. JIKA NASKAH TERBATAS:
   - Naskah bisa saja santai, singkat, atau bergaya percakapan. Tetap buat soal HANYA dari isi yang ada.
   - Jika informasi di naskah tidak cukup untuk {$count} soal berkualitas, buatlah sebanyak yang bisa didukung naskah (boleh kurang dari {$count}), TETAPI JANGAN pernah menambal dengan fakta karangan.

3. PENGECOH (DISTRACTOR):
   - Hanya 1 jawaban benar per soal (harus dari naskah).
   - Pilihan yang salah (pengecoh) boleh berupa pernyataan yang masuk akal namun keliru, ASAL tidak menyesatkan seolah-olah itu ada di naskah. Pengecoh tidak perlu ada di naskah, tetapi jawaban BENAR wajib ada di naskah.

4. VARIASI TINGKAT KESULITAN (berdasarkan isi naskah, bukan menambah materi):
   - Mudah  → definisi/istilah/penyebab yang disebut langsung di naskah.
   - Sedang → hubungan sebab-akibat atau proses yang dijelaskan di naskah.
   - Sulit  → menggabungkan beberapa fakta yang SEMUANYA berasal dari naskah (tetap tanpa pengetahuan luar).

5. POSISI JAWABAN BENAR: acak di antara A, B, C, D antar soal.

6. BAHASA: seluruh teks dalam Bahasa Indonesia yang baku dan jelas.

7. video_timestamp_seconds: perkiraan detik di video saat fakta tersebut diucapkan (berdasarkan urutan kemunculan di naskah). Jika tidak yakin, boleh 0.

Kembalikan HANYA array JSON berisi objek-objek soal sesuai skema.
PROMPT;
    }

    protected function buildResponseSchema(): array
    {
        return [
            'type' => 'ARRAY',
            'items' => [
                'type' => 'OBJECT',
                'properties' => [
                    'question_text' => [
                        'type'        => 'STRING',
                        'description' => 'The quiz question text in Bahasa Indonesia.',
                    ],
                    'options' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'A' => ['type' => 'STRING'],
                            'B' => ['type' => 'STRING'],
                            'C' => ['type' => 'STRING'],
                            'D' => ['type' => 'STRING'],
                        ],
                        'required' => ['A', 'B', 'C', 'D'],
                    ],
                    'correct_answer' => [
                        'type' => 'STRING',
                        'enum' => ['A', 'B', 'C', 'D'],
                    ],
                    'video_timestamp_seconds' => [
                        'type' => 'INTEGER',
                    ],
                ],
                'required' => ['question_text', 'options', 'correct_answer', 'video_timestamp_seconds'],
            ],
        ];
    }

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
            if (!is_array($item)) continue;
            if (empty($item['question_text'])) continue;
            if (!isset($item['options']) || !is_array($item['options'])) continue;
            if (empty($item['correct_answer'])) continue;

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

            $valid[] = $this->shuffleQuestionOptions($question);
        }

        return $valid;
    }

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
     * Fallback rich spoken transcripts for known video topics.
     */
    public function getDetailedFallbackTranscript(Video $video): string
    {
        $title = strtolower($video->title);

        if (str_contains($title, 'puasa')) {
            return <<<TRANSCRIPT
Saat kita berpuasa, tubuh mengalami perubahan biologis dan metabolik yang sangat terstruktur. Pada 4 hingga 8 jam pertama setelah makan sahur, tubuh mengandalkan glukosa yang diserap dari makanan sebagai sumber energi utama. Setelah kadar glukosa dalam darah menurun, organ hati (hepar) mulai memecah cadangan glikogen melalui proses glikogenolisis untuk menjaga kestabilan kadar gula darah.

Memasuki jam ke-12 puasa, cadangan glikogen di hati hampir habis. Pada fase ini, pankreas menurunkan sekresi hormon insulin dan meningkatkan produksi hormon glukagon. Kondisi ini memicu tubuh untuk beralih menggunakan cadangan lemak sebagai sumber energi utama melalui proses ketosis. Hati mengubah asam lemak menjadi badan keton yang dapat digunakan oleh otak dan sel tubuh lainnya.

Selain perubahan energi, sistem pencernaan seperti lambung, usus halus, dan pankreas mendapatkan kesempatan untuk beristirahat. Produksi asam lambung berkurang, dan dinding pencernaan melakukan proses regenerasi sel serta pembersihan sisa makanan yang disebut siklus MMC (Migrating Motor Complex). Puasa juga merangsang proses autofagi, yaitu mekanisme pembersihan alami di mana sel-sel tubuh menghancurkan komponen sel yang rusak dan meregenerasi sel baru yang lebih sehat.
TRANSCRIPT;
        }

        if (str_contains($title, 'kentut') || str_contains($title, 'bau')) {
            return <<<TRANSCRIPT
Kentut atau flatus adalah campuran gas dalam saluran pencernaan yang dikeluarkan melalui anus. Sebagian besar gas dalam perut sebenarnya tidak berbau, seperti nitrogen, oksigen, karbondioksida, hidrogen, dan metana. Namun, bau menyengat pada kentut disebabkan oleh sejumlah kecil gas yang mengandung sulfur atau belerang, terutama hidrogen sulfida (H2S), metanetiol, dan dimetil sulfida.

Gas berbau sulfur ini dihasilkan oleh mikrobioma atau bakteri di usus besar saat mem fermentasi makanan yang kaya akan senyawa hidrogen sulfida. Makanan yang kaya protein (seperti telur, daging, dan produk susu) serta sayuran cruciferous (seperti kubis, brokoli, dan kembang kol) mengandung serat rantai pendek (FODMAP) dan asam amino kaya sulfur seperti sistein dan metionin.

Ketika makanan tersebut tidak tercerna sepenuhnya di usus halus, bakteri usus besar akan memecahnya dan menghasilkan gas hidrogen sulfida sebagai produk sampingan. Selain makanan, kentut yang sangat berbau juga bisa dipengaruhi oleh sembelit (konstipasi), karena feses yang tertahan lama di usus besar memberikan lebih banyak waktu bagi bakteri untuk membusukkan serat dan menghasilkan gas berbau busuk.
TRANSCRIPT;
        }

        if (str_contains($title, 'usus buntu') || str_contains($title, 'radang usus')) {
            return <<<TRANSCRIPT
Radang usus buntu atau apendisitis adalah peradangan yang terjadi pada apendiks vermiformis, yaitu organ berbentuk kantung kecil menempel pada bagian awal usus besar (sekum) di perut kanan bawah. Penyebab utama apendisitis adalah penyumbatan pada lumen (saluran dalam) apendiks. Penyumbatan ini paling sering disebabkan oleh fekalit (penumpukan feses yang mengeras), pembengkakan jaringan limfoid di dinding usus akibat infeksi, atau masuknya benda asing.

Ketika lumen apendiks tersumbat, bakteri di dalamnya berkembang biak secara cepat. Tekanan di dalam kantung apendiks meningkat karena penumpukan mukus dan nanah, yang mengganggu aliran darah dan pembuluh limfa di dinding usus buntu. Akibatnya, dinding apendiks mengalami iskemia (kekurangan oksigen) dan nekrosis (kematian jaringan).

Gejala khas apendisitis dimulai dengan nyeri tumpul di sekitar pusar yang kemudian berpindah dan menetap di perut kanan bawah (area yang dikenal sebagai titik McBurney). Nyeri bertambah parah saat batuk, berjalan, atau ditekan. Jika tidak segera ditangani medis melalui tindakan operasi apendektomi, apendiks yang membengkak dapat pecah (perforasi) dan menyebabkan infeksi peritoneum parah (peritonitis) yang membahayakan nyawa.
TRANSCRIPT;
        }

        if (str_contains($title, 'nyamuk') || str_contains($title, 'bentol')) {
            return <<<TRANSCRIPT
Saat nyamuk betina menggigit kulit manusia untuk mengisap darah, nyamuk akan menyuntikkan air liurnya yang mengandung senyawa antikoagulan (pencegah pembekuan darah) dan enzim protein tertentu. Senyawa asing dalam air liur nyamuk ini dideteksi oleh sistem kekebalan tubuh sebagai ancaman asing.

Sistem imun merespons dengan melepaskan antibodi dan merangsang sel mast (mast cells) di bawah kulit untuk melepaskan histamin. Histamin adalah zat kimia yang menyebabkan pembuluh darah di sekitar gigitan melebar (vasodilatasi) dan meningkatkan permeabilitas pembuluh darah. Penumpukan cairan dari pembuluh darah ke jaringan kulit sekitarnya membentuk bentol merah yang menonjol (bentol atau wheal).

Selain itu, histamin juga merangsang serabut saraf sensorik di kulit yang mengirimkan sinyal gatal ke otak. Memotong atau menggaruk bentol secara berlebihan dapat merusak benteng kulit, memicu pelepasan lebih banyak histamin, dan meningkatkan risiko infeksi bakteri sekunder pada bekas gigitan nyamuk.
TRANSCRIPT;
        }

        if (str_contains($title, 'gula')) {
            return <<<TRANSCRIPT
Konsumsi gula berlebih, terutama jenis gula sederhana seperti fruktosa dan glukosa terrefinasi, memiliki dampak sistemik negatif terhadap sistem pencernaan dan metabolisme tubuh. Saat mengonsumsi gula berlebih, pankreas harus bekerja ekstra keras melepaskan hormon insulin untuk menurunkan kadar gula darah yang melonjak drastis.

Dalam saluran pencernaan, gula berlebih yang tidak terserap di usus halus menjadi makanan utama bagi bakteri patogen dan jamur Candida di usus besar. Hal ini menyebabkan ketidakseimbangan mikrobioma usus (disbiosis), yang dapat merusak lapisan mukosa usus (gut barrier) dan meningkatkan permeabilitas usus atau sindrom usus bocor (leaky gut).

Selain itu, kelebihan fruktosa diolah langsung di hati (hepar). Hati merubah kelebihan fruktosa menjadi trigliserida melalui proses lipogenesis de novo. Penumpukan trigliserida di sel-sel hati menyebabkan penyakit hati berlemak non-alkoholik (NAFLD/perlemakan hati) yang memicu peradangan kronis dan resistensi insulin.
TRANSCRIPT;
        }

        if (str_contains($title, 'pipis') || str_contains($title, 'kencing')) {
            return <<<TRANSCRIPT
Kebiasaan menahan buang air kecil (pipis) secara terus-menerus memberikan tekanan fisik dan risiko biologis yang signifikan pada kantung kemih (vesika urinaria) dan organ pencernaan sekitarnya. Kantung kemih manusia memiliki kapasitas rata-rata 300 hingga 500 ml urin. Ketika kantung kemih penuh, saraf meregang dan mengirimkan sinyal ke otak untuk memicu dorongan berkemih.

Menahan pipis terlalu lama memaksa otot sfingter uretra luar bekerja keras menahan cairan, yang berisiko melemahkan otot dasar panggul dan menyebabkan inkontinensia urin. Selain itu, penumpukan urin yang tertahan menjadi media ideal bagi perkembangbiakan bakteri seperti Escherichia coli. Bakteri dapat naik ke atas menuju uretra dan kandung kemih, menyebabkan Infeksi Saluran Kemih (ISK) seperti sistitis.

Jika kebiasaan ini berlanjut, urin yang bertekanan tinggi dapat membalik naik ke ginjal (refluks vesikoureter) dan memicu infeksi ginjal (pielonefritis) atau pembentukan batu ginjal. Dalam jangka panjang, peregangan berlebihan pada kantung kemih juga dapat mengganggu ritme saraf pencernaan di area panggul.
TRANSCRIPT;
        }

        return <<<TRANSCRIPT
Materi edukasi mengenai {$video->title} menjelaskan konsep dasar, fisiologi, serta dampaknya terhadap sistem organ manusia. Penjelasan mencakup bagaimana faktor gizi, metabolisme seluler, serta kebiasaan sehari-hari mempengaruhi fungsi organ pencernaan seperti lambung, usus halus, usus besar, dan hati. Dengan memahami proses biologis ini, peserta didik dapat mengidentifikasi gejala awal gangguan kesehatan serta menerapkan langkah pencegahan secara mandiri demi menjaga kesehatan tubuh secara holistik.
TRANSCRIPT;
    }

    /**
     * Fallback questions customized specifically per video topic.
     */
    protected function getFallbackQuestions(Video $video): array
    {
        $title = strtolower($video->title);
        $dur   = $video->duration_seconds ?? 180;

        if (str_contains($title, 'puasa')) {
            $questions = [
                [
                    'question_text' => 'Sumber energi utama apakah yang digunakan tubuh pada 4 hingga 8 jam pertama setelah makan sahur?',
                    'options' => [
                        'A' => 'Glukosa dari makanan yang baru dicerna',
                        'B' => 'Asam lemak dari jaringan adiposa',
                        'C' => 'Badan keton dari proses ketosis',
                        'D' => 'Protein otot dari proses glukoneogenesis',
                    ],
                    'correct_answer'          => 'A',
                    'video_timestamp_seconds' => (int) round($dur * 0.15),
                ],
                [
                    'question_text' => 'Proses apakah yang dilakukan organ hati saat cadangan glukosa darah mulai menurun saat berpuasa?',
                    'options' => [
                        'A' => 'Glikogenolisis (pemecahan glikogen menjadi glukosa)',
                        'B' => 'Glikolisis anaerobik di otot rangka',
                        'C' => 'Inaktivasi fungsi pankreas total',
                        'D' => 'Penumpukan asam laktat di kantung empedu',
                    ],
                    'correct_answer'          => 'A',
                    'video_timestamp_seconds' => (int) round($dur * 0.3),
                ],
                [
                    'question_text' => 'Perubahan hormon apakah yang terjadi pada pankreas ketika memasuki jam ke-12 puasa?',
                    'options' => [
                        'A' => 'Penurunan sekresi insulin dan peningkatan sekresi glukagon',
                        'B' => 'Peningkatan tajam sekresi insulin dan tiroksin',
                        'C' => 'Penghentian total hormon pertumbuhan',
                        'D' => 'Penurunan hormon glukagon dan peningkatan kortisol',
                    ],
                    'correct_answer'          => 'A',
                    'video_timestamp_seconds' => (int) round($dur * 0.45),
                ],
                [
                    'question_text' => 'Apakah yang dimaksud dengan proses ketosis yang terjadi saat puasa berkepanjangan?',
                    'options' => [
                        'A' => 'Pengubahan asam lemak menjadi badan keton oleh hati sebagai energi alternatif',
                        'B' => 'Pembengkakan jaringan lambung akibat akumulasi gas',
                        'C' => 'Peningkatan pengeluaran asam empedu ke duodenum',
                        'D' => 'Penebalan dinding mukosa usus halus',
                    ],
                    'correct_answer'          => 'A',
                    'video_timestamp_seconds' => (int) round($dur * 0.6),
                ],
                [
                    'question_text' => 'Siklus pembersihan alami di mana sel-sel tubuh merusak sel yang rusak dan meregenerasi sel baru saat berpuasa disebut:',
                    'options' => [
                        'A' => 'Autofagi',
                        'B' => 'Apoptosis sekunder',
                        'C' => 'Glikolisis',
                        'D' => 'Emulsifikasi',
                    ],
                    'correct_answer'          => 'A',
                    'video_timestamp_seconds' => (int) round($dur * 0.75),
                ],
                [
                    'question_text' => 'Manfaat utama beristirahatnya organ pencernaan seperti lambung dan usus saat berpuasa adalah:',
                    'options' => [
                        'A' => 'Memberikan waktu bagi dinding mukosa pencernaan untuk regenerasi sel dan pembersihan seluler',
                        'B' => 'Menghentikan sirkulasi darah ke ginjal',
                        'C' => 'Menghilangkan bakteri baik usus secara permanen',
                        'D' => 'Mencegah pembentukan hormon pertumbuhan',
                    ],
                    'correct_answer'          => 'A',
                    'video_timestamp_seconds' => (int) round($dur * 0.9),
                ],
            ];
            return array_map([$this, 'shuffleQuestionOptions'], $questions);
        }

        if (str_contains($title, 'kentut') || str_contains($title, 'bau')) {
            $questions = [
                [
                    'question_text' => 'Senyawa apakah yang menjadi penyebab utama bau menyengat pada kentut?',
                    'options' => [
                        'A' => 'Gas sulfur/belerang seperti hidrogen sulfida (H2S)',
                        'B' => 'Gas nitrogen murni (N2)',
                        'C' => 'Uap air (H2O)',
                        'D' => 'Oksigen (O2)',
                    ],
                    'correct_answer'          => 'A',
                    'video_timestamp_seconds' => (int) round($dur * 0.15),
                ],
                [
                    'question_text' => 'Di manakah tempat terjadinya proses fermentasi makanan oleh bakteri yang menghasilkan gas berbau?',
                    'options' => [
                        'A' => 'Usus besar (kolon)',
                        'B' => 'Lambung (gaster)',
                        'C' => 'Esofagus',
                        'D' => 'Kantung empedu',
                    ],
                    'correct_answer'          => 'A',
                    'video_timestamp_seconds' => (int) round($dur * 0.3),
                ],
                [
                    'question_text' => 'Jenis makanan apakah yang kaya akan protein dan zat sulfur sehingga memicu kentut berbau busuk?',
                    'options' => [
                        'A' => 'Telur, daging merah, serta sayuran cruciferous seperti brokoli dan kubis',
                        'B' => 'Buah semangka dan melon',
                        'C' => 'Nasi putih dan kentang rebus',
                        'D' => 'Minyak zaitun dan mentega murni',
                    ],
                    'correct_answer'          => 'A',
                    'video_timestamp_seconds' => (int) round($dur * 0.45),
                ],
                [
                    'question_text' => 'Mengapa kondisi sembelit (konstipasi) dapat memperparah bau kentut seseorang?',
                    'options' => [
                        'A' => 'Feses yang tertahan lama memberikan waktu lebih banyak bagi bakteri usus untuk membusukkan makanan dan melepaskan gas sulfur',
                        'B' => 'Sembelit meningkatkan kadar karbondioksida di lambung',
                        'C' => 'Sembelit menghentikan produksi enzim pankreas',
                        'D' => 'Sembelit mempercepat gerakan peristaltik usus',
                    ],
                    'correct_answer'          => 'A',
                    'video_timestamp_seconds' => (int) round($dur * 0.6),
                ],
                [
                    'question_text' => 'Gas berikut manakah yang sebenarnya menyusun porsi terbesar dari kentut namun TIDAK berbau?',
                    'options' => [
                        'A' => 'Nitrogen dan karbondioksida',
                        'B' => 'Hidrogen sulfida dan metanetiol',
                        'C' => 'Klorida dan amonia',
                        'D' => 'Asam sulfat dan etanol',
                    ],
                    'correct_answer'          => 'A',
                    'video_timestamp_seconds' => (int) round($dur * 0.75),
                ],
                [
                    'question_text' => 'Langkah praktis yang paling efektif untuk mengontrol dan mengurangi kentut berbau busuk adalah:',
                    'options' => [
                        'A' => 'Mengatur asupan makanan tinggi sulfur dan menjaga kelancaran buang air besar harian',
                        'B' => 'Menghentikan konsumsi minum air putih',
                        'C' => 'Mengunyah makanan secepat mungkin',
                        'D' => 'Menahan kentut sekuat tenaga sepanjang hari',
                    ],
                    'correct_answer'          => 'A',
                    'video_timestamp_seconds' => (int) round($dur * 0.9),
                ],
            ];
            return array_map([$this, 'shuffleQuestionOptions'], $questions);
        }

        if (str_contains($title, 'usus buntu') || str_contains($title, 'radang usus')) {
            $questions = [
                [
                    'question_text' => 'Apakah penyebab utama peradangan pada radang usus buntu (apendisitis)?',
                    'options' => [
                        'A' => 'Penyumbatan lumen (saluran dalam) apendiks oleh fekalit (feses keras) atau pembengkakan limfoid',
                        'B' => 'Konsumsi air es berlebih saat cuaca panas',
                        'C' => 'Infeksi jamur pada kantung kemih',
                        'D' => 'Kekurangan vitamin C kronis',
                    ],
                    'correct_answer'          => 'A',
                    'video_timestamp_seconds' => (int) round($dur * 0.15),
                ],
                [
                    'question_text' => 'Titik nyeri khas di perut kanan bawah yang menjadi indikator utama apendisitis disebut:',
                    'options' => [
                        'A' => 'Titik McBurney',
                        'B' => 'Titik Erb',
                        'C' => 'Kanal Inguinal',
                        'D' => 'Titik Larynx',
                    ],
                    'correct_answer'          => 'A',
                    'video_timestamp_seconds' => (int) round($dur * 0.3),
                ],
                [
                    'question_text' => 'Apakah bahaya utama jika apendisitis dibiarkan tanpa penanganan medis yang cepat?',
                    'options' => [
                        'A' => 'Apendiks dapat pecah (perforasi) dan memicu infeksi peritoneum (peritonitis)',
                        'B' => 'Terjadinya penurunan fungsi pendengaran',
                        'C' => 'Kerusakan permanen pada enamel gigi',
                        'D' => 'Peningkatan sel darah merah berlebih',
                    ],
                    'correct_answer'          => 'A',
                    'video_timestamp_seconds' => (int) round($dur * 0.45),
                ],
                [
                    'question_text' => 'Lokasi awal nyeri perut pada penderita radang usus buntu sebelum berpindah ke kanan bawah adalah:',
                    'options' => [
                        'A' => 'Di sekitar pusar (umbilikus)',
                        'B' => 'Di punggung bagian atas',
                        'C' => 'Di bawah dada sebelah kiri',
                        'D' => 'Di persendian lutut',
                    ],
                    'correct_answer'          => 'A',
                    'video_timestamp_seconds' => (int) round($dur * 0.6),
                ],
                [
                    'question_text' => 'Proses yang terjadi di dalam dinding apendiks saat tekanan cairan nanah meningkat adalah:',
                    'options' => [
                        'A' => 'Iskemia (kekurangan aliran darah) dan nekrosis jaringan',
                        'B' => 'Pembentukan hormon insulin secara cepat',
                        'C' => 'Penyerapan asam empedu ke dalam usus halus',
                        'D' => 'Peningkatan lapisan mukosa pelindung',
                    ],
                    'correct_answer'          => 'A',
                    'video_timestamp_seconds' => (int) round($dur * 0.75),
                ],
                [
                    'question_text' => 'Tindakan medis standar terbaik untuk menangani pasien apendisitis akut adalah:',
                    'options' => [
                        'A' => 'Operasi pengangkatan usus buntu (Apendektomi)',
                        'B' => 'Pijat refleksi area abdomen secara keras',
                        'C' => 'Pemberian kompres es hangat di pusar',
                        'D' => 'Konsumsi obat pencahar dosis tinggi',
                    ],
                    'correct_answer'          => 'A',
                    'video_timestamp_seconds' => (int) round($dur * 0.9),
                ],
            ];
            return array_map([$this, 'shuffleQuestionOptions'], $questions);
        }

        // Standard topic fallback — a real generic question set (NOT recursion).
        $t = $video->title;
        $questions = [
            [
                'question_text' => "Apa fokus utama pembahasan pada video \"{$t}\"?",
                'options' => [
                    'A' => "Konsep dan penjelasan seputar {$t} pada kesehatan pencernaan",
                    'B' => 'Teknik perakitan mesin industri',
                    'C' => 'Sejarah perkembangan seni rupa modern',
                    'D' => 'Cara pemrograman aplikasi seluler',
                ],
                'correct_answer'          => 'A',
                'video_timestamp_seconds' => (int) round($dur * 0.1),
            ],
            [
                'question_text' => "Mengapa topik \"{$t}\" penting untuk kesehatan sistem pencernaan?",
                'options' => [
                    'A' => 'Karena berkaitan langsung dengan fungsi dan kesehatan organ pencernaan',
                    'B' => 'Karena menentukan kecepatan koneksi internet',
                    'C' => 'Karena memengaruhi cuaca dan iklim',
                    'D' => 'Karena mengatur nilai tukar mata uang',
                ],
                'correct_answer'          => 'A',
                'video_timestamp_seconds' => (int) round($dur * 0.35),
            ],
            [
                'question_text' => 'Organ manakah yang paling erat kaitannya dengan sistem pencernaan manusia?',
                'options' => [
                    'A' => 'Lambung dan usus',
                    'B' => 'Paru-paru dan trakea',
                    'C' => 'Ginjal dan kandung kemih',
                    'D' => 'Jantung dan pembuluh arteri',
                ],
                'correct_answer'          => 'A',
                'video_timestamp_seconds' => (int) round($dur * 0.55),
            ],
            [
                'question_text' => "Sikap paling tepat setelah memahami materi \"{$t}\" adalah:",
                'options' => [
                    'A' => 'Menerapkan pola hidup sehat untuk menjaga kesehatan pencernaan',
                    'B' => 'Mengabaikan gejala gangguan pencernaan',
                    'C' => 'Menghindari konsumsi air putih sepenuhnya',
                    'D' => 'Menahan keluhan tanpa mencari informasi',
                ],
                'correct_answer'          => 'A',
                'video_timestamp_seconds' => (int) round($dur * 0.8),
            ],
        ];

        return array_map([$this, 'shuffleQuestionOptions'], $questions);
    }
}
