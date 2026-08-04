<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Video;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        // ── Videos the user has started but not fully watched ─────────────────
        $continueVideos = Video::query()
            ->join('video_resumes', function ($join) use ($user) {
                $join->on('videos.id', '=', 'video_resumes.video_id')
                     ->where('video_resumes.user_id', '=', $user->id)
                     ->where('video_resumes.last_position', '>', 0);
            })
            ->with(['category'])
            ->where('videos.is_published', true)
            ->orderByDesc('video_resumes.updated_at')
            ->select('videos.*', 'video_resumes.last_position')
            ->limit(4)
            ->get()
            ->map(function (Video $video) {
                $progress = ($video->duration_seconds && $video->duration_seconds > 0)
                    ? min(99, max(5, intval(($video->last_position / $video->duration_seconds) * 100)))
                    : 15;

                return [
                    'id'               => $video->id,
                    'title'            => $video->title,
                    'thumbnail'        => $video->dyntube_thumbnail,
                    'category'         => $video->category->name,
                    'duration_seconds' => $video->duration_seconds,
                    'last_position'    => $video->last_position,
                    'progress'         => $progress,
                ];
            });

        // ── Latest published videos ──────────────────────────────────────────
        $latestVideos = Video::query()
            ->with('category')
            ->where('is_published', true)
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn(Video $video) => [
                'id'        => $video->id,
                'title'     => $video->title,
                'thumbnail' => $video->dyntube_thumbnail,
                'category'  => $video->category->name,
                'category_slug' => $video->category->slug,
                'views'     => number_format($video->view_count, 0, ',', '.'),
                'duration'  => $video->duration_formatted,
                'is_new'    => $video->created_at->diffInDays() <= 7,
            ]);

        // ── Categories for filter ────────────────────────────────────────────
        $categories = Category::orderBy('name')->pluck('name')->prepend('Semua');

        return Inertia::render('Portal/Home', [
            'continueVideos' => $continueVideos,
            'latestVideos'   => $latestVideos,
            'categories'     => $categories,
        ]);
    }
}
