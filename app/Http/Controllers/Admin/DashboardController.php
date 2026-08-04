<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Models\Video;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        // ── Stat Cards ────────────────────────────────────────────────────────
        $stats = [
            'total_videos'    => Video::count(),
            'total_views'     => Video::sum('view_count'),
            'quiz_active'     => Video::where('quiz_status', 'aktif')->count(),
            'published'       => Video::where('is_published', true)->count(),
            'total_users'     => User::where('role', 'user')->count(),
        ];

        // ── Latest Videos (for dashboard table) ──────────────────────────────
        $latestVideos = Video::query()
            ->with('category')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn(Video $v) => [
                'id'              => $v->id,
                'title'           => $v->title,
                'dyntube_video_id' => $v->dyntube_video_id,
                'category'        => $v->category->name,
                'view_count'      => $v->view_count,
                'quiz_status'     => $v->quiz_status,
                'is_published'    => $v->is_published,
                'created_at'      => $v->created_at->format('d M Y'),
            ]);

        // ── Latest Users ──────────────────────────────────────────────────────
        $latestUsers = User::query()
            ->where('role', 'user')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'name', 'email', 'created_at']);

        return Inertia::render('Admin/Dashboard', [
            'stats'        => $stats,
            'latestVideos' => $latestVideos,
            'latestUsers'  => $latestUsers,
        ]);
    }
}
