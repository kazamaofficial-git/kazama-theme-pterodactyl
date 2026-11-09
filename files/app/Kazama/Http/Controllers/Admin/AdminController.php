<?php

namespace Pterodactyl\Kazama\Http\Controllers\Admin;

use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Prologue\Alerts\Facades\Alert;
use Pterodactyl\Http\Controllers\Admin\ServersController;
use Pterodactyl\Http\Controllers\Admin\Settings\IndexController as SettingsIndexController;
use Pterodactyl\Kazama\Services\ExpiryService;
use Pterodactyl\Kazama\Services\Notifier;
use Pterodactyl\Kazama\Support\Restrict;
use Pterodactyl\Kazama\Support\Settings;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;

class AdminController extends Controller
{
    public function __construct(private ExpiryService $expiry)
    {
    }

    private function superOnly(Request $request): void
    {
        abort_unless(Restrict::isSuper($request->user()), 403, Restrict::name() . ': hanya admin utama (ID 1).');
    }

    /* ---------- Route lama: PATCH /admin/servers/view/{id}/details (Masa Aktif ada di halaman server) ---------- */

    public function serverDetails(Request $request, Server $server)
    {
        if ($request->input('kazama_form') !== 'expiry') {
            return app()->call([app(ServersController::class), 'setDetails'], ['request' => $request, 'server' => $server]);
        }

        $request->validate([
            'kazama_expires_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'kazama_extend_days' => ['nullable', 'integer', 'between:1,365'],
        ]);

        if ($request->filled('kazama_expires_at')) {
            $new = Carbon::createFromFormat('Y-m-d\TH:i', $request->input('kazama_expires_at'), config('kazama.timezone'))->utc();
            $this->expiry->set($server, $new);
        } elseif ($request->filled('kazama_extend_days')) {
            $this->expiry->extend($server, (int) $request->input('kazama_extend_days'));
        } else {
            Alert::warning('Isi tanggal berakhir atau jumlah hari perpanjangan.')->flash();

            return redirect()->route('admin.servers.view.details', $server->id);
        }

        Alert::success('Masa aktif server diperbarui.')->flash();

        return redirect()->route('admin.servers.view.details', $server->id);
    }

    /* ---------- Route lama: PATCH /admin/settings (Pengaturan Kazama ada di halaman Settings) ---------- */

    public function settings(Request $request)
    {
        if ($request->input('kazama_form') !== 'settings') {
            return app()->call([app(SettingsIndexController::class), 'update']);
        }
        $this->superOnly($request);

        $data = $request->validate([
            'protect_name' => ['required', 'string', 'max:60'],
            'mcp_default_name' => ['required', 'string', 'max:60'],
            'contact_telegram' => ['nullable', 'url', 'max:200'],
            'contact_whatsapp' => ['nullable', 'url', 'max:200'],
            'active_days' => ['required', 'integer', 'between:1,3650'],
            'grace_days' => ['required', 'integer', 'between:0,60'],
            'oauth_google_id' => ['nullable', 'string', 'max:200'],
            'oauth_google_secret' => ['nullable', 'string', 'max:200'],
            'oauth_github_id' => ['nullable', 'string', 'max:200'],
            'oauth_github_secret' => ['nullable', 'string', 'max:200'],
        ]);

        foreach (['protect_name', 'mcp_default_name', 'contact_telegram', 'contact_whatsapp', 'active_days', 'grace_days', 'oauth_google_id', 'oauth_github_id'] as $k) {
            Settings::set($k, $data[$k] ?? '');
        }
        foreach (['oauth_google_secret', 'oauth_github_secret'] as $k) {
            if (!empty($data[$k])) {
                Settings::setSecret($k, $data[$k]);
            }
        }
        foreach (['registration_enabled', 'oauth_google_enabled', 'oauth_github_enabled'] as $k) {
            Settings::set($k, $request->boolean($k) ? '1' : '0');
        }

        Alert::success('Pengaturan Kazama disimpan.')->flash();

        return redirect()->route('admin.settings');
    }

    /* ---------- Notifikasi ---------- */

    public function notifications(Request $request): View
    {
        $this->superOnly($request);

        return view('kazama::admin.notifications', ['items' => DB::table('kazama_notifications')->orderByDesc('id')->limit(50)->get()]);
    }

    public function sendNotification(Request $request): RedirectResponse
    {
        $this->superOnly($request);
        $data = $request->validate([
            'target' => ['required', 'in:all,email'],
            'email' => ['required_if:target,email', 'nullable', 'email'],
            'type' => ['required', 'in:info,success,warning,danger'],
            'title' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $userId = null;
        if ($data['target'] === 'email') {
            $userId = User::withoutGlobalScopes()->where('email', $data['email'])->value('id');
            if (!$userId) {
                Alert::danger('User dengan email tersebut tidak ditemukan.')->flash();

                return redirect()->route('kazama.admin.notifications');
            }
        }

        Notifier::send($userId, $data['title'], $data['message'], $data['type']);
        \Pterodactyl\Kazama\Support\KLog::write('notify.send', ['target' => $data['target'] === 'all' ? 'Semua user' : $data['email'], 'detail' => $data['title']]);
        Alert::success('Notifikasi terkirim.')->flash();

        return redirect()->route('kazama.admin.notifications');
    }

    public function deleteNotification(Request $request, int $id): RedirectResponse
    {
        $this->superOnly($request);
        DB::table('kazama_notifications')->where('id', $id)->delete();
        DB::table('kazama_notification_reads')->where('notification_id', $id)->delete();

        return redirect()->route('kazama.admin.notifications');
    }

    /* ---------- Logs (khusus admin ID 1) ---------- */

    public function logs(Request $request): View
    {
        $this->superOnly($request);

        $q = DB::table('kazama_logs')->orderByDesc('id');
        if ($s = $request->query('source')) {
            $q->where('source', $s);
        }
        if ($a = trim((string) $request->query('actor'))) {
            $q->where('actor_name', 'like', '%' . $a . '%');
        }
        if ($t = trim((string) $request->query('q'))) {
            $q->where(function ($w) use ($t) {
                $w->where('action', 'like', "%{$t}%")->orWhere('server_name', 'like', "%{$t}%")->orWhere('target', 'like', "%{$t}%")->orWhere('detail', 'like', "%{$t}%");
            });
        }

        return view('kazama::admin.logs', ['logs' => $q->paginate(50)->withQueryString()]);
    }
}
