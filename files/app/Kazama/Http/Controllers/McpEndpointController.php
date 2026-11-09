<?php

namespace Pterodactyl\Kazama\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Pterodactyl\Kazama\Mcp\Connections;
use Pterodactyl\Kazama\Mcp\McpServer;

class McpEndpointController extends Controller
{
    public static function unauthorized(string $msg = 'unauthorized'): JsonResponse
    {
        $meta = url('/.well-known/oauth-protected-resource');

        return new JsonResponse(['error' => 'invalid_token', 'error_description' => $msg], 401, ['WWW-Authenticate' => 'Bearer realm="kazama", resource_metadata="' . $meta . '"']);
    }

    public function handle(Request $request)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return self::unauthorized('Token diperlukan.');
        }
        $conn = Connections::authenticate($token);
        if (!$conn) {
            return self::unauthorized('Token tidak valid, kedaluwarsa atau sudah diputuskan.');
        }

        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            return new JsonResponse(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32700, 'message' => 'Parse error']], 400);
        }

        $isBatch = array_is_list($body);
        $out = [];
        foreach ($isBatch ? $body : [$body] as $msg) {
            if (!is_array($msg)) {
                continue;
            }
            $r = McpServer::handle($msg, $conn);
            if ($r !== null) {
                $out[] = $r;
            }
        }
        if (!$out) {
            return new Response('', 202);
        }

        return new JsonResponse($isBatch ? $out : $out[0]);
    }

    public function notAllowed(): Response
    {
        return new Response('', 405, ['Allow' => 'POST']);
    }

    public function revokeSelf(Request $request): JsonResponse
    {
        $conn = $request->bearerToken() ? Connections::authenticate($request->bearerToken()) : null;
        if ($conn) {
            Connections::revoke((int) $conn->id);
        }

        return new JsonResponse(['ok' => true]);
    }
}
