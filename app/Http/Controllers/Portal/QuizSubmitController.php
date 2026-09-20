<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\Video;
use App\Models\Question;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuizSubmitController extends Controller
{
    /**
     * Store a quiz attempt and answers, then redirect back with score.
     */
    public function store(Request $request, Video $video): RedirectResponse
    {
        $user = $request->user();

        // ── Watch gate: quiz may only be submitted after watching the video ──
        // Mirrors the frontend lock (90% of duration). Prevents bypassing the
        // UI by POSTing directly to this endpoint.
        if ($video->duration_seconds && $video->duration_seconds > 0) {
            $resume = $video->resumes()->where('user_id', $user->id)->first();
            $watched = $resume?->last_position ?? 0;
            if ($watched < $video->duration_seconds * 0.9) {
                return redirect()
                    ->route('portal.video.watch', $video->id)
                    ->with('error', 'Kamu harus menonton video ini hingga selesai sebelum mengerjakan kuis.');
            }
        }

        $validated = $request->validate([
            'answers'              => 'required|array',
            'answers.*.question_id' => 'required|integer|exists:questions,id',
            'answers.*.chosen'      => 'required|string|in:A,B,C,D',
        ]);

        // Load correct answers for all submitted questions
        $questionIds = collect($validated['answers'])->pluck('question_id');
        $questions   = Question::whereIn('id', $questionIds)
            ->where('video_id', $video->id)
            ->get()
            ->keyBy('id');

        $correctCount = 0;
        $totalCount   = $questions->count();

        // Create the attempt record
        $attempt = QuizAttempt::create([
            'user_id'      => $user->id,
            'video_id'     => $video->id,
            'score'        => 0,
            'attempted_at' => now(),
        ]);

        // Record each answer
        foreach ($validated['answers'] as $ans) {
            $question  = $questions->get($ans['question_id']);
            $isCorrect = $question && strtoupper($ans['chosen']) === strtoupper($question->correct_answer);

            if ($isCorrect) {
                $correctCount++;
            }

            QuizAttemptAnswer::create([
                'quiz_attempt_id' => $attempt->id,
                'question_id'     => $ans['question_id'],
                'selected_answer' => strtoupper($ans['chosen']),
                'is_correct'      => $isCorrect,
            ]);
        }

        // Score = percentage (0–100)
        $score = $totalCount > 0 ? intval(($correctCount / $totalCount) * 100) : 0;
        $attempt->update(['score' => $score]);

        return redirect()
            ->route('portal.video.watch', $video->id)
            ->with('quizResult', [
                'score'   => $score,
                'correct' => $correctCount,
                'total'   => $totalCount,
            ]);
    }
}
