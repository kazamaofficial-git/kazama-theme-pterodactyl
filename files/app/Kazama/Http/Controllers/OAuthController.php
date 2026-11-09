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
use Pterodactyl\Kazama\Support\KLog;
use Pterodactyl\Kazama\Support\OAuth;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Users\UserCreationService;

/** Login & daftar via Google / GitHub (bisa dinonaktifkan dari Admin > Settings). */
class OAuthController extends Controller
{
    public function __construct(private UserCreationService $creation)
    {
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect('/auth/login?kz_error=' . urlencode($message));
    }

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        if (!OAuth::enabled($provider)) {
            return $this->fail('Login ' . ucfirst($provider) . ' sedang dinonaktifkan.');
        }

        [$id] = OAuth::credentials($provider);
        $state = Str::random(40);
        $request->session()->put('kazama_oauth_state', $state);

        $url = $provider === 'google'
            ? 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
                'client_id' => $id, 'redirect_uri' => OAuth::redirectUri('google'), 'response_type' => 'code',
                'scope' => 'openid email profile', 'state' => $state, 'prompt' => 'select_account',
            ])
            : 'https://github.com/login/oauth/authorize?' . http_build_query([
                'client_id' => $id, 'redirect_uri' => OAuth::redirectUri('github'),
                'scope' => 'read:user user:email', 'state' => $state, 'allow_signup' => 'true',
            ]);

        return redirect($url);
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        if (!OAuth::enabled($provider)) {
            return $this->fail('Login ' . ucfirst($provider) . ' sedang dinonaktifkan.');
        }

        $expected = $request->session()->pull('kazama_oauth_state');
        if ($request->query('error') || !$expected || !hash_equals($expected, (string) $request->query('state')) || !$request->query('code')) {
            return $this->fail('Login dibatalkan atau tidak valid.');
        }

        try {
            $profile = $provider === 'google' ? $this->google($request) : $this->github($request);
        } catch (\Throwable $e) {
            Log::error("[kazama:{$provider}] " . $e->getMessage());

            return $this->fail('Gagal menghubungi ' . ucfirst($provider) . '. Coba lagi.');
        }

        if (!$profile['email'] || !$profile['verified']) {
            return $this->fail('Email ' . ucfirst($provider) . ' belum terverifikasi.');
        }

        $col = $provider . '_id';
        $user = User::withoutGlobalScopes()->where($col, $profile['id'])->first()
            ?? User::withoutGlobalScopes()->where('email', $profile['email'])->first();

        if (!$user) {
            if (!OAuth::registrationOpen()) {
                return $this->fail('Pendaftaran akun baru sedang ditutup.');
            }
            $user = $this->creation->handle([
                'email' => $profile['email'],
                'username' => $this->makeUsername($profile['email'], $profile['login'] ?? null),
                'name_first' => Str::limit($profile['first'] ?: Str::before($profile['email'], '@'), 40, ''),
                'name_last' => Str::limit($profile['last'] ?: 'User', 40, ''),
                'password' => Str::random(40),
                'root_admin' => false,
                'language' => 'id',
            ]);
            KLog::write('auth.register', ['actor' => ['id' => $user->id, 'name' => $user->username, 'source' => 'user', 'client' => null, 'conn' => null], 'detail' => "via {$provider}"]);
        }

        if ($user->use_totp) {
            return $this->fail('Akun ini memakai 2FA. Silakan login dengan email dan password.');
        }

        DB::table('users')->where('id', $user->id)->update([$col => $profile['id']]);

        Auth::guard()->login($user, true);
        $request->session()->regenerate();

        return redirect('/');
    }

    private function google(Request $request): array
    {
        [$id, $secret] = OAuth::credentials('google');
        $http = new Client(['timeout' => 15]);
        $token = json_decode((string) $http->post('https://oauth2.googleapis.com/token', ['form_params' => [
            'code' => $request->query('code'), 'client_id' => $id, 'client_secret' => $secret,
            'redirect_uri' => OAuth::redirectUri('google'), 'grant_type' => 'authorization_code',
        ]])->getBody(), true);
        $p = json_decode((string) $http->get('https://openidconnect.googleapis.com/v1/userinfo', [
            'headers' => ['Authorization' => 'Bearer ' . $token['access_token']],
        ])->getBody(), true);

        return [
            'id' => (string) ($p['sub'] ?? ''), 'email' => $p['email'] ?? null, 'verified' => !empty($p['email_verified']),
            'first' => $p['given_name'] ?? '', 'last' => $p['family_name'] ?? '', 'login' => null,
        ];
    }

    private function github(Request $request): array
    {
        [$id, $secret] = OAuth::credentials('github');
        $http = new Client(['timeout' => 15, 'headers' => ['Accept' => 'application/json', 'User-Agent' => 'KazamaCloud']]);
        $token = json_decode((string) $http->post('https://github.com/login/oauth/access_token', ['form_params' => [
            'code' => $request->query('code'), 'client_id' => $id, 'client_secret' => $secret, 'redirect_uri' => OAuth::redirectUri('github'),
        ]])->getBody(), true);
        $auth = ['Authorization' => 'Bearer ' . ($token['access_token'] ?? '')];
        $u = json_decode((string) $http->get('https://api.github.com/user', ['headers' => $auth])->getBody(), true);
        $emails = json_decode((string) $http->get('https://api.github.com/user/emails', ['headers' => $auth])->getBody(), true) ?: [];

        $email = null;
        foreach ($emails as $e) {
            if (!empty($e['primary']) && !empty($e['verified'])) {
                $email = $e['email'];
            }
        }
        $parts = preg_split('/\s+/', trim((string) ($u['name'] ?? $u['login'] ?? '')), 2);

        return [
            'id' => (string) ($u['id'] ?? ''), 'email' => $email, 'verified' => $email !== null,
            'first' => $parts[0] ?? '', 'last' => $parts[1] ?? '', 'login' => $u['login'] ?? null,
        ];
    }

    private function makeUsername(string $email, ?string $login): string
    {
        $base = preg_replace('/[^a-z0-9]/', '', strtolower($login ?: Str::before($email, '@'))) ?: 'user';
        $base = substr(str_pad($base, 3, '0'), 0, 20);
        $name = $base;
        while (User::withoutGlobalScopes()->where('username', $name)->exists()) {
            $name = $base . random_int(100, 99999);
        }

        return $name;
    }
}
