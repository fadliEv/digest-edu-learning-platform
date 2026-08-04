<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\VideoResume;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class PortalProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        // ── Stats ────────────────────────────────────────────────────────────
        $totalWatched = $user->watches()->distinct('video_id')->count('video_id');
        $totalQuizzes = $user->quizAttempts()->count();
        $avgScore     = $totalQuizzes > 0
            ? intval($user->quizAttempts()->avg('score'))
            : 0;

        // ── Quiz history (latest 10) ──────────────────────────────────────────
        $quizHistory = $user->quizAttempts()
            ->with('video:id,title,dyntube_thumbnail,category_id')
            ->with('video.category:id,name')
            ->orderByDesc('attempted_at')
            ->limit(10)
            ->get()
            ->map(fn ($attempt) => [
                'id'           => $attempt->id,
                'video_id'     => $attempt->video_id,
                'video_title'  => $attempt->video->title ?? '—',
                'category'     => $attempt->video->category->name ?? '—',
                'score'        => $attempt->score,
                'passed'       => $attempt->score >= 70,
                'attempted_at' => $attempt->attempted_at?->format('d M Y H:i'),
            ]);

        // ── Videos in progress ───────────────────────────────────────────────
        $inProgress = VideoResume::with('video:id,title,dyntube_thumbnail,duration_seconds,category_id')
            ->with('video.category:id,name')
            ->where('user_id', $user->id)
            ->where('last_position', '>', 0)
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get()
            ->map(function ($resume) {
                $video    = $resume->video;
                $progress = ($video && $video->duration_seconds > 0)
                    ? min(100, intval(($resume->last_position / $video->duration_seconds) * 100))
                    : 0;

                return [
                    'video_id'      => $resume->video_id,
                    'title'         => $video->title ?? '—',
                    'category'      => $video->category->name ?? '—',
                    'thumbnail'     => $video->dyntube_thumbnail,
                    'last_position' => $resume->last_position,
                    'progress'      => $progress,
                    'duration'      => $video?->duration_formatted ?? '—',
                ];
            });

        return Inertia::render('Portal/Profile', [
            'userProfile' => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'role'       => $user->role,
                'joined_at'  => $user->created_at?->format('d M Y'),
            ],
            'stats' => [
                'total_watched' => $totalWatched,
                'total_quizzes' => $totalQuizzes,
                'avg_score'     => $avgScore,
            ],
            'quizHistory' => $quizHistory,
            'inProgress'  => $inProgress,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|email|max:255|unique:users,email,' . $user->id,
            'current_password'      => 'nullable|string',
            'new_password'          => 'nullable|string|min:8|confirmed',
        ]);

        // Check current password if changing password
        if (! empty($validated['new_password'])) {
            if (empty($validated['current_password']) || ! Hash::check($validated['current_password'], $user->password)) {
                return back()->withErrors(['current_password' => 'Password saat ini tidak sesuai.']);
            }
        }

        $updateData = [
            'name'  => $validated['name'],
            'email' => $validated['email'],
        ];

        if (! empty($validated['new_password'])) {
            $updateData['password'] = Hash::make($validated['new_password']);
        }

        $user->update($updateData);

        return back()->with('success', 'Profil berhasil diperbarui!');
    }
}
