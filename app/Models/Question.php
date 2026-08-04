<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    protected $fillable = [
        'video_id',
        'question_text',
        'options',
        'correct_answer',
        'order_number',
        'video_timestamp_seconds', // nullable: approximate second-mark in video
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array', // {"A": "...", "B": "...", "C": "...", "D": "..."}
        ];
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAttemptAnswer::class);
    }
}
