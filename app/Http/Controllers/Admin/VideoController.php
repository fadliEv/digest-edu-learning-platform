<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateQuizJob;
use App\Jobs\GenerateTranscriptJob;
use App\Models\Category;
use App\Models\Video;
use App\Services\DyntubeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Inertia\Inertia;
use Inertia\Response;

class VideoController extends Controller
{
    /**
     * Display list of videos for admin.
     */
    public function index(Request $request): Response
    {
        $categories = Category::orderBy('name')->get(['id', 'name']);

        $query = Video::query()->with('category')->orderByDesc('created_at');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('title', 'like', "%{$search}%")
                ->orWhere('dyntube_video_id', 'like', "%{$search}%");
        }

        if ($request->filled('category')) {
            $query->where('category_id', $request->input('category'));
        }

        // Auto-update transcoding status for videos whose timer has expired
        Video::where('transcoding_status', 'transcoding')
            ->whereNotNull('transcoding_ready_at')
            ->where('transcoding_ready_at', '<=', now())
            ->update(['transcoding_status' => 'ready']);

        $videos = $query->paginate(10)->through(fn (Video $v) => [
            'id' => $v->id,
            'title' => $v->title,
            'description' => $v->description,
            'dyntube_video_id' => $v->dyntube_video_id,
            'dyntube_iframe_link' => $v->dyntube_iframe_link,
            'dyntube_thumbnail' => $v->dyntube_thumbnail,
            'category_id' => $v->category_id,
            'category_name' => $v->category?->name ?? 'Uncategorized',
            'view_count' => $v->view_count,
            'quiz_status' => $v->quiz_status,
            'transcoding_status' => $v->transcoding_status,
            'is_published' => $v->is_published,
            'duration_formatted' => $v->duration_formatted,
            'created_at' => $v->created_at->setTimezone('Asia/Jakarta')->format('d M Y, H:i'),
        ]);

        $stats = [
            'total_videos' => Video::count(),
            'total_views' => Video::sum('view_count'),
            'quiz_active' => Video::where('quiz_status', 'aktif')->count(),
            'published' => Video::where('is_published', true)->count(),
            'transcoding' => Video::where('transcoding_status', 'transcoding')->count(),
        ];

