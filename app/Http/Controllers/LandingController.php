<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function __invoke(): Response
    {
        $videos = Video::with('category')
            ->where('is_published', true)
            ->latest()
            ->take(8)
            ->get()
            ->map(fn(Video $v) => [
                'id'        => $v->id,
                'title'     => $v->title,
                'category'  => $v->category?->name ?? '-',
                'duration'  => $v->duration_seconds
                    ? intdiv($v->duration_seconds, 60) . ' Menit'
                    : '-',
                'views'     => number_format($v->view_count),
                'thumbnail' => $v->dyntube_thumbnail,
                'isNew'     => $v->created_at?->diffInDays(now()) <= 7,
            ]);

        return Inertia::render('Welcome', [
            'latestVideos' => $videos,
        ]);
    }
}
