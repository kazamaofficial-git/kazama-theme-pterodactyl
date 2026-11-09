<?php

namespace Pterodactyl\Kazama\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** CORS untuk endpoint MCP / OAuth (klien berbasis browser seperti MCP Inspector). */
class McpCors
{
    public function handle(Request $request, Closure $next)
    {
        $headers = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Authorization, Content-Type, Accept, Mcp-Session-Id, Mcp-Protocol-Version',
            'Access-Control-Expose-Headers' => 'WWW-Authenticate, Mcp-Session-Id',
            'Access-Control-Max-Age' => '600',
        ];

        $response = $request->isMethod('OPTIONS') ? new Response('', 204) : $next($request);
        foreach ($headers as $k => $v) {
            $response->headers->set($k, $v);
        }

        return $response;
    }
}
