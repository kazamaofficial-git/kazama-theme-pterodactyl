<?php

namespace Pterodactyl\Kazama\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pterodactyl\Kazama\Mcp\Connections;
use Pterodactyl\Kazama\Mcp\Platforms;
use Pterodactyl\Kazama\Mcp\Tools;

/** OAuth 2.1 (PKCE) untuk koneksi otomatis: Claude, ChatGPT, Cursor, VS Code, dll. */
class McpOAuthController extends Controller
{
    public function protectedResource(): JsonResponse
    {
        return new JsonResponse(['resource' => url('/api/mcp'), 'authorization_servers' => [rtrim(url('/'), '/')], 'bearer_methods_supported' => ['header'], 'resource_name' => Connections::defaultName()]);
    }

    public function metadata(): JsonResponse
    {
        return new JsonResponse([
            'issuer' => rtrim(url('/'), '/'),
            'authorization_endpoint' => url('/api/mcp/oauth/authorize'),
            'token_endpoint' => url('/api/mcp/oauth/token'),
            'registration_endpoint' => url('/api/mcp/oauth/register'),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'scopes_supported' => ['mcp'],
        ]);
    }

    private function err(string $e, string $d = '', int $status = 400): JsonResponse
    {
        return new JsonResponse(['error' => $e, 'error_description' => $d], $status);
    }

    private static function redirectOk(string $uri): bool
    {
        $p = parse_url($uri);
        if (!$p || empty($p['scheme'])) {
            return false;
        }
        if (in_array($p['scheme'], ['javascript', 'data', 'file', 'vbscript'], true)) {
            return false;
        }
        if ($p['scheme'] === 'http') {
            return in_array($p['host'] ?? '', ['localhost', '127.0.0.1', '[::1]'], true);
        }

        return true; // https atau skema aplikasi (cursor://, vscode://, ...)
    }

    public function register(Request $request): JsonResponse
    {
        $uris = $request->input('redirect_uris');
        if (!is_array($uris) || !$uris) {
            return $this->err('invalid_redirect_uri', 'redirect_uris wajib.');
        }
        foreach ($uris as $u) {
            if (!is_string($u) || !self::redirectOk($u)) {
                return $this->err('invalid_redirect_uri', 'redirect_uri tidak valid.');
            }
        }
        $id = 'kzc_' . Str::random(24);
        $name = mb_substr((string) $request->input('client_name', 'Aplikasi AI'), 0, 120);
        DB::table('kazama_mcp_clients')->insert(['client_id' => $id, 'client_name' => $name, 'redirect_uris' => json_encode(array_values($uris)), 'created_at' => now('UTC')->format('Y-m-d H:i:s')]);

        return new JsonResponse(['client_id' => $id, 'client_name' => $name, 'redirect_uris' => $uris, 'grant_types' => ['authorization_code', 'refresh_token'], 'response_types' => ['code'], 'token_endpoint_auth_method' => 'none'], 201);
    }

    private function clientFor(Request $r)
    {
        $c = DB::table('kazama_mcp_clients')->where('client_id', (string) $r->input('client_id'))->first();
        if (!$c) {
            return [null, 'Aplikasi tidak dikenal (client_id).'];
        }
        $uri = (string) $r->input('redirect_uri');
        if (!in_array($uri, json_decode($c->redirect_uris, true) ?: [], true)) {
            return [null, 'redirect_uri tidak cocok dengan yang terdaftar.'];
        }
        if ($r->input('response_type') !== 'code' || !$r->input('code_challenge') || $r->input('code_challenge_method', 'S256') !== 'S256') {
            return [null, 'Permintaan harus memakai response_type=code dan PKCE S256.'];
        }

        return [$c, null];
    }

    public function authorizeForm(Request $request)
    {
        [$client, $error] = $this->clientFor($request);
        if ($error) {
            return response()->view('kazama::mcp.authorize', ['error' => $error], 400);
        }
        $user = Auth::user();
        if (!$user) {
            $request->session()->put('kazama_return', $request->fullUrl());

            return redirect('/auth/login');
        }

        return view('kazama::mcp.authorize', [
            'error' => null, 'client' => $client, 'user' => $user, 'query' => $request->query(),
            'name' => Connections::defaultName(), 'defaults' => Connections::defaults($user->id),
            'platform' => Platforms::guess($client->client_name),
            'servers' => \Pterodactyl\Kazama\Support\ServerAccess::listFor($user)->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->all(),
            'platforms' => Platforms::LIST,
        ]);
    }

