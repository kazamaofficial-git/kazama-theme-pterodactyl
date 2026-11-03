<?php

namespace Pterodactyl\Kazama\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Users\UserCreationService;

class RegisterController extends Controller
{
    public function __construct(private UserCreationService $creation)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (!config('kazama.registration')) {
            return $this->error('Pendaftaran akun baru sedang ditutup.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
        ], [
            'email.unique' => 'Email sudah terdaftar. Silakan login.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.min' => 'Password minimal 8 karakter.',
        ]);

        $base = substr(str_pad(preg_replace('/[^a-z0-9]/', '', strtolower(Str::before($data['email'], '@'))) ?: 'user', 3, '0'), 0, 20);
        $username = $base;
        while (User::withoutGlobalScopes()->where('username', $username)->exists()) {
            $username = $base . random_int(100, 99999);
        }

        $parts = preg_split('/\s+/', trim($data['name']), 2);
        $user = $this->creation->handle([
            'email' => $data['email'],
            'username' => $username,
            'name_first' => $parts[0],
            'name_last' => $parts[1] ?? 'User',
            'password' => $data['password'],
            'root_admin' => false,
        ]);

        Auth::guard()->login($user, true);
        $request->session()->regenerate();

        return new JsonResponse(['complete' => true, 'intended' => '/']);
    }

    private function error(string $detail): JsonResponse
    {
        return new JsonResponse(['errors' => [['code' => 'RegistrationClosed', 'status' => '403', 'detail' => $detail]]], 403);
    }
}
