<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Models\VideoResume;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideoResumeController extends Controller
{
    /**
     * Upsert the user's resume position for a video.
     * Called via fetch() from the frontend every few seconds while watching.
     */
    public function store(Request $request, Video $video): JsonResponse
    {
        $validated = $request->validate([
            'position' => 'required|numeric|min:0',
        ]);

        VideoResume::updateOrCreate(
            [
                'user_id'  => $request->user()->id,
                'video_id' => $video->id,
            ],
            [
                'last_position' => (int) $validated['position'],
            ]
        );

        return response()->json(['ok' => true]);
    }
}