    public function authorizeSubmit(Request $request): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user, 401);
        [$client, $error] = $this->clientFor($request);
        abort_if($error, 400, (string) $error);

        $uri = (string) $request->input('redirect_uri');
        $state = $request->input('state');
        $glue = str_contains($uri, '?') ? '&' : '?';
        $tail = $state !== null ? '&state=' . rawurlencode((string) $state) : '';

        if ($request->input('decision') !== 'approve') {
            return redirect()->away($uri . $glue . 'error=access_denied' . $tail);
        }

        $platform = Platforms::valid((string) $request->input('platform')) ? $request->input('platform') : Platforms::guess($client->client_name);
        $name = trim((string) $request->input('name')) ?: Connections::defaultName();
        [$conn] = Connections::create($user, $name, $platform, 'oauth', [
            'access' => $request->input('access'), 'servers_mode' => $request->input('servers_mode'), 'server_ids' => (array) $request->input('server_ids', []),
            'daily_limit' => $request->input('daily_limit'), 'auto_disconnect_days' => $request->input('auto_disconnect_days'),
            'auto_backup' => $request->boolean('auto_backup'),
        ]);

        $code = Str::random(64);
        DB::table('kazama_mcp_codes')->insert([
            'code_hash' => Connections::hash($code), 'client_id' => $client->client_id, 'connection_id' => $conn->id, 'redirect_uri' => $uri,
            'code_challenge' => (string) $request->input('code_challenge'), 'expires_at' => now('UTC')->addMinutes(5)->format('Y-m-d H:i:s'),
        ]);
        \Pterodactyl\Kazama\Support\KLog::write('mcp.connect', ['target' => $name, 'detail' => "OAuth: {$client->client_name}", 'actor' => ['id' => $user->id, 'name' => $user->username, 'source' => 'user', 'client' => null, 'conn' => (int) $conn->id]]);

        return redirect()->away($uri . $glue . 'code=' . rawurlencode($code) . $tail);
    }

    public function token(Request $request): JsonResponse
    {
        $grant = $request->input('grant_type');
        if ($grant === 'authorization_code') {
            $row = DB::table('kazama_mcp_codes')->where('code_hash', Connections::hash((string) $request->input('code')))->first();
            if ($row) {
                DB::table('kazama_mcp_codes')->where('code_hash', $row->code_hash)->delete(); // sekali pakai
            }
            if (!$row || $row->expires_at < now('UTC')->format('Y-m-d H:i:s')) {
                return $this->err('invalid_grant', 'Kode tidak valid atau kedaluwarsa.');
            }
            if ($row->client_id !== $request->input('client_id') || $row->redirect_uri !== $request->input('redirect_uri')) {
                return $this->err('invalid_grant', 'client_id / redirect_uri tidak cocok.');
            }
            $challenge = rtrim(strtr(base64_encode(hash('sha256', (string) $request->input('code_verifier'), true)), '+/', '-_'), '=');
            if (!hash_equals($row->code_challenge, $challenge)) {
                return $this->err('invalid_grant', 'PKCE verifier salah.');
            }
            $conn = DB::table('kazama_mcp_connections')->where('id', $row->connection_id)->whereNull('revoked_at')->first();
            if (!$conn) {
                return $this->err('invalid_grant', 'Koneksi sudah diputuskan.');
            }

            return new JsonResponse(Connections::issueOAuthTokens((int) $conn->id), 200, ['Cache-Control' => 'no-store']);
        }

        if ($grant === 'refresh_token') {
            $conn = DB::table('kazama_mcp_connections')->where('refresh_hash', Connections::hash((string) $request->input('refresh_token')))->whereNull('revoked_at')->first();
            if (!$conn || ($conn->expires_at && $conn->expires_at < now('UTC')->format('Y-m-d H:i:s'))) {
                return $this->err('invalid_grant', 'Refresh token tidak valid.');
            }

            return new JsonResponse(Connections::issueOAuthTokens((int) $conn->id), 200, ['Cache-Control' => 'no-store']);
        }

        return $this->err('unsupported_grant_type');
    }
}
