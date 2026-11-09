<?php

namespace Pterodactyl\Kazama\Services;

use Illuminate\Support\Facades\DB;

final class Notifier
{
    /** $userId = null → broadcast ke semua user. $dedupe mencegah notifikasi ganda. */
    public static function send(?int $userId, string $title, string $message, string $type = 'info', ?int $serverId = null, ?string $dedupe = null): void
    {
        if ($dedupe && DB::table('kazama_notifications')->where('dedupe', $dedupe)->exists()) {
            return;
        }

        DB::table('kazama_notifications')->insert([
            'user_id' => $userId,
            'server_id' => $serverId,
            'type' => in_array($type, ['info', 'success', 'warning', 'danger'], true) ? $type : 'info',
            'title' => mb_substr($title, 0, 250),
            'message' => $message,
            'dedupe' => $dedupe,
            'created_at' => now('UTC')->format('Y-m-d H:i:s'),
        ]);
    }
}
