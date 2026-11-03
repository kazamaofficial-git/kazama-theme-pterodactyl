<?php

namespace Pterodactyl\Kazama\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Prologue\Alerts\Facades\Alert;
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
        abort_unless(Restrict::isSuper($request->user()), 403);
    }

    /* ---------- Masa aktif ---------- */

    public function expiry(Request $request): View
    {
        // Global scope otomatis membatasi admin terbatas ke server buatannya sendiri.
        $servers = Server::query()->with('user')->orderByRaw('kazama_expires_at IS NULL, kazama_expires_at ASC')->paginate(25);

        return view('kazama::admin.expiry', ['servers' => $servers]);
    }

    public function extend(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['days' => ['required', 'integer', 'between:1,365']]);
        $server = Server::query()->findOrFail($id);
        $new = $this->expiry->extend($server, (int) $data['days']);
        Alert::success("Masa aktif {$server->name} diperpanjang hingga " . \Pterodactyl\Kazama\Support\Cadence::wib($new) . '.')->flash();

        return redirect()->route('kazama.admin.expiry');
    }

    /* ---------- Notifikasi ---------- */

    public function notifications(Request $request): View
    {
        $this->superOnly($request);

        return view('kazama::admin.notifications', [
            'items' => DB::table('kazama_notifications')->orderByDesc('id')->limit(50)->get(),
        ]);
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

    /* ---------- Pengaturan ---------- */

    public function settings(Request $request): View
    {
        $this->superOnly($request);

        return view('kazama::admin.settings', [
            's' => [
                'telegram' => Settings::get('contact_telegram', config('kazama.contact.telegram')),
                'whatsapp' => Settings::get('contact_whatsapp', config('kazama.contact.whatsapp')),
                'active_days' => Settings::int('active_days', (int) config('kazama.server.active_days')),
                'grace_days' => Settings::int('grace_days', (int) config('kazama.server.grace_days')),
            ],
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $this->superOnly($request);
        $data = $request->validate([
            'contact_telegram' => ['nullable', 'url', 'max:200'],
            'contact_whatsapp' => ['nullable', 'url', 'max:200'],
            'active_days' => ['required', 'integer', 'between:1,3650'],
            'grace_days' => ['required', 'integer', 'between:0,60'],
        ]);
        foreach ($data as $k => $v) {
            Settings::set($k, $v ?? '');
        }
        Alert::success('Pengaturan disimpan.')->flash();

        return redirect()->route('kazama.admin.settings');
    }
}
