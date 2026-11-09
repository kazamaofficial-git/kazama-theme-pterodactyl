<?php

namespace Pterodactyl\Kazama\Mcp;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Kazama\Support\Actor;
use Pterodactyl\Kazama\Support\KLog;
use Pterodactyl\Kazama\Support\Restrict;
use Pterodactyl\Kazama\Support\ServerAccess;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;

/** Implementasi JSON-RPC MCP (Streamable HTTP, stateless). */
final class McpServer
{
    public const VERSION = '2025-03-26';

    public static function instructions($user, object $conn): string
    {
        $who = Restrict::applies($user) ? 'an admin with limited access (' . Restrict::name() . ')' : ($user->root_admin ? 'an admin' : 'a regular user');
        $mode = $conn->access === 'read' ? 'READ-ONLY (write tools are hidden)' : 'FULL access';

        return "You control Kazama Cloud (a Pterodactyl panel) for {$user->username}, {$who}. Access: {$mode}.\n"
            . "RULES:\n"
            . "1. NEVER guess which server to act on. If the user did not name a server, call list_servers and ASK which one.\n"
            . "2. If a request is vague or missing details (what content, which file, which values), ASK for clarification instead of inventing anything.\n"
            . "3. For destructive tools (delete, restore, reinstall, kill, rotate, suspend...) first tell the user exactly what will happen, wait for an explicit yes, then call again with confirmed=true.\n"
            . '4. Before risky changes' . ($conn->auto_backup ? ' a backup is created automatically; wait for it.' : ' consider creating a backup first.') . "\n"
            . "5. Deleted files go to the Trash for 7 days and can be restored (trash_restore).\n"
            . "6. All times are WIB (Asia/Jakarta). Reply in the user's language.\n"
            . ($conn->daily_limit > 0 ? "7. Daily action limit: {$conn->daily_limit}. Avoid unnecessary calls.\n" : '');
    }

    /** Server yang boleh dipakai koneksi ini. */
    public static function allowedServers(Ctx $c)
    {
        $all = ServerAccess::listFor($c->user);
        if ($c->conn->servers_mode === 'selected') {
            $ids = array_map('intval', json_decode((string) $c->conn->server_ids, true) ?: []);
            $all = $all->filter(fn ($s) => in_array((int) $s->id, $ids, true));
        }

        return $all->values();
    }

    private static function resolveServer(Ctx $c, ?string $ref): Server
    {
        $list = self::allowedServers($c);
        $names = $list->map(fn ($s) => "- {$s->name} ({$s->uuidShort})")->implode("\n");
        $ref = trim((string) $ref);
        if ($ref === '') {
            throw new ToolError("Server belum disebutkan. JANGAN menebak: tanyakan kepada pengguna server mana yang dimaksud.\nServer yang tersedia:\n" . ($names ?: '(tidak ada)'));
        }
        $l = mb_strtolower($ref);
        $hit = $list->filter(fn ($s) => $s->uuidShort === $ref || $s->uuid === $ref || mb_strtolower($s->name) === $l);
        if ($hit->isEmpty()) {
            $hit = $list->filter(fn ($s) => str_contains(mb_strtolower($s->name), $l));
        }
        if ($hit->count() > 1) {
            throw new ToolError("\"{$ref}\" cocok dengan lebih dari satu server. Tanyakan kepada pengguna yang mana:\n" . $hit->map(fn ($s) => "- {$s->name} ({$s->uuidShort})")->implode("\n"));
        }
        if ($hit->isEmpty()) {
            throw new ToolError("Server \"{$ref}\" tidak ditemukan atau tidak diizinkan untuk koneksi ini. Tanyakan kepada pengguna.\nServer yang tersedia:\n" . ($names ?: '(tidak ada)'));
        }

        return $hit->first();
    }

