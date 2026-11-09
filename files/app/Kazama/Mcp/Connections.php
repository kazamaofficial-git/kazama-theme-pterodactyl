<?php

namespace Pterodactyl\Kazama\Mcp;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pterodactyl\Kazama\Support\Settings;
use Pterodactyl\Models\ApiKey;
use Pterodactyl\Services\Api\KeyCreationService;

final class Connections
{
    public const LIMITS = [0, 20, 50, 100, 200];
    public const DISCONNECT = [0, 7, 30, 90];

    public static function defaultName(): string
    {
        return (string) Settings::get('mcp_default_name', config('kazama.mcp.default_name', 'Kazama Cloud (MCP)'));
    }

    public static function baseDefaults(): array
    {
        return ['access' => 'full', 'servers_mode' => 'all', 'server_ids' => [], 'daily_limit' => 0, 'auto_disconnect_days' => 0, 'auto_backup' => true];
    }

    public static function defaults(int $userId): array
    {
        $row = DB::table('kazama_mcp_prefs')->where('user_id', $userId)->value('settings');
        $saved = $row ? (json_decode($row, true) ?: []) : [];

        return self::sanitize($saved, self::baseDefaults());
    }

    public static function saveDefaults(int $userId, array $in): array
    {
        $s = self::sanitize($in, self::defaults($userId));
        DB::table('kazama_mcp_prefs')->updateOrInsert(['user_id' => $userId], ['settings' => json_encode($s)]);

        return $s;
    }

