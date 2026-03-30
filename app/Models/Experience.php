<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Experience extends Model
{
    protected $fillable = [
        'title', 'company', 'description', 'icon', 'color', 'start_year', 'end_year', 'sort_order',
    ];

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderByDesc('start_year');
    }

    public function getYearRangeAttribute()
    {
        return $this->start_year . ' — ' . $this->end_year;
    }
}
