<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    protected $fillable = [
        'title', 'description', 'icon', 'color', 'is_wide', 'sort_order',
    ];

    protected $casts = [
        'is_wide' => 'boolean',
    ];

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