        return Inertia::render('Admin/Videos/Index', [
            'videos' => $videos,
            'categories' => $categories,
            'stats' => $stats,
            'filters' => $request->only(['search', 'category']),
        ]);
    }

    /**
     * Store new video in database via direct file upload & optionally dispatch AI Quiz Generation job.
     */
    public function store(Request $request, DyntubeService $dyntubeService): RedirectResponse
    {
        // Block upload if there is already a video currently transcoding
        $existingTranscoding = Video::where('transcoding_status', 'transcoding')->exists();
        if ($existingTranscoding) {
            return redirect()->back()->with('error', 'Masih ada video lain yang sedang dalam proses transcoding. Silakan tunggu hingga selesai.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'video_file' => 'nullable|file|mimes:mp4,mov,avi,mkv,webm|max:204800', // 200MB max
            'dyntube_video_id' => 'nullable|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string',
            'generate_quiz' => 'nullable|boolean',
            'is_published' => 'nullable|boolean',
        ]);

        $dyntubeInfo = null;

        // Direct upload to Dyntube via API
        if ($request->hasFile('video_file')) {
            $uploadedFile = $request->file('video_file');
            $dyntubeInfo = $dyntubeService->uploadVideo($uploadedFile, $validated['title']);
        } elseif (! empty($validated['dyntube_video_id'])) {
            $dyntubeInfo = $dyntubeService->getVideoDetails($validated['dyntube_video_id']);
        } else {
            return redirect()->back()->with('error', 'Pilih file video MP4 untuk diunggah!');
        }

        $videoId = $dyntubeInfo['video_id'] ?? $dyntubeInfo['iframe_link'] ?? 'v_'.uniqid();
        $durationSeconds = $dyntubeInfo['duration_seconds'] ?? 180;

        // Simulation transcoding time:
        // <= 5 min (300s) -> 60s
        // 5-7 min (300-420s) -> 90s
        // > 7 min (420s+) -> 120s
        $transcodingDelaySeconds = 60;
        if ($durationSeconds > 420) {
            $transcodingDelaySeconds = 120;
        } elseif ($durationSeconds > 300) {
            $transcodingDelaySeconds = 90;
        }

        $video = Video::create([
            'admin_id' => $request->user()->id,
            'category_id' => $validated['category_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'transcript' => null, // Will be populated automatically by TranscriptionService below
            'dyntube_video_id' => $videoId,
            'dyntube_iframe_link' => $dyntubeInfo['iframe_link'] ?? $videoId,
            'dyntube_thumbnail' => $dyntubeInfo['thumbnail'] ?? null,
            'duration_seconds' => $durationSeconds,
            'is_published' => $validated['is_published'] ?? false,
            'quiz_status' => ! empty($validated['generate_quiz']) ? 'proses' : 'belum',
            'transcoding_status' => 'transcoding',
            'transcoding_ready_at' => now()->addSeconds($transcodingDelaySeconds),
            'view_count' => 0,
        ]);

        // Dispatch async jobs for transcript generation and quiz generation.
        // These run in the background via queue worker so the upload
        // completes quickly without blocking the user.
        // When quiz generation is requested, chain the jobs so the transcript
        // is available before Gemini tries to generate questions from it.
        if (! empty($validated['generate_quiz'])) {
            Bus::chain([
                new GenerateTranscriptJob($video),
                new GenerateQuizJob($video),
            ])->dispatch();
        } else {
            GenerateTranscriptJob::dispatch($video);
        }

        return redirect()->back()->with('success', 'Video berhasil diunggah! Transcript dan kuis AI sedang diproses di latar belakang. Video dalam proses transcoding (~'.round($transcodingDelaySeconds / 60, 1).' menit).');
    }

    /**
     * Polling endpoint to check & update transcoding status for all pending videos.
     */
    public function transcodingStatus(): JsonResponse
    {
        $transcodingVideos = Video::where('transcoding_status', 'transcoding')->get();

        $updatedCount = 0;
        foreach ($transcodingVideos as $video) {
            if ($video->transcoding_ready_at && now()->gte($video->transcoding_ready_at)) {
                $dyntubeService = app(DyntubeService::class);
                $details = $dyntubeService->getVideoDetails($video->dyntube_video_id);

                $updateData = ['transcoding_status' => 'ready'];
                if (! empty($details['thumbnail'])) {
                    $updateData['dyntube_thumbnail'] = $details['thumbnail'];
                }
                if (! empty($details['duration_seconds']) && $details['duration_seconds'] > 0) {
                    $updateData['duration_seconds'] = $details['duration_seconds'];
                }
                if (! empty($details['iframe_link'])) {
                    $updateData['dyntube_iframe_link'] = $details['iframe_link'];
                }

                $video->update($updateData);
                $updatedCount++;
            }
        }

        $anyTranscoding = Video::where('transcoding_status', 'transcoding')->exists();

        return response()->json([
            'any_transcoding' => $anyTranscoding,
            'updated_count' => $updatedCount,
        ]);
    }

    /**
     * Display detailed view for a single video.
     */
    public function show(Video $video): Response
    {
        $video->load(['category', 'admin', 'objectives', 'questions']);

        return Inertia::render('Admin/Videos/Show', [
            'video' => [
                'id' => $video->id,
                'title' => $video->title,
                'description' => $video->description,
                'transcript' => $video->transcript,
                'dyntube_video_id' => $video->dyntube_video_id,
                'dyntube_iframe_link' => $video->dyntube_iframe_link,
                'dyntube_thumbnail' => $video->dyntube_thumbnail,
                'category_name' => $video->category?->name ?? 'Uncategorized',
                'view_count' => $video->view_count,
                'quiz_status' => $video->quiz_status,
                'is_published' => $video->is_published,
                'transcoding_status' => $video->transcoding_status,
                'duration_formatted' => $video->duration_formatted,
                'created_at' => $video->created_at->setTimezone('Asia/Jakarta')->format('d M Y, H:i'),
                'objectives' => $video->objectives,
                'questions_count' => $video->questions->count(),
            ],
        ]);
    }

    /**
     * Update video metadata.
     */
    public function update(Request $request, Video $video, DyntubeService $dyntubeService): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'dyntube_video_id' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string',
            'transcript' => 'nullable|string',
            'is_published' => 'nullable|boolean',
        ]);

        if ($video->dyntube_video_id !== trim($validated['dyntube_video_id'])) {
            $dyntubeInfo = $dyntubeService->getVideoDetails($validated['dyntube_video_id']);
            $video->dyntube_iframe_link = $dyntubeInfo['iframe_link'];
            $video->dyntube_thumbnail = $dyntubeInfo['thumbnail'];
            $video->duration_seconds = $dyntubeInfo['duration_seconds'];
        }

        $video->update([
            'category_id' => $validated['category_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'transcript' => $validated['transcript'] ?? null,
            'dyntube_video_id' => trim($validated['dyntube_video_id']),
            'is_published' => $validated['is_published'] ?? $video->is_published,
        ]);

        return redirect()->back()->with('success', 'Video berhasil diperbarui!');
    }

    /**
     * Toggle publish status (Tayang / Draft).
     */
    public function togglePublish(Video $video): RedirectResponse
    {
        $video->update([
            'is_published' => ! $video->is_published,
        ]);

        return redirect()->back()->with('success', 'Status publikasi berhasil diubah!');
    }

    /**
     * Delete video.
     */
    public function destroy(Video $video): RedirectResponse
    {
        $video->delete();

        return redirect()->route('admin.videos.index')->with('success', 'Video berhasil dihapus!');
    }

    /**
     * Trigger Quiz Generation on demand for a video.
     *
     * If the transcript already exists, only the quiz is regenerated.
     * If it does not (e.g. a previous transcription failed), we first
     * (re)generate the transcript, then chain the quiz — otherwise the
     * quiz job would immediately fail its "no transcript" guard.
     */
    public function generateQuiz(Video $video): RedirectResponse
    {
        $video->update(['quiz_status' => 'proses']);

        if (empty(trim($video->transcript ?? ''))) {
            Bus::chain([
                new GenerateTranscriptJob($video),
                new GenerateQuizJob($video),
            ])->dispatch();

            return redirect()->back()->with('success', 'Naskah video belum tersedia — sistem akan mengambil naskah dulu lalu membuat kuis. Proses ini berjalan di latar belakang, silakan cek kembali beberapa saat lagi.');
        }

        GenerateQuizJob::dispatch($video);

        return redirect()->back()->with('success', 'Kuis AI sedang diproses di latar belakang! Silakan cek kembali dalam beberapa saat.');
    }
}
