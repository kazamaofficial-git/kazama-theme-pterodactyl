<?php

namespace Pterodactyl\Kazama\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class KLog
{
    /**
     * @param array{server?:mixed,target?:string,detail?:string,status?:string,source?:string,actor?:array} $o
     */
    public static function write(string $action, array $o = []): void
    {
        try {
            $actor = $o['actor'] ?? Actor::resolve();
            $server = $o['server'] ?? null;
            $sid = null;
            $sname = null;
            if (is_object($server)) {
                $sid = $server->id ?? null;
                $sname = $server->name ?? null;
            } elseif (is_array($server)) {
                [$sid, $sname] = [$server['id'] ?? null, $server['name'] ?? null];
            }

            DB::table('kazama_logs')->insert([
                'source' => $o['source'] ?? $actor['source'],
                'actor_id' => $actor['id'],
                'actor_name' => mb_substr($actor['name'], 0, 120),
                'client' => $actor['client'],
                'action' => mb_substr($action, 0, 64),
                'server_id' => $sid,
                'server_name' => $sname ? mb_substr($sname, 0, 191) : null,
                'target' => isset($o['target']) ? mb_substr((string) $o['target'], 0, 191) : null,
                'detail' => isset($o['detail']) ? mb_substr((string) $o['detail'], 0, 2000) : null,
                'status' => $o['status'] ?? 'ok',
                'connection_id' => $actor['conn'],
                'created_at' => now('UTC')->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[kazama:log] ' . $e->getMessage());
        }
    }
}
