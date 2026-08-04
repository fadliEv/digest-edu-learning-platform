<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int         $id
 * @property int         $admin_id
 * @property int         $category_id
 * @property string      $title
 * @property string|null $description
 * @property string      $dyntube_video_id
 * @property string|null $dyntube_iframe_link
 * @property string|null $dyntube_thumbnail
 * @property int|null    $duration_seconds
 * @property bool        $is_published
 * @property string      $quiz_status         'belum'|'proses'|'aktif'|'gagal'
 * @property int         $view_count
 */
class Video extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'category_id',
        'title',
        'description',
        'transcript',
        'dyntube_video_id',
        'dyntube_iframe_link',
        'dyntube_thumbnail',
        'duration_seconds',
        'is_published',
        'quiz_status',
        'transcoding_status',
        'transcoding_ready_at',
        'view_count',
    ];

    protected function casts(): array
    {
        return [
            'is_published'         => 'boolean',
            'duration_seconds'     => 'integer',
            'view_count'           => 'integer',
            'transcoding_ready_at' => 'datetime',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function objectives(): HasMany
    {
        return $this->hasMany(VideoObjective::class)->orderBy('order_number');
    }

    public function resumes(): HasMany
    {
        return $this->hasMany(VideoResume::class);
    }

    public function watches(): HasMany
    {
        return $this->hasMany(VideoWatch::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('order_number');
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    /** Duration formatted as "MM:SS" */
    public function getDurationFormattedAttribute(): string
    {
        if (! $this->duration_seconds) {
            return '—';
        }

        $m = intdiv($this->duration_seconds, 60);
        $s = $this->duration_seconds % 60;

        return sprintf('%d:%02d', $m, $s);
    }
}
