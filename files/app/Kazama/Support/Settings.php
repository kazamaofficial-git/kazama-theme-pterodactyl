<?php

namespace Pterodactyl\Kazama\Support;

use Illuminate\Support\Facades\DB;

final class Settings
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            try {
                self::$cache = DB::table('kazama_settings')->pluck('value', 'key')->all();
            } catch (\Throwable) {
                self::$cache = [];
            }
        }

        return self::$cache;
    }

    public static function get(string $key, $default = null)
    {
        $all = self::all();

        return isset($all[$key]) && $all[$key] !== '' ? $all[$key] : $default;
    }

    public static function int(string $key, int $default): int
    {
        return (int) self::get($key, $default);
    }

    public static function bool(string $key, bool $default = false): bool
    {
        return filter_var(self::get($key, $default ? '1' : '0'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function set(string $key, $value): void
    {
        DB::table('kazama_settings')->updateOrInsert(['key' => $key], ['value' => (string) $value]);
        self::$cache = null;
    }
}
