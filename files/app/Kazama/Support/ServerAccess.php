<?php

namespace Pterodactyl\Kazama\Support;

use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\Server;

final class ServerAccess
{
    /** Cari server berdasar uuid/uuidShort dan pastikan $user boleh mengaksesnya (opsional: izin subuser). */
    public static function get($user, string $ref, ?string $permission = null): Server
    {
        $server = Server::withoutGlobalScopes()->where('uuidShort', $ref)->orWhere('uuid', $ref)->first();
        abort_unless($server && self::can($user, $server, $permission), 404);

        return $server;
    }

    public static function can($user, Server $server, ?string $permission = null): bool
    {
        if (Restrict::isSuper($user) || (int) $server->owner_id === (int) $user->id) {
            return true;
        }
        if (Restrict::applies($user) && (int) $server->kazama_created_by === (int) $user->id) {
            return true;
        }

        $sub = DB::table('subusers')->where('server_id', $server->id)->where('user_id', $user->id)->first();
        if (!$sub) {
            return false;
        }
        if ($permission === null) {
            return true;
        }
        $perms = json_decode((string) ($sub->permissions ?? '[]'), true) ?: [];

        return in_array($permission, $perms, true);
    }

    /** Semua server yang bisa diurus user (pemilik, subuser, dan—untuk admin—buatan sendiri / semua bagi super admin). */
    public static function listFor($user)
    {
        $q = Server::withoutGlobalScopes()->orderBy('name');
        if (Restrict::isSuper($user)) {
            return $q->get();
        }

        $sub = DB::table('subusers')->where('user_id', $user->id)->pluck('server_id');

        return $q->where(function ($w) use ($user, $sub) {
            $w->where('owner_id', $user->id)->orWhereIn('id', $sub);
            if (Restrict::applies($user)) {
                $w->orWhere('kazama_created_by', $user->id);
            }
        })->get();
    }
}
