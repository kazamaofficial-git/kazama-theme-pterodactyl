<?php

namespace Pterodactyl\Kazama\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Siapa yang sedang melakukan aksi. Dipakai untuk keterangan suspend/hapus dan Logs.
 * Urutan: override manual (command/system) → permintaan MCP loopback → user login → Sistem.
 */
final class Actor
{
    private static ?array $override = null;
    private static ?string $reasonOverride = null;
    private static array $cache = [];

    public static function as(string $name, string $source = 'system', ?string $reason = null, ?string $client = null, ?int $conn = null, ?int $id = null): void
    {
        self::$override = ['id' => $id, 'name' => $name, 'source' => $source, 'client' => $client, 'conn' => $conn];
        self::$reasonOverride = $reason;
    }

    public static function clear(): void
    {
        self::$override = null;
        self::$reasonOverride = null;
    }

    public static function mcpSignature(int $connectionId, int $userId): string
    {
        return $connectionId . '.' . hash_hmac('sha256', "mcp:{$connectionId}:{$userId}", (string) config('app.key'));
    }

    /** @return array{id:?int,name:string,source:string,client:?string,conn:?int} */
    public static function resolve(): array
    {
        if (self::$override) {
            return self::$override;
        }
        if (app()->runningInConsole() && !app()->runningUnitTests()) {
            return ['id' => null, 'name' => 'Sistem', 'source' => 'system', 'client' => null, 'conn' => null];
        }

        $request = request();
        $user = Auth::user();
        if (!$user) {
            return ['id' => null, 'name' => 'Sistem', 'source' => 'system', 'client' => null, 'conn' => null];
        }

        $sig = (string) $request->header('X-Kazama-Mcp', '');
        if ($sig !== '' && str_contains($sig, '.')) {
            [$cid] = explode('.', $sig, 2);
            if ((int) $cid > 0 && hash_equals(self::mcpSignature((int) $cid, (int) $user->id), $sig)) {
                $conn = self::$cache[$cid] ??= DB::table('kazama_mcp_connections')->where('id', (int) $cid)->first(['id', 'platform', 'name']);
                if ($conn) {
                    $label = \Pterodactyl\Kazama\Mcp\Platforms::label($conn->platform, $conn->name) . '-MCP';

                    return ['id' => (int) $user->id, 'name' => $user->username . ' via ' . $label, 'source' => 'mcp', 'client' => $label, 'conn' => (int) $conn->id];
                }
            }
        }

        return ['id' => (int) $user->id, 'name' => $user->username, 'source' => $user->root_admin ? 'admin' : 'user', 'client' => null, 'conn' => null];
    }

    public static function name(): string
    {
        return self::resolve()['name'];
    }

    /** Keterangan dari form admin (kazama_reason) / header X-Kazama-Reason. */
    public static function reason(string $default = 'Tanpa keterangan'): string
    {
        if (self::$reasonOverride) {
            return self::$reasonOverride;
        }
        if (app()->runningInConsole() && !app()->runningUnitTests()) {
            return $default;
        }
        $r = trim((string) (request()->input('kazama_reason') ?: request()->header('X-Kazama-Reason', '')));

        return $r !== '' ? mb_substr($r, 0, 480) : $default;
    }
}
