<?php

namespace Pterodactyl\Kazama\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Prologue\Alerts\Facades\Alert;
use Pterodactyl\Kazama\Services\PanelBackupService;
use Pterodactyl\Kazama\Support\Cadence;
use Pterodactyl\Kazama\Support\Restrict;
use Pterodactyl\Kazama\Support\Settings;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PanelBackupController extends Controller
{
    public function __construct(private PanelBackupService $service)
    {
    }

    private function superOnly(Request $request): void
    {
        abort_unless(Restrict::isSuper($request->user()), 403);
    }

    public function index(Request $request): View
    {
        $this->superOnly($request);

        return view('kazama::admin.backup', [
            'files' => $this->service->list(),
            's' => [
                'auto' => Settings::bool('panel_auto_enabled'),
                'frequency' => Settings::get('panel_auto_frequency', 'daily'),
                'time' => Settings::get('panel_auto_time', '00:00'),
                'weekday' => (int) Settings::get('panel_auto_weekday', 1),
                'keep' => Settings::int('panel_auto_keep', (int) config('kazama.backup.keep')),
                'tg_enabled' => Settings::bool('telegram_enabled'),
                'tg_token' => Settings::get('telegram_bot_token') ? '********' : '',
                'tg_chat' => Settings::get('telegram_chat_id', ''),
                'next' => Settings::get('panel_auto_next') ? Cadence::wib(Settings::get('panel_auto_next')) : null,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->superOnly($request);
        try {
            $name = $this->service->create();
            Alert::success("Backup {$name} berhasil dibuat.")->flash();
        } catch (\Throwable $e) {
            Alert::danger('Backup gagal: ' . $e->getMessage())->flash();
        }

        return redirect()->route('kazama.admin.backup');
    }

    public function download(Request $request, string $name): BinaryFileResponse
    {
        $this->superOnly($request);

        return response()->download($this->service->path($name));
    }

    public function destroy(Request $request, string $name): RedirectResponse
    {
        $this->superOnly($request);
        @unlink($this->service->path($name));
        Alert::success('Backup dihapus.')->flash();

        return redirect()->route('kazama.admin.backup');
    }

    public function restore(Request $request): RedirectResponse
    {
        $this->superOnly($request);
        $request->validate(['confirm' => ['required', 'in:RESTORE']], ['confirm.in' => 'Ketik RESTORE untuk konfirmasi.']);

        try {
            if ($request->hasFile('archive')) {
                $file = $request->file('archive');
                if (!str_ends_with(strtolower($file->getClientOriginalName()), '.tar.gz')) {
                    throw new \RuntimeException('File harus berformat .tar.gz');
                }
                $path = $file->getRealPath();
            } else {
                $path = $this->service->path((string) $request->input('name'));
            }
            $this->service->restore($path);
            Alert::success('Restore selesai. Silakan login ulang jika diminta.')->flash();
        } catch (\Throwable $e) {
            Alert::danger('Restore gagal: ' . $e->getMessage())->flash();
        }

        return redirect()->route('kazama.admin.backup');
    }

    public function settings(Request $request): RedirectResponse
    {
        $this->superOnly($request);
        $data = $request->validate([
            'frequency' => ['required', 'in:hourly,daily,weekly'],
            'time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'weekday' => ['required', 'integer', 'between:0,6'],
            'keep' => ['required', 'integer', 'between:1,60'],
            'telegram_chat_id' => ['nullable', 'string', 'max:64'],
            'telegram_bot_token' => ['nullable', 'string', 'max:128'],
        ]);

        Settings::set('panel_auto_enabled', $request->boolean('auto') ? '1' : '0');
        Settings::set('panel_auto_frequency', $data['frequency']);
        Settings::set('panel_auto_time', $data['time']);
        Settings::set('panel_auto_weekday', $data['weekday']);
        Settings::set('panel_auto_keep', $data['keep']);
        Settings::set('telegram_enabled', $request->boolean('telegram_enabled') ? '1' : '0');
        Settings::set('telegram_chat_id', $data['telegram_chat_id'] ?? '');
        if (!empty($data['telegram_bot_token']) && $data['telegram_bot_token'] !== '********') {
            Settings::set('telegram_bot_token', $data['telegram_bot_token']);
        }
        Settings::set('panel_auto_next', $request->boolean('auto')
            ? Cadence::next($data['frequency'], $data['time'], (int) $data['weekday'])->format('Y-m-d H:i:s')
            : '');

        Alert::success('Pengaturan auto-backup disimpan (waktu dalam WIB).')->flash();

        return redirect()->route('kazama.admin.backup');
    }
}