    /** Validasi + normalisasi pengaturan (nilai di luar pilihan diabaikan). */
    public static function sanitize(array $in, array $base): array
    {
        $s = $base;
        if (isset($in['access']) && in_array($in['access'], ['full', 'read'], true)) {
            $s['access'] = $in['access'];
        }
        if (isset($in['servers_mode']) && in_array($in['servers_mode'], ['all', 'selected'], true)) {
            $s['servers_mode'] = $in['servers_mode'];
        }
        if (isset($in['server_ids']) && is_array($in['server_ids'])) {
            $s['server_ids'] = array_values(array_unique(array_map('intval', $in['server_ids'])));
        }
        if (isset($in['daily_limit']) && in_array((int) $in['daily_limit'], self::LIMITS, true)) {
            $s['daily_limit'] = (int) $in['daily_limit'];
        }
        if (isset($in['auto_disconnect_days']) && in_array((int) $in['auto_disconnect_days'], self::DISCONNECT, true)) {
            $s['auto_disconnect_days'] = (int) $in['auto_disconnect_days'];
        }
        if (array_key_exists('auto_backup', $in)) {
            $s['auto_backup'] = filter_var($in['auto_backup'], FILTER_VALIDATE_BOOLEAN);
        }

        return $s;
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function newToken(): string
    {
        return 'kzm_' . Str::random(48);
    }

    private static function createKeys($user, string $name): array
    {
        /** @var KeyCreationService $svc */
        $svc = app(KeyCreationService::class);
        $client = $svc->setKeyType(ApiKey::TYPE_ACCOUNT)->handle(['user_id' => $user->id, 'memo' => mb_substr('Kazama MCP: ' . $name, 0, 250)]);
        $app = null;
        if ($user->root_admin) {
            $app = self::createAppKey($user, $name);
        }

        return [$client->id, $app];
    }

    public static function createAppKey($user, string $name): int
    {
        $key = app(KeyCreationService::class)->setKeyType(ApiKey::TYPE_APPLICATION)->handle(
            ['user_id' => $user->id, 'memo' => mb_substr('Kazama MCP (admin): ' . $name, 0, 250)],
            [
                'r_servers' => 3, 'r_users' => 3, 'r_nodes' => 1, 'r_allocations' => 1, 'r_eggs' => 1, 'r_nests' => 1,
                'r_locations' => 1, 'r_database_hosts' => 0, 'r_server_databases' => 0,
            ]
        );

        return $key->id;
    }

    /** @return array{0:object,1:?string} [koneksi, token plaintext (hanya untuk tipe manual)] */
    public static function create($user, string $name, string $platform, string $authType, array $settings): array
    {
        $s = self::sanitize($settings, self::defaults($user->id));
        [$clientKeyId, $appKeyId] = self::createKeys($user, $name);
        $plain = $authType === 'manual' ? self::newToken() : null;
        $now = Carbon::now('UTC');

        $id = DB::table('kazama_mcp_connections')->insertGetId([
            'user_id' => $user->id, 'name' => mb_substr($name, 0, 120), 'platform' => Platforms::valid($platform) ? $platform : 'other',
            'auth_type' => $authType, 'token_hash' => $plain ? self::hash($plain) : null,
            'api_key_id' => $clientKeyId, 'app_key_id' => $appKeyId,
            'access' => $s['access'], 'servers_mode' => $s['servers_mode'], 'server_ids' => json_encode($s['server_ids']),
            'daily_limit' => $s['daily_limit'], 'auto_disconnect_days' => $s['auto_disconnect_days'], 'auto_backup' => $s['auto_backup'],
            'expires_at' => $s['auto_disconnect_days'] > 0 ? $now->copy()->addDays($s['auto_disconnect_days'])->format('Y-m-d H:i:s') : null,
            'created_at' => $now->format('Y-m-d H:i:s'),
        ]);

        return [DB::table('kazama_mcp_connections')->find($id), $plain];
    }

    public static function update(int $id, array $in): void
    {
        $row = DB::table('kazama_mcp_connections')->find($id);
        $cur = ['access' => $row->access, 'servers_mode' => $row->servers_mode, 'server_ids' => json_decode((string) $row->server_ids, true) ?: [],
            'daily_limit' => (int) $row->daily_limit, 'auto_disconnect_days' => (int) $row->auto_disconnect_days, 'auto_backup' => (bool) $row->auto_backup];
        $s = self::sanitize($in, $cur);
        $update = [
            'access' => $s['access'], 'servers_mode' => $s['servers_mode'], 'server_ids' => json_encode($s['server_ids']),
            'daily_limit' => $s['daily_limit'], 'auto_disconnect_days' => $s['auto_disconnect_days'], 'auto_backup' => $s['auto_backup'],
            'expires_at' => $s['auto_disconnect_days'] > 0 ? Carbon::now('UTC')->addDays($s['auto_disconnect_days'])->format('Y-m-d H:i:s') : null,
        ];
        if (!empty($in['name'])) {
            $update['name'] = mb_substr((string) $in['name'], 0, 120);
        }
        if (!empty($in['platform']) && Platforms::valid($in['platform'])) {
            $update['platform'] = $in['platform'];
        }
        DB::table('kazama_mcp_connections')->where('id', $id)->update($update);
    }

    public static function revoke(int $id): void
    {
        $row = DB::table('kazama_mcp_connections')->find($id);
        if (!$row) {
            return;
        }
        ApiKey::query()->whereIn('id', array_filter([$row->api_key_id, $row->app_key_id]))->delete();
        DB::table('kazama_mcp_connections')->where('id', $id)->update([
            'revoked_at' => Carbon::now('UTC')->format('Y-m-d H:i:s'), 'token_hash' => null, 'refresh_hash' => null, 'api_key_id' => null, 'app_key_id' => null,
        ]);
        DB::table('kazama_mcp_codes')->where('connection_id', $id)->delete();
    }

    /** Terbitkan access + refresh token OAuth (access 1 jam, refresh berotasi). */
    public static function issueOAuthTokens(int $connectionId): array
    {
        $access = self::newToken();
        $refresh = 'kzr_' . Str::random(48);
        DB::table('kazama_mcp_connections')->where('id', $connectionId)->update([
            'token_hash' => self::hash($access), 'refresh_hash' => self::hash($refresh),
            'access_expires_at' => Carbon::now('UTC')->addHour()->format('Y-m-d H:i:s'),
        ]);

        return ['access_token' => $access, 'token_type' => 'Bearer', 'expires_in' => 3600, 'refresh_token' => $refresh, 'scope' => 'mcp'];
    }

    /** Cari koneksi aktif dari bearer token; tolak bila dicabut / kedaluwarsa. */
    public static function authenticate(string $token): ?object
    {
        $row = DB::table('kazama_mcp_connections')->where('token_hash', self::hash($token))->whereNull('revoked_at')->first();
        if (!$row) {
            return null;
        }
        $now = Carbon::now('UTC');
        if ($row->access_expires_at && Carbon::parse($row->access_expires_at, 'UTC')->lt($now)) {
            return null;
        }
        if ($row->expires_at && Carbon::parse($row->expires_at, 'UTC')->lt($now)) {
            self::revoke((int) $row->id);

            return null;
        }

        $update = ['last_used_at' => $now->format('Y-m-d H:i:s')];
        if ($row->auto_disconnect_days > 0) {
            $update['expires_at'] = $now->copy()->addDays($row->auto_disconnect_days)->format('Y-m-d H:i:s');
        }
        DB::table('kazama_mcp_connections')->where('id', $row->id)->update($update);

        return $row;
    }

    public static function pruneExpired(): int
    {
        $n = 0;
        $rows = DB::table('kazama_mcp_connections')->whereNull('revoked_at')->whereNotNull('expires_at')
            ->where('expires_at', '<', Carbon::now('UTC')->format('Y-m-d H:i:s'))->pluck('id');
        foreach ($rows as $id) {
            self::revoke((int) $id);
            ++$n;
        }
        DB::table('kazama_mcp_codes')->where('expires_at', '<', Carbon::now('UTC')->format('Y-m-d H:i:s'))->delete();

        return $n;
    }

    public static function startOfDayUtc(): string
    {
        return Carbon::now(config('kazama.timezone'))->startOfDay()->utc()->format('Y-m-d H:i:s');
    }

    public static function callsToday(int $connectionId): int
    {
        return DB::table('kazama_logs')->where('connection_id', $connectionId)->where('action', 'mcp.call')
            ->where('created_at', '>=', self::startOfDayUtc())->count();
    }

    public static function serialize(object $c): array
    {
        return [
            'id' => (int) $c->id, 'name' => $c->name, 'platform' => $c->platform, 'authType' => $c->auth_type,
            'label' => Platforms::label($c->platform, $c->name) . '-MCP',
            'settings' => [
                'access' => $c->access, 'servers_mode' => $c->servers_mode, 'server_ids' => json_decode((string) $c->server_ids, true) ?: [],
                'daily_limit' => (int) $c->daily_limit, 'auto_disconnect_days' => (int) $c->auto_disconnect_days, 'auto_backup' => (bool) $c->auto_backup,
            ],
            'callsToday' => self::callsToday((int) $c->id),
            'lastUsedAt' => $c->last_used_at ? Carbon::parse($c->last_used_at, 'UTC')->toIso8601String() : null,
            'expiresAt' => $c->expires_at ? Carbon::parse($c->expires_at, 'UTC')->toIso8601String() : null,
            'createdAt' => Carbon::parse($c->created_at, 'UTC')->toIso8601String(),
        ];
    }
}