    private static function validate(array $tool, array $args): void
    {
        foreach ($tool['params'] as $k => $p) {
            $v = $args[$k] ?? null;
            if (($p['_req'] ?? false) && ($v === null || $v === '' || $v === [])) {
                throw new ToolError("Parameter \"{$k}\" wajib diisi. Tanyakan kepada pengguna nilai yang tepat; jangan mengarang.");
            }
            if ($v === null) {
                continue;
            }
            $ok = match ($p['type']) {
                'string' => is_string($v) || is_numeric($v),
                'integer' => is_int($v) || (is_string($v) && ctype_digit($v)),
                'boolean' => is_bool($v),
                'array' => is_array($v),
                'object' => is_array($v),
                default => true,
            };
            if (!$ok) {
                throw new ToolError("Parameter \"{$k}\" bertipe salah (harus {$p['type']}).");
            }
            if (!empty($p['enum']) && !in_array($v, $p['enum'], true)) {
                throw new ToolError("Parameter \"{$k}\" harus salah satu dari: " . implode(', ', $p['enum']));
            }
        }
    }

    /** Backup otomatis sebelum aksi berisiko. Gagal = aksi dibatalkan. */
    private static function guardBackup(Ctx $c): ?string
    {
        $recent = DB::table('backups')->where('server_id', $c->server->id)->whereNull('deleted_at')->where('is_successful', true)
            ->where('name', 'like', 'Kazama MCP%')->where('created_at', '>=', now('UTC')->subMinutes(10)->format('Y-m-d H:i:s'))->first();
        if ($recent) {
            return 'memakai backup terbaru';
        }
        $limit = (int) DB::table("servers")->where("id", $c->server->id)->value("backup_limit");
        if ($limit === 0) {
            throw new ToolError('Cadangkan otomatis aktif tetapi server ini memiliki batas backup 0, sehingga aksi dibatalkan demi keamanan. Naikkan batas backup, atau matikan "Cadangkan otomatis" di pengaturan koneksi MCP.');
        }
        $res = $c->srv('POST', 'backups', ['json' => ['name' => 'Kazama MCP ' . now(config('kazama.timezone'))->format('d/m H:i')]]);
        $uuid = $res['attributes']['uuid'] ?? null;
        if (!$uuid) {
            throw new ToolError('Gagal membuat backup otomatis; aksi dibatalkan.');
        }
        $deadline = time() + 45;
        do {
            sleep(2);
            $row = DB::table('backups')->where('uuid', $uuid)->first();
            if ($row && $row->completed_at) {
                if (!$row->is_successful) {
                    throw new ToolError('Backup otomatis gagal; aksi dibatalkan.');
                }

                return 'backup dibuat';
            }
        } while (time() < $deadline);
        throw new ToolError('Backup otomatis belum selesai dalam 45 detik; aksi dibatalkan. Coba lagi sebentar lagi.');
    }

    private static function consoleLine(Ctx $c, string $text): void
    {
        $label = Platforms::label($c->conn->platform, $c->conn->name);
        DB::table('kazama_console_lines')->insert(['server_id' => $c->server->id, 'line' => mb_substr("[{$label}-MCP]: {$text}", 0, 500), 'created_at' => now('UTC')->format('Y-m-d H:i:s')]);
    }

