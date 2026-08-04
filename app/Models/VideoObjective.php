<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoObjective extends Model
{
    protected $fillable = ['video_id', 'objective_text', 'order_number'];

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
}
