<?php

namespace Pterodactyl\Kazama\Support;

/** Konfigurasi login OAuth (Google / GitHub): kredensial dari Admin > Settings, fallback ke .env. */
final class OAuth
{
    public const PROVIDERS = ['google', 'github'];

    public static function credentials(string $provider): array
    {
        $id = Settings::get("oauth_{$provider}_id") ?: config("kazama.{$provider}.client_id");
        $secret = Settings::secret("oauth_{$provider}_secret") ?: config("kazama.{$provider}.client_secret");

        return [(string) $id, (string) $secret];
    }

    public static function configured(string $provider): bool
    {
        [$id, $secret] = self::credentials($provider);

        return $id !== '' && $secret !== '';
    }

    /** Aktif = diaktifkan admin (default aktif) DAN kredensial terisi. */
    public static function enabled(string $provider): bool
    {
        return Settings::bool("oauth_{$provider}_enabled", true) && self::configured($provider);
    }

    public static function redirectUri(string $provider): string
    {
        return config("kazama.{$provider}.redirect") ?: rtrim(config('app.url'), '/') . "/auth/{$provider}/callback";
    }

    public static function registrationOpen(): bool
    {
        return Settings::bool('registration_enabled', (bool) config('kazama.registration', true));
    }
}
