<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AiModel extends Model
{
    protected $fillable = [
        'model_id',
        'label',
        'is_enabled',
        'is_default',
        'supports_effort',
        'supports_vision',
        'is_image',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'is_default' => 'boolean',
            'supports_effort' => 'boolean',
            'supports_vision' => 'boolean',
            'is_image' => 'boolean',
        ];
    }

    /** @param  Builder<AiModel>  $query */
    public function scopeChat(Builder $query): void
    {
        $query->where('is_enabled', true)->where('is_image', false);
    }

    /** @param  Builder<AiModel>  $query */
    public function scopeImage(Builder $query): void
    {
        $query->where('is_enabled', true)->where('is_image', true);
    }

    public static function defaultChat(): ?self
    {
        return static::chat()->orderByDesc('is_default')->orderBy('id')->first();
    }

    public static function defaultImage(): ?self
    {
        return static::image()->orderByDesc('is_default')->orderBy('id')->first();
    }
}
