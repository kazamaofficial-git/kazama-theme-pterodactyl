<?php

namespace Pterodactyl\Kazama\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Users\UserCreationService;

class GoogleAuthController extends Controller
{
    public function __construct(private UserCreationService $creation)
    {
    }

    private function redirectUri(): string
    {
        return config('kazama.google.redirect') ?: rtrim(config('app.url'), '/') . '/auth/google/callback';
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect('/auth/login?kz_error=' . urlencode($message));
    }

    public function redirect(Request $request): RedirectResponse
    {
        if (!config('kazama.google.client_id') || !config('kazama.google.client_secret')) {
            return $this->fail('Login Google belum dikonfigurasi oleh admin.');
        }

        $state = Str::random(40);
        $request->session()->put('kazama_oauth_state', $state);

        return redirect('https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => config('kazama.google.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]));
    }

    public function callback(Request $request): RedirectResponse
    {
        $expected = $request->session()->pull('kazama_oauth_state');
        if ($request->query('error') || !$expected || !hash_equals($expected, (string) $request->query('state')) || !$request->query('code')) {
            return $this->fail('Login Google dibatalkan atau tidak valid.');
        }

        try {
            $http = new Client(['timeout' => 15]);
            $token = json_decode((string) $http->post('https://oauth2.googleapis.com/token', ['form_params' => [
                'code' => $request->query('code'),
                'client_id' => config('kazama.google.client_id'),
                'client_secret' => config('kazama.google.client_secret'),
                'redirect_uri' => $this->redirectUri(),
                'grant_type' => 'authorization_code',
            ]])->getBody(), true);

            $profile = json_decode((string) $http->get('https://openidconnect.googleapis.com/v1/userinfo', [
                'headers' => ['Authorization' => 'Bearer ' . $token['access_token']],
            ])->getBody(), true);
        } catch (\Throwable $e) {
            Log::error('[kazama:google] ' . $e->getMessage());

            return $this->fail('Gagal menghubungi Google. Coba lagi.');
        }

        if (empty($profile['sub']) || empty($profile['email']) || empty($profile['email_verified'])) {
            return $this->fail('Email Google belum terverifikasi.');
        }

        $user = User::withoutGlobalScopes()->where('google_id', $profile['sub'])->first()
            ?? User::withoutGlobalScopes()->where('email', $profile['email'])->first();

        if (!$user) {
            if (!config('kazama.registration')) {
                return $this->fail('Pendaftaran akun baru sedang ditutup.');
            }
            $user = $this->creation->handle([
                'email' => $profile['email'],
                'username' => $this->makeUsername($profile['email']),
                'name_first' => Str::limit($profile['given_name'] ?? Str::before($profile['email'], '@'), 40, ''),
                'name_last' => Str::limit($profile['family_name'] ?? 'User', 40, ''),
                'password' => Str::random(40),
                'root_admin' => false,
            ]);
        }

        if ($user->use_totp) {
            return $this->fail('Akun ini memakai 2FA. Silakan login dengan email dan password.');
        }

        DB::table('users')->where('id', $user->id)->update(['google_id' => $profile['sub']]);

        Auth::guard()->login($user, true);
        $request->session()->regenerate();

        return redirect('/');
    }

    private function makeUsername(string $email): string
    {
        $base = preg_replace('/[^a-z0-9]/', '', strtolower(Str::before($email, '@'))) ?: 'user';
        $base = substr(str_pad($base, 3, '0'), 0, 20);
        $name = $base;
        while (User::withoutGlobalScopes()->where('username', $name)->exists()) {
            $name = $base . random_int(100, 99999);
        }

        return $name;
    }
}
