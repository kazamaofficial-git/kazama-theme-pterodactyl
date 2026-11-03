<?php

namespace Pterodactyl\Kazama\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Login hanya boleh memakai email + password (username tidak didukung). */
class EmailLoginOnly
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('POST') && $request->is('auth/login')) {
            $identity = trim((string) $request->input('user'));
            if (!filter_var($identity, FILTER_VALIDATE_EMAIL)) {
                return response()->json(['errors' => [[
                    'code' => 'InvalidCredentials',
                    'status' => '422',
                    'detail' => 'Login hanya menggunakan email dan password. Username tidak didukung.',
                ]]], 422);
            }
        }

        return $next($request);
    }
}
