<?php

namespace Pterodactyl\Kazama\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Kazama\Mcp\Connections;
use Pterodactyl\Kazama\Mcp\Platforms;
use Pterodactyl\Kazama\Mcp\Tools;
use Pterodactyl\Kazama\Support\KLog;
use Pterodactyl\Kazama\Support\ServerAccess;

/** API sesi untuk halaman /mcp di dashboard. */
class McpPanelController extends Controller
{
    private function mine(Request $r, int $id): object
    {
        $row = DB::table('kazama_mcp_connections')->where('id', $id)->where('user_id', $r->user()->id)->whereNull('revoked_at')->first();
        abort_unless($row, 404);

        return $row;
    }

    public function overview(Request $r): JsonResponse
    {
        $u = $r->user();
        $rows = DB::table('kazama_mcp_connections')->where('user_id', $u->id)->whereNull('revoked_at')->orderByDesc('id')->get();

        return new JsonResponse([
            'endpoint' => url('/api/mcp'),
            'defaultName' => Connections::defaultName(),
            'platforms' => collect(Platforms::LIST)->map(fn ($p, $id) => ['id' => $id, 'label' => $p[0], 'name' => $p[1]])->values(),
            'defaults' => Connections::defaults($u->id),
            'options' => ['limits' => Connections::LIMITS, 'disconnect' => Connections::DISCONNECT],
            'connections' => $rows->map(fn ($c) => Connections::serialize($c))->values(),
            'servers' => ServerAccess::listFor($u)->map(fn ($s) => ['id' => (int) $s->id, 'name' => $s->name, 'identifier' => $s->uuidShort])->values(),
            'role' => $u->root_admin ? ($u->id === 1 ? 'super' : 'admin') : 'user',
        ]);
    }

    public function saveDefaults(Request $r): JsonResponse
    {
        return new JsonResponse(Connections::saveDefaults($r->user()->id, $r->all()));
    }

    /** Koneksi manual: buat token (ditampilkan sekali). */
    public function create(Request $r): JsonResponse
    {
        $data = $r->validate(['name' => ['nullable', 'string', 'max:120'], 'platform' => ['required', 'string']]);
        $name = trim((string) ($data['name'] ?? '')) ?: Connections::defaultName();
        [$conn, $token] = Connections::create($r->user(), $name, $data['platform'], 'manual', (array) $r->input('settings', []));
        KLog::write('mcp.connect', ['target' => $name, 'detail' => 'Manual (token)']);

        return new JsonResponse(['connection' => Connections::serialize($conn), 'token' => $token, 'endpoint' => url('/api/mcp')], 201);
    }

    public function update(Request $r, int $id): JsonResponse
    {
        $row = $this->mine($r, $id);
        Connections::update($id, $r->only(['name', 'platform', 'access', 'servers_mode', 'server_ids', 'daily_limit', 'auto_disconnect_days', 'auto_backup']));
        KLog::write('mcp.settings', ['target' => $row->name]);

        return new JsonResponse(Connections::serialize(DB::table('kazama_mcp_connections')->find($id)));
    }

    public function destroy(Request $r, int $id): JsonResponse
    {
        $row = $this->mine($r, $id);
        Connections::revoke($id);
        KLog::write('mcp.disconnect', ['target' => $row->name]);

        return new JsonResponse(['ok' => true]);
    }

    public function activity(Request $r): JsonResponse
    {
        $q = DB::table('kazama_logs')->where('actor_id', $r->user()->id)->whereNotNull('connection_id')->orderByDesc('id');
        if ($r->query('connection')) {
            $q->where('connection_id', (int) $r->query('connection'));
        }
        $rows = $q->limit(100)->get();

        return new JsonResponse(['data' => $rows->map(fn ($l) => [
            'id' => (int) $l->id, 'client' => $l->client, 'action' => $l->action, 'tool' => $l->target, 'server' => $l->server_name,
            'status' => $l->status, 'detail' => $l->detail, 'at' => Carbon::parse($l->created_at, 'UTC')->toIso8601String(),
        ])]);
    }

    public function tools(Request $r): JsonResponse
    {
        $access = $r->query('connection') ? $this->mine($r, (int) $r->query('connection'))->access : Connections::defaults($r->user()->id)['access'];

        return new JsonResponse(['data' => Tools::catalog($r->user(), (object) ['access' => $access]), 'groups' => Tools::GROUPS]);
    }

    public function stats(Request $r): JsonResponse
    {
        $uid = $r->user()->id;
        $tz = config('kazama.timezone');
        $since = Carbon::now($tz)->startOfDay()->subDays(13)->utc()->format('Y-m-d H:i:s');
        $rows = DB::table('kazama_logs')->where('actor_id', $uid)->where('action', 'mcp.call')->where('created_at', '>=', $since)->get(['target', 'status', 'created_at', 'client']);

        $days = [];
        for ($i = 13; $i >= 0; --$i) {
            $days[Carbon::now($tz)->subDays($i)->format('Y-m-d')] = 0;
        }
        $tools = [];
        $clients = [];
        $ok = 0;
        foreach ($rows as $l) {
            $d = Carbon::parse($l->created_at, 'UTC')->setTimezone($tz)->format('Y-m-d');
            if (isset($days[$d])) {
                ++$days[$d];
            }
            $tools[$l->target] = ($tools[$l->target] ?? 0) + 1;
            $clients[$l->client ?? '-'] = ($clients[$l->client ?? '-'] ?? 0) + 1;
            $ok += $l->status === 'ok' ? 1 : 0;
        }
        arsort($tools);
        arsort($clients);

        return new JsonResponse([
            'total' => $rows->count(), 'successRate' => $rows->count() ? round($ok * 100 / $rows->count()) : null,
            'days' => collect($days)->map(fn ($n, $d) => ['date' => $d, 'count' => $n])->values(),
            'tools' => collect($tools)->take(8)->map(fn ($n, $k) => ['name' => $k, 'count' => $n])->values(),
            'clients' => collect($clients)->take(8)->map(fn ($n, $k) => ['name' => $k, 'count' => $n])->values(),
            'connections' => DB::table('kazama_mcp_connections')->where('user_id', $uid)->whereNull('revoked_at')->count(),
        ]);
    }
}
