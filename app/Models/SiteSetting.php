<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'group'];

    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    /**
     * $type/$group are only applied when given, so saving a value never
     * clobbers the group/type an existing setting already has.
     */
    public static function set(string $key, $value, ?string $type = null, ?string $group = null)
    {
        $setting = static::firstOrNew(['key' => $key]);
        $setting->value = $value;
        $setting->type = $type ?? $setting->type ?? 'text';
        $setting->group = $group ?? $setting->group ?? 'general';
        $setting->save();

        return $setting;
    }
}
