<?php

namespace Pterodactyl\Kazama\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Proteksi "Akses Terbatas" untuk semua admin selain super admin (ID 1).
 *
 * Penegakan dilakukan berbasis path (UI admin + Application API + Client API) pada saat user
 * ter-autentikasi, sehingga tidak bergantung pada urutan middleware / route model binding.
 */
final class Restrict
{
    private static bool $busy = false;

    // [metode dipisah |, regex terhadap path relatif, jenis kepemilikan yang diperiksa pada group #1]
    private const WEB = [
        ['GET', '#^$#', null],
        ['GET', '#^users$#', null],
        ['GET|POST', '#^users/new$#', null],
        ['GET', '#^users/accounts\.json$#', null],
        ['GET|PATCH', '#^users/view/(\d+)$#', 'user'],
        ['GET', '#^servers$#', null],
        ['GET|POST', '#^servers/new$#', null],
        ['GET', '#^servers/new/nodes$#', null],
        ['GET', '#^nodes/view/\d+/allocations\.json$#', null],
        ['GET', '#^servers/view/(\d+)$#', 'server'],
        ['GET', '#^servers/view/(\d+)/(details|build|manage)$#', 'server'],
        ['POST', '#^servers/view/(\d+)/details(/container)?$#', 'server'],
        ['PATCH', '#^servers/view/(\d+)/build$#', 'server'],
        ['POST', '#^servers/view/(\d+)/manage/suspension$#', 'server'],
        ['GET', '#^kazama/expiry$#', null],
        ['POST', '#^kazama/expiry/(\d+)/extend$#', 'server'],
    ];

    private const API = [
        ['GET|POST', '#^users$#', null],
        ['GET|PATCH', '#^users/(\d+)$#', 'user'],
        ['GET|POST', '#^servers$#', null],
        ['GET', '#^servers/(\d+)$#', 'server'],
        ['PATCH', '#^servers/(\d+)/(details|build)$#', 'server'],
        ['POST', '#^servers/(\d+)/(suspend|unsuspend)$#', 'server'],
    ];

    public static function applies($user): bool
    {
        return $user && $user->root_admin && (int) $user->id !== (int) config('kazama.super_admin_id', 1);
    }

    public static function isSuper($user): bool
    {
        return $user && $user->root_admin && (int) $user->id === (int) config('kazama.super_admin_id', 1);
    }

    /** Admin terbatas yang sedang mengakses area admin / application API (dipakai global scope & model hooks). */
    public static function actor()
    {
        if (self::$busy || app()->runningInConsole()) {
            return null;
        }
        if (!request()->is('admin', 'admin/*', 'api/application/*')) {
            return null;
        }

        self::$busy = true;
        try {
            $user = Auth::user();
        } finally {
            self::$busy = false;
        }

        return self::applies($user) ? $user : null;
    }

    public static function enforce(Request $request, $user): void
    {
        if (!self::applies($user) || app()->runningInConsole()) {
            return;
        }

        $path = trim($request->path(), '/');
        $method = strtoupper($request->method());
        if ($method === 'HEAD') {
            $method = 'GET';
        }

        if ($path === 'admin' || str_starts_with($path, 'admin/')) {
            self::guard($user, trim(substr($path, 5), '/'), $method, self::WEB);
        } elseif (str_starts_with($path, 'api/application/')) {
            self::guard($user, substr($path, strlen('api/application/')), $method, self::API);
        } elseif (str_starts_with($path, 'api/client')) {
            self::guardClient($request, $user, $path);
        }
    }

    private static function guard($user, string $relative, string $method, array $rules): void
    {
        foreach ($rules as [$methods, $regex, $kind]) {
            if (!in_array($method, explode('|', $methods), true) || !preg_match($regex, $relative, $m)) {
                continue;
            }
            if ($kind && isset($m[1]) && !self::owns($user, $kind, (int) $m[1])) {
                abort(404);
            }

            return;
        }

        abort(403, 'Akses terbatas: akun admin Anda tidak memiliki izin untuk area ini.');
    }

    private static function guardClient(Request $request, $user, string $path): void
    {
        // Cegah admin terbatas melihat seluruh server panel lewat daftar server "admin".
        if (in_array($request->query('type'), ['admin', 'admin-all'], true)) {
            $request->query->set('type', 'owner');
        }

        if (preg_match('#^api/client/servers/([^/]+)#', $path, $m)) {
            $server = DB::table('servers')->where('uuidShort', $m[1])->orWhere('uuid', $m[1])
                ->first(['id', 'owner_id', 'kazama_created_by']);
            if (!$server) {
                return;
            }
            $ok = (int) $server->owner_id === (int) $user->id
                || (int) $server->kazama_created_by === (int) $user->id
                || DB::table('subusers')->where('server_id', $server->id)->where('user_id', $user->id)->exists();
            if (!$ok) {
                abort(404);
            }
        }
    }

    public static function owns($user, string $kind, int $id): bool
    {
        if ($kind === 'server') {
            $creator = DB::table('servers')->where('id', $id)->value('kazama_created_by');

            return $creator !== null && (int) $creator === (int) $user->id;
        }

        $row = DB::table('users')->where('id', $id)->first(['id', 'kazama_created_by', 'root_admin']);
        if (!$row) {
            return false;
        }
        if ((int) $row->id === (int) $user->id) {
            return true;
        }

        return !$row->root_admin && (int) $row->kazama_created_by === (int) $user->id;
    }

    /** Pemilik server yang boleh dipilih admin terbatas: dirinya sendiri atau user yang ia buat. */
    public static function allowedOwner($user, int $ownerId): bool
    {
        return self::owns($user, 'user', $ownerId);
    }
}
