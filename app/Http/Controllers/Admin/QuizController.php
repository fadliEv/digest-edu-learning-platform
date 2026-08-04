<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateQuizJob;
use App\Models\Question;
use App\Models\Video;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuizController extends Controller
{
    /**
     * Display Overview list of all videos and their quiz status for Admin Sidebar "Manajemen Kuis".
     */
    public function listAll(): Response
    {
        $videos = Video::query()
            ->with(['category', 'questions'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn(Video $v) => [
                'id' => $v->id,
                'title' => $v->title,
                'category_name' => $v->category?->name ?? 'Uncategorized',
                'quiz_status' => $v->quiz_status,
                'is_published' => $v->is_published,
                'questions_count' => $v->questions->count(),
                'created_at' => $v->created_at->setTimezone('Asia/Jakarta')->format('d M Y, H:i'),
            ]);

        $stats = [
            'total_videos' => Video::count(),
            'quiz_active' => Video::where('quiz_status', 'aktif')->count(),
            'quiz_proses' => Video::where('quiz_status', 'proses')->count(),
            'quiz_belum' => Video::where('quiz_status', 'belum')->count(),
        ];

        return Inertia::render('Admin/Quiz/List', [
            'videos' => $videos,
            'stats' => $stats,
        ]);
    }

    /**
     * Display Quiz Management page for a specific video.
     */
    public function index(Video $video): Response
    {
        $video->load(['category', 'questions' => fn($q) => $q->orderBy('order_number')]);

        return Inertia::render('Admin/Quiz/Index', [
            'video' => [
                'id'               => $video->id,
                'title'            => $video->title,
                'description'      => $video->description,
                'dyntube_video_id' => $video->dyntube_video_id,
                'category_name'    => $video->category?->name ?? 'Uncategorized',
                'quiz_status'      => $video->quiz_status,
                'is_published'     => $video->is_published,
                'questions_count'  => $video->questions->count(),
                'duration_seconds' => $video->duration_seconds,
            ],
            'questions' => $video->questions->map(fn(Question $q) => [
                'id'                      => $q->id,
                'video_id'               => $q->video_id,
                'question_text'          => $q->question_text,
                'options'                => $q->options,
                'correct_answer'         => $q->correct_answer,
                'order_number'           => $q->order_number,
                'video_timestamp_seconds'=> $q->video_timestamp_seconds,
            ]),
        ]);
    }

    /**
     * Generate / Regenerate AI Quiz using Gemini API.
     */
    public function generate(Video $video): RedirectResponse
    {
        try {
            GenerateQuizJob::dispatchSync($video);
            return redirect()->back()->with('success', 'Kuis AI berhasil diproses oleh Gemini 2.0 Flash!');
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'RATE_LIMIT_EXCEEDED') {
                return redirect()->back()->with('error', 'Gemini API sedang mencapai batas limit gratis (Rate Limit 429). Silakan tunggu ~1 menit sebelum klik Generate lagi.');
            }
            return redirect()->back()->with('error', 'Gagal memproses kuis AI: ' . $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan saat memproses kuis AI.');
        }
    }

    /**
     * Store a manually created question for a video.
     */
    public function storeQuestion(Request $request, Video $video): RedirectResponse
    {
        $validated = $request->validate([
            'question_text'           => 'required|string',
            'option_a'                => 'required|string',
            'option_b'                => 'required|string',
            'option_c'                => 'required|string',
            'option_d'                => 'required|string',
            'correct_answer'          => 'required|in:A,B,C,D',
            'video_timestamp_seconds' => 'nullable|integer|min:0',
        ]);

        $maxOrder = $video->questions()->max('order_number') ?? 0;

        $video->questions()->create([
            'question_text'           => $validated['question_text'],
            'options'                 => [
                'A' => $validated['option_a'],
                'B' => $validated['option_b'],
                'C' => $validated['option_c'],
                'D' => $validated['option_d'],
            ],
            'correct_answer'          => $validated['correct_answer'],
            'order_number'            => $maxOrder + 1,
            'video_timestamp_seconds' => $validated['video_timestamp_seconds'] ?? null,
        ]);

        $video->update(['quiz_status' => 'aktif']);

        return redirect()->back()->with('success', 'Soal kuis berhasil ditambahkan!');
    }

    /**
     * Update an existing question.
     */
    public function updateQuestion(Request $request, Question $question): RedirectResponse
    {
        $validated = $request->validate([
            'question_text'           => 'required|string',
            'option_a'                => 'required|string',
            'option_b'                => 'required|string',
            'option_c'                => 'required|string',
            'option_d'                => 'required|string',
            'correct_answer'          => 'required|in:A,B,C,D',
            'video_timestamp_seconds' => 'nullable|integer|min:0',
        ]);

        $question->update([
            'question_text'           => $validated['question_text'],
            'options'                 => [
                'A' => $validated['option_a'],
                'B' => $validated['option_b'],
                'C' => $validated['option_c'],
                'D' => $validated['option_d'],
            ],
            'correct_answer'          => $validated['correct_answer'],
            'video_timestamp_seconds' => $validated['video_timestamp_seconds'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Soal kuis berhasil diperbarui!');
    }

    /**
     * Delete a question.
     */
    public function destroyQuestion(Question $question): RedirectResponse
    {
        $video = $question->video;
        $question->delete();

        if ($video->questions()->count() === 0) {
            $video->update(['quiz_status' => 'belum']);
        }

        return redirect()->back()->with('success', 'Soal kuis berhasil dihapus!');
    }
}
