<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Models\VideoWatch;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VideoWatchController extends Controller
{
    public function show(Request $request, Video $video): Response
    {
        // Only show published videos
        abort_if(! $video->is_published, 404);

        $user = $request->user();

        // ── Increment view count (once per session) ──────────────────────────
        $sessionKey = "viewed_video_{$video->id}";
        if (! session()->has($sessionKey)) {
            $video->increment('view_count');
            session()->put($sessionKey, true);
        }

        // ── Resume position ──────────────────────────────────────────────────
        $resume = $video->resumes()->where('user_id', $user->id)->first();
        if (! $resume) {
            $resume = $video->resumes()->create([
                'user_id'       => $user->id,
                'last_position' => 15,
            ]);
        } elseif ($resume->last_position == 0) {
            $resume->update(['last_position' => 15]);
        }

        $progress = ($video->duration_seconds && $video->duration_seconds > 0)
            ? min(100, intval(($resume->last_position / $video->duration_seconds) * 100))
            : 10;

        // ── Quiz questions ───────────────────────────────────────────────────
        $questions = [];
        if ($video->quiz_status === 'aktif') {
            $questions = $video->questions()
                ->get()
                ->map(fn ($q) => [
                    'id'             => $q->id,
                    'question_text'  => $q->question_text,
                    'options'        => $q->options,
                    'correct_answer' => $q->correct_answer,
                    'order_number'   => $q->order_number,
                ])
                ->values();
        }

        // ── Best quiz score for this user ────────────────────────────────────
        $bestAttempt = $user->quizAttempts()
            ->where('video_id', $video->id)
            ->orderByDesc('score')
            ->first();

        // ── Related videos (same category, excluding current) ────────────────
        $relatedVideos = Video::with('category')
            ->where('category_id', $video->category_id)
            ->where('id', '!=', $video->id)
            ->where('is_published', true)
            ->orderByDesc('view_count')
            ->limit(4)
            ->get()
            ->map(fn (Video $v) => [
                'id'        => $v->id,
                'title'     => $v->title,
                'thumbnail' => $v->dyntube_thumbnail,
                'category'  => $v->category->name,
                'duration'  => $v->duration_formatted,
                'views'     => number_format($v->view_count, 0, ',', '.'),
            ]);

        // ── Video objectives ─────────────────────────────────────────────────
        $objectives = $video->objectives()->get()->map(fn ($obj) => [
            'id'           => $obj->id,
            'objective'    => $obj->objective,
            'order_number' => $obj->order_number,
        ]);

        $video->load('category');

        return Inertia::render('Portal/Watch', [
            'video' => [
                'id'                  => $video->id,
                'title'               => $video->title,
                'description'         => $video->description,
                'category'            => $video->category->name,
                'category_id'         => $video->category_id,
                'dyntube_video_id'    => $video->dyntube_video_id,
                'dyntube_iframe_link' => $video->dyntube_iframe_link,
                'dyntube_thumbnail'   => $video->dyntube_thumbnail,
                'duration_seconds'    => $video->duration_seconds,
                'duration'            => $video->duration_formatted,
                'view_count'          => number_format($video->view_count, 0, ',', '.'),
                'quiz_status'         => $video->quiz_status,
                'created_at'          => $video->created_at?->format('d M Y'),
            ],
            'questions'     => $questions,
            'objectives'    => $objectives,
            'resume'        => [
                'last_position' => $resume?->last_position ?? 0,
                'progress'      => $progress,
            ],
            'bestScore'     => $bestAttempt?->score,
            'relatedVideos' => $relatedVideos,
        ]);
    }
}
