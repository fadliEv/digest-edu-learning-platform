<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int    $id
 * @property string $name
 * @property string $slug
 * @property string $description
 */
class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description'];

    // ─── Relationships ───────────────────────────────────────────────────────

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }
}
