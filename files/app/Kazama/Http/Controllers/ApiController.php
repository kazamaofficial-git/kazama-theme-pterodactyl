<?php

namespace Pterodactyl\Kazama\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Kazama\Support\Cadence;
use Pterodactyl\Kazama\Support\Restrict;
use Pterodactyl\Kazama\Support\Settings;
use Pterodactyl\Models\Server;

class ApiController extends Controller
{
    private function unreadQuery(int $userId)
    {
        return DB::table('kazama_notifications as n')
            ->leftJoin('kazama_notification_reads as r', function ($j) use ($userId) {
                $j->on('r.notification_id', '=', 'n.id')->where('r.user_id', '=', $userId);
            })
            ->where(function ($q) use ($userId) {
                $q->whereNull('n.user_id')->orWhere('n.user_id', $userId);
            });
    }

    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();
        $subIds = DB::table('subusers')->where('user_id', $user->id)->pluck('server_id');
        $servers = DB::table('servers')->where('owner_id', $user->id)->orWhereIn('id', $subIds)
            ->get(['uuidShort', 'kazama_expires_at', 'kazama_expired']);

        $expiries = [];
        foreach ($servers as $s) {
            $exp = $s->kazama_expires_at ? Carbon::parse($s->kazama_expires_at, 'UTC') : null;
            $expiries[$s->uuidShort] = [
                'expiresAt' => $exp ? $exp->toIso8601String() : null,
                'expired' => (bool) $s->kazama_expired,
                'daysLeft' => $exp ? (int) ceil(($exp->getTimestamp() - time()) / 86400) : null,
            ];
        }

        return new JsonResponse([
            'config' => [
                'brand' => config('kazama.brand'),
                'developer' => config('kazama.developer'),
                'telegram' => Settings::get('contact_telegram', config('kazama.contact.telegram')),
                'whatsapp' => Settings::get('contact_whatsapp', config('kazama.contact.whatsapp')),
                'registration' => (bool) config('kazama.registration'),
            ],
            'unread' => $this->unreadQuery($user->id)->whereNull('r.notification_id')->count(),
            'expiries' => (object) $expiries,
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $rows = $this->unreadQuery($request->user()->id)
            ->leftJoin('servers as s', 's.id', '=', 'n.server_id')
            ->orderByDesc('n.id')->limit(30)
            ->get(['n.id', 'n.type', 'n.title', 'n.message', 'n.created_at', 's.name as server_name', 'r.notification_id as read_id']);

        return new JsonResponse(['data' => $rows->map(fn ($n) => [
            'id' => (int) $n->id,
            'type' => $n->type,
            'title' => $n->title,
            'message' => $n->message,
            'serverName' => $n->server_name,
            'createdAt' => Carbon::parse($n->created_at, 'UTC')->toIso8601String(),
            'read' => $n->read_id !== null,
        ])]);
    }

    public function read(Request $request, int $id): JsonResponse
    {
        DB::table('kazama_notification_reads')->insertOrIgnore(['notification_id' => $id, 'user_id' => $request->user()->id]);

        return new JsonResponse(['ok' => true]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $uid = $request->user()->id;
        $ids = $this->unreadQuery($uid)->whereNull('r.notification_id')->pluck('n.id');
        foreach ($ids as $id) {
            DB::table('kazama_notification_reads')->insertOrIgnore(['notification_id' => $id, 'user_id' => $uid]);
        }

        return new JsonResponse(['ok' => true]);
    }

    private function authorizeServer(Request $request, string $uuid): Server
    {
        $user = $request->user();
        $server = Server::withoutGlobalScopes()->where('uuidShort', $uuid)->orWhere('uuid', $uuid)->firstOrFail();

        if (Restrict::isSuper($user) || (int) $server->owner_id === (int) $user->id) {
            return $server;
        }
        if (Restrict::applies($user) && (int) $server->kazama_created_by === (int) $user->id) {
            return $server;
        }

        $sub = DB::table('subusers')->where('server_id', $server->id)->where('user_id', $user->id)->first();
        if ($sub) {
            $perms = DB::getSchemaBuilder()->hasTable('permissions')
                ? DB::table('permissions')->where('subuser_id', $sub->id)->pluck('permission')->all()
                : (json_decode($sub->permissions ?? '[]', true) ?: []);
            if (in_array('backup.create', $perms, true)) {
                return $server;
            }
        }

        abort(404);
    }

    private function serialize(Server $server): array
    {
        $cfg = DB::table('kazama_auto_backups')->where('server_id', $server->id)->first();

        return [
            'enabled' => $cfg ? (bool) $cfg->enabled : false,
            'frequency' => $cfg->frequency ?? 'daily',
            'time' => $cfg->time ?? '02:00',
            'weekday' => (int) ($cfg->weekday ?? 1),
            'nextRun' => $cfg && $cfg->enabled && $cfg->next_run_at ? Cadence::wib($cfg->next_run_at) : null,
            'lastRun' => $cfg && $cfg->last_run_at ? Cadence::wib($cfg->last_run_at) : null,
        ];
    }

    public function autoBackup(Request $request, string $uuid): JsonResponse
    {
        return new JsonResponse($this->serialize($this->authorizeServer($request, $uuid)));
    }

    public function saveAutoBackup(Request $request, string $uuid): JsonResponse
    {
        $server = $this->authorizeServer($request, $uuid);
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'frequency' => ['required', 'in:hourly,daily,weekly'],
            'time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'weekday' => ['required', 'integer', 'between:0,6'],
        ]);

        $next = $data['enabled']
            ? Cadence::next($data['frequency'], $data['time'], (int) $data['weekday'])->format('Y-m-d H:i:s')
            : null;

        DB::table('kazama_auto_backups')->updateOrInsert(['server_id' => $server->id], [
            'enabled' => $data['enabled'],
            'frequency' => $data['frequency'],
            'time' => $data['time'],
            'weekday' => $data['weekday'],
            'next_run_at' => $next,
            'updated_at' => now('UTC')->format('Y-m-d H:i:s'),
        ]);

        return new JsonResponse($this->serialize($server));
    }
}
