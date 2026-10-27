<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function getValue($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public static function enabled($key, bool $default = false): bool
    {
        return filter_var(self::getValue($key, $default), FILTER_VALIDATE_BOOLEAN);
    }

    public static function setValue($key, $value)
    {
        $setting = self::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
        return $setting;
    }

}
