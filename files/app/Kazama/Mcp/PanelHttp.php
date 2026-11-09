<?php

namespace Pterodactyl\Kazama\Mcp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Kazama\Support\Actor;
use Pterodactyl\Models\ApiKey;

/**
 * Memanggil Client API / Application API panel sendiri (loopback) memakai API key milik user,
 * sehingga semua aturan izin, subuser, dan Kazama Protect tetap berlaku.
 */
class PanelHttp
{
    public function __construct(private object $conn, private $user)
    {
    }

    private function bearer(string $type): string
    {
        $id = $type === 'app' ? $this->conn->app_key_id : $this->conn->api_key_id;
        if ($type === 'app' && !$id) {
            // Akun baru dijadikan admin setelah koneksi dibuat → buat key aplikasi sekarang.
            abort_unless($this->user->root_admin, 403);
            $id = Connections::createAppKey($this->user, $this->conn->name);
            DB::table('kazama_mcp_connections')->where('id', $this->conn->id)->update(['app_key_id' => $id]);
            $this->conn->app_key_id = $id;
        }
        $key = ApiKey::query()->find($id);
        if (!$key) {
            throw new ToolError('Kredensial internal koneksi ini sudah dicabut. Hubungkan ulang dari menu MCP.');
        }

        return $key->identifier . decrypt($key->token);
    }

    /**
     * @param  'client'|'app'  $type
     * @return array|string
     */
    public function call(string $type, string $method, string $path, array $opt = [])
    {
        $internal = config('kazama.mcp.internal_url');
        $base = rtrim($internal ?: config('app.url'), '/');
        $url = $base . '/api/' . ($type === 'app' ? 'application' : 'client') . '/' . ltrim($path, '/');

        $headers = [
            'X-Kazama-Mcp' => Actor::mcpSignature((int) $this->conn->id, (int) $this->user->id),
            'Accept' => 'application/json',
        ];
        if ($internal) {
            $headers['Host'] = parse_url((string) config('app.url'), PHP_URL_HOST);
        }
        if (!empty($opt['reason'])) {
            $headers['X-Kazama-Reason'] = $opt['reason'];
        }

        $req = Http::withToken($this->bearer($type))->withHeaders($headers)->timeout(90);
        if (config('kazama.mcp.verify_ssl') === false) {
            $req = $req->withoutVerifying();
        }
        if (isset($opt['raw'])) {
            $req = $req->withBody((string) $opt['raw'], 'text/plain');
        }

        $res = $req->send(strtoupper($method), $url, array_filter([
            'query' => $opt['query'] ?? null,
            'json' => isset($opt['raw']) ? null : ($opt['json'] ?? null),
        ], fn ($v) => $v !== null));

        if ($res->status() >= 400) {
            $j = $res->json();
            $detail = $j['errors'][0]['detail'] ?? ($j['message'] ?? mb_substr($res->body(), 0, 300));
            throw new ToolError("Panel menolak permintaan (HTTP {$res->status()}): {$detail}");
        }
        if ($res->status() === 204 || $res->body() === '') {
            return ['ok' => true];
        }

        $json = $res->json();

        return $json === null ? $res->body() : $json;
    }
}