    private static function ok($id, $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    private static function err($id, int $code, string $msg): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $msg]];
    }

    private static function text($data, bool $isError = false): array
    {
        $t = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return ['content' => [['type' => 'text', 'text' => $t]], 'isError' => $isError];
    }

    /** @return array|null null untuk notifikasi (tanpa balasan) */
    public static function handle(array $msg, object $conn): ?array
    {
        $id = $msg['id'] ?? null;
        $method = (string) ($msg['method'] ?? '');
        $params = (array) ($msg['params'] ?? []);

        if (!array_key_exists('id', $msg)) {
            return null; // notifications/initialized dll.
        }

        $user = User::withoutGlobalScopes()->find($conn->user_id);
        if (!$user) {
            return self::err($id, -32001, 'Akun tidak ditemukan.');
        }
        Auth::setUser($user);

        switch ($method) {
            case 'initialize':
                return self::ok($id, [
                    'protocolVersion' => in_array($params['protocolVersion'] ?? '', ['2024-11-05', '2025-03-26', '2025-06-18'], true) ? $params['protocolVersion'] : self::VERSION,
                    'capabilities' => ['tools' => ['listChanged' => false]],
                    'serverInfo' => ['name' => 'kazama-cloud', 'title' => Connections::defaultName(), 'version' => '2.0.0'],
                    'instructions' => self::instructions($user, $conn),
                ]);
            case 'ping':
                return self::ok($id, (object) []);
            case 'tools/list':
                $out = [];
                foreach (Tools::visible($user, $conn) as $t) {
                    $out[] = ['name' => $t['name'], 'description' => $t['desc'], 'inputSchema' => Tools::schema($t),
                        'annotations' => ['readOnlyHint' => !$t['write'], 'destructiveHint' => (bool) $t['confirm']]];
                }

                return self::ok($id, ['tools' => $out]);
            case 'tools/call':
                return self::ok($id, self::call($conn, $user, (string) ($params['name'] ?? ''), (array) ($params['arguments'] ?? [])));
            default:
                return self::err($id, -32601, "Method tidak dikenal: {$method}");
        }
    }

    private static function call(object $conn, $user, string $name, array $args): array
    {
        $label = Platforms::label($conn->platform, $conn->name) . '-MCP';
        Actor::as($user->username . ' via ' . $label, 'mcp', null, $label, (int) $conn->id, (int) $user->id);
        $ctx = null;
        $tool = null;
        $status = 'ok';
        $detail = null;
        $logIt = false;
        try {
            $visible = Tools::visible($user, $conn);
            $tool = $visible[$name] ?? null;
            if (!$tool) {
                $exists = Tools::all()[$name] ?? null;
                throw new ToolError($exists ? ($exists['write'] && $conn->access === 'read' ? 'Alat ini ditolak: koneksi disetel Baca saja.' : 'Alat ini tidak tersedia untuk peran akun Anda.') : "Alat \"{$name}\" tidak ada.");
            }
            $logIt = true;
            if ($conn->daily_limit > 0 && Connections::callsToday((int) $conn->id) >= $conn->daily_limit) {
                $status = 'denied';
                throw new ToolError("Batas aksi harian ({$conn->daily_limit}) tercapai. Reset pukul 00:00 WIB atau naikkan batas di pengaturan MCP.");
            }
            self::validate($tool, $args);

            $ctx = new Ctx($conn, $user);
            if ($tool['scope'] === 'server') {
                $ctx->server = self::resolveServer($ctx, $args['server'] ?? null);
            }

            $needConfirm = is_callable($tool['confirm']) ? ($tool['confirm'])($args) : (bool) $tool['confirm'];
            if ($needConfirm && empty($args['confirmed'])) {
                $status = 'pending';
                $where = $ctx->server ? " pada server \"{$ctx->server->name}\"" : '';
                throw new ToolError("KONFIRMASI DIPERLUKAN: aksi {$name}{$where} bersifat berisiko. Jelaskan dampaknya kepada pengguna dan tunggu persetujuan eksplisit, lalu panggil lagi dengan confirmed=true.");
            }

            $note = null;
            if ($tool['backup'] && $conn->auto_backup && $ctx->server) {
                $note = self::guardBackup($ctx);
            }

            $reasonArgs = $args;
            $result = ($tool['run'])($ctx, $args);

            if ($tool['write'] && $ctx->server && $tool['console']) {
                self::consoleLine($ctx, ($tool['console'])($reasonArgs));
            }
            $detail = $note ? "[{$note}]" : null;

            return self::text($result);
        } catch (ToolError $e) {
            $status = $status === 'ok' ? 'error' : $status;
            $detail = $e->getMessage();

            return self::text($e->getMessage(), true);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException|\Symfony\Component\HttpKernel\Exception\HttpException|\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $status = 'error';
            $detail = $e->getMessage() ?: 'Ditolak.';

            return self::text('Ditolak: ' . $detail, true);
        } catch (\Throwable $e) {
            $status = 'error';
            $detail = $e->getMessage();
            report($e);

            return self::text('Terjadi kesalahan internal: ' . $e->getMessage(), true);
        } finally {
            if ($logIt) {
                $argLog = array_diff_key($args, array_flip(['content', 'password', 'current_password', 'public_key', 'confirmed']));
                $short = mb_substr(json_encode($argLog, JSON_UNESCAPED_UNICODE) ?: '', 0, 600);
                KLog::write('mcp.call', [
                    'server' => $ctx?->server, 'target' => $name, 'status' => $status,
                    'detail' => trim(($detail ? $detail . ' ' : '') . $short),
                ]);
            }
            Actor::clear();
        }
    }
}
