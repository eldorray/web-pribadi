<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialLink extends Model
{
    protected $fillable = ['platform', 'url', 'icon', 'sort_order'];

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
