<?php

namespace Pterodactyl\Kazama\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Kazama\Support\Cadence;
use Pterodactyl\Kazama\Support\ServerAccess;
use Pterodactyl\Kazama\Support\Settings;

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
        $servers = DB::table('servers')->where(function ($q) use ($user, $subIds) {
            $q->where('owner_id', $user->id)->orWhereIn('id', $subIds);
        })->get(['uuidShort', 'kazama_expires_at', 'kazama_expired', 'kazama_suspended_by', 'kazama_suspend_reason', 'kazama_suspended_at']);

        $expiries = [];
        foreach ($servers as $s) {
            $exp = $s->kazama_expires_at ? Carbon::parse($s->kazama_expires_at, 'UTC') : null;
            $expiries[$s->uuidShort] = [
                'expiresAt' => $exp ? $exp->toIso8601String() : null,
                'expired' => (bool) $s->kazama_expired,
                'daysLeft' => $exp ? (int) ceil(($exp->getTimestamp() - time()) / 86400) : null,
                'suspendedBy' => $s->kazama_suspended_by,
                'suspendReason' => $s->kazama_suspend_reason,
                'suspendedAt' => $s->kazama_suspended_at ? Carbon::parse($s->kazama_suspended_at, 'UTC')->toIso8601String() : null,
            ];
        }

        $tombstones = DB::table('kazama_tombstones')->where('owner_id', $user->id)
            ->where('expires_at', '>', now('UTC')->format('Y-m-d H:i:s'))->orderByDesc('id')->get()
            ->map(fn ($t) => [
                'id' => (int) $t->id, 'name' => $t->server_name, 'address' => $t->address, 'deletedBy' => $t->deleted_by_name,
                'reason' => $t->reason, 'deletedAt' => Carbon::parse($t->deleted_at, 'UTC')->toIso8601String(),
            ])->all();

        return new JsonResponse([
            'config' => [
                'brand' => config('kazama.brand'),
                'developer' => config('kazama.developer'),
                'telegram' => Settings::get('contact_telegram', config('kazama.contact.telegram')),
                'whatsapp' => Settings::get('contact_whatsapp', config('kazama.contact.whatsapp')),
                'protectName' => Settings::get('protect_name', config('kazama.protect_name')),
            ],
            'unread' => $this->unreadQuery($user->id)->whereNull('r.notification_id')->count(),
            'expiries' => (object) $expiries,
            'tombstones' => $tombstones,
            'background' => BackgroundController::read($user->id),
            // Setelah login lewat halaman /api/mcp/oauth/authorize, kembalikan user ke sana (sekali pakai).
            'returnTo' => $request->session()->pull('kazama_return'),
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $rows = $this->unreadQuery($request->user()->id)
            ->leftJoin('servers as s', 's.id', '=', 'n.server_id')
            ->orderByDesc('n.id')->limit(30)
            ->get(['n.id', 'n.type', 'n.title', 'n.message', 'n.created_at', 's.name as server_name', 'r.notification_id as read_id']);

        return new JsonResponse(['data' => $rows->map(fn ($n) => [
            'id' => (int) $n->id, 'type' => $n->type, 'title' => $n->title, 'message' => $n->message,
            'serverName' => $n->server_name, 'createdAt' => Carbon::parse($n->created_at, 'UTC')->toIso8601String(), 'read' => $n->read_id !== null,
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
        foreach ($this->unreadQuery($uid)->whereNull('r.notification_id')->pluck('n.id') as $id) {
            DB::table('kazama_notification_reads')->insertOrIgnore(['notification_id' => $id, 'user_id' => $uid]);
        }

        return new JsonResponse(['ok' => true]);
    }

    private function serialize($server): array
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
        return new JsonResponse($this->serialize(ServerAccess::get($request->user(), $uuid, 'backup.create')));
    }

    public function saveAutoBackup(Request $request, string $uuid): JsonResponse
    {
        $server = ServerAccess::get($request->user(), $uuid, 'backup.create');
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'frequency' => ['required', 'in:hourly,daily,weekly'],
            'time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'weekday' => ['required', 'integer', 'between:0,6'],
        ]);

        $next = $data['enabled'] ? Cadence::next($data['frequency'], $data['time'], (int) $data['weekday'])->format('Y-m-d H:i:s') : null;

        DB::table('kazama_auto_backups')->updateOrInsert(['server_id' => $server->id], [
            'enabled' => $data['enabled'], 'frequency' => $data['frequency'], 'time' => $data['time'],
            'weekday' => $data['weekday'], 'next_run_at' => $next, 'updated_at' => now('UTC')->format('Y-m-d H:i:s'),
        ]);

        return new JsonResponse($this->serialize($server));
    }

    /** Baris "[Claude-MCP]: Restart Server" yang ditampilkan di console server (polling dari frontend). */
    public function consoleLines(Request $request, string $uuid): JsonResponse
    {
        $server = ServerAccess::get($request->user(), $uuid);
        $after = $request->query('after');

        if ($after === null || !ctype_digit((string) $after)) {
            return new JsonResponse(['lines' => [], 'last' => (int) DB::table('kazama_console_lines')->where('server_id', $server->id)->max('id')]);
        }

        $rows = DB::table('kazama_console_lines')->where('server_id', $server->id)->where('id', '>', (int) $after)->orderBy('id')->limit(50)->get(['id', 'line']);

        return new JsonResponse(['lines' => $rows->pluck('line'), 'last' => $rows->isEmpty() ? (int) $after : (int) $rows->last()->id]);
    }
}
