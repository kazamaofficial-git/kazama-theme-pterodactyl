<?php

namespace Pterodactyl\Kazama\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Pterodactyl\Kazama\Support\Settings;
use Symfony\Component\Process\Process;

class PanelBackupService
{
    public const NAME_REGEX = '/^panel-backup-[0-9_\-]+\.tar\.gz$/';

    public function dir(): string
    {
        $dir = storage_path('app/kazama-backups');
        File::ensureDirectoryExists($dir, 0750);

        return $dir;
    }

    public function list(): array
    {
        $files = [];
        foreach (File::files($this->dir()) as $file) {
            if (preg_match(self::NAME_REGEX, $file->getFilename())) {
                $files[] = ['name' => $file->getFilename(), 'size' => $file->getSize(), 'mtime' => $file->getMTime()];
            }
        }
        usort($files, fn ($a, $b) => $b['mtime'] <=> $a['mtime']);

        return $files;
    }

    public function path(string $name): string
    {
        if (!preg_match(self::NAME_REGEX, $name)) {
            abort(404);
        }
        $path = $this->dir() . '/' . $name;
        abort_unless(is_file($path), 404);

        return $path;
    }

    /** Buat arsip: dump database MySQL + .env (kunci enkripsi). Nama file memakai waktu WIB. */
    public function create(bool $notify = true): string
    {
        $name = 'panel-backup-' . now(config('kazama.timezone'))->format('Y-m-d_H-i-s') . '.tar.gz';
        $dest = $this->dir() . '/' . $name;
        $tmp = storage_path('app/kazama-tmp/' . Str::random(16));
        File::ensureDirectoryExists($tmp, 0700);

        try {
            $db = config('database.connections.' . config('database.default'));
            $dump = new Process([
                config('kazama.backup.mysqldump'), '--single-transaction', '--quick', '--no-tablespaces',
                '-h', (string) $db['host'], '-P', (string) $db['port'], '-u', (string) $db['username'],
                '--result-file=' . $tmp . '/database.sql', (string) $db['database'],
            ], null, ['MYSQL_PWD' => (string) $db['password']], null, 600);
            $dump->mustRun();

            File::copy(base_path('.env'), $tmp . '/.env');
            (new Process(['tar', '-czf', $dest, '-C', $tmp, 'database.sql', '.env']))->setTimeout(600)->mustRun();
            @chmod($dest, 0640);
        } finally {
            File::deleteDirectory($tmp);
        }

        $this->prune();
        $this->ship($dest, $name, $notify);

        return $name;
    }

    private function prune(): void
    {
        $keep = Settings::int('panel_auto_keep', (int) config('kazama.backup.keep', 14));
        foreach (array_slice($this->list(), max(1, $keep)) as $old) {
            @unlink($this->dir() . '/' . $old['name']);
        }
    }

    /** Kirim salinan ke Telegram Bot dan/atau disk cloud (opsional). */
    private function ship(string $path, string $name, bool $notify): void
    {
        if ($disk = config('kazama.backup.disk')) {
            try {
                Storage::disk($disk)->put('kazama-panel-backups/' . $name, fopen($path, 'r'));
            } catch (\Throwable $e) {
                Log::error('[kazama:backup] cloud upload gagal: ' . $e->getMessage());
            }
        }

        $token = Settings::get('telegram_bot_token');
        $chat = Settings::get('telegram_chat_id');
        if (!$notify || !$token || !$chat || !Settings::bool('telegram_enabled')) {
            return;
        }

        try {
            $caption = "Backup panel {$name}\n" . now(config('kazama.timezone'))->format('d M Y H:i') . ' WIB';
            if (filesize($path) > 49 * 1024 * 1024) {
                Http::post("https://api.telegram.org/bot{$token}/sendMessage", ['chat_id' => $chat, 'text' => $caption . "\n(File >49MB, tidak dikirim lewat Telegram)"]);

                return;
            }
            Http::timeout(120)->attach('document', fopen($path, 'r'), $name)
                ->post("https://api.telegram.org/bot{$token}/sendDocument", ['chat_id' => $chat, 'caption' => $caption]);
        } catch (\Throwable $e) {
            Log::error('[kazama:backup] telegram gagal: ' . $e->getMessage());
        }
    }

    /** Restore dari arsip: impor database.sql dan kembalikan .env (APP_KEY lama). */
    public function restore(string $archive): void
    {
        $list = new Process(['tar', '-tzf', $archive]);
        $list->mustRun();
        $entries = array_filter(explode("\n", trim($list->getOutput())));
        foreach ($entries as $entry) {
            if (str_starts_with($entry, '/') || str_contains($entry, '..')) {
                throw new \RuntimeException('Arsip tidak valid (path berbahaya).');
            }
        }
        if (!in_array('database.sql', array_map(fn ($e) => ltrim($e, './'), $entries), true)) {
            throw new \RuntimeException('Arsip tidak berisi database.sql.');
        }

        $tmp = storage_path('app/kazama-tmp/' . Str::random(16));
        File::ensureDirectoryExists($tmp, 0700);

        try {
            (new Process(['tar', '-xzf', $archive, '-C', $tmp, '--no-same-owner']))->setTimeout(600)->mustRun();

            $db = config('database.connections.' . config('database.default'));
            $import = Process::fromShellCommandline(
                escapeshellarg(config('kazama.backup.mysql')) . ' -h ' . escapeshellarg((string) $db['host']) . ' -P ' . escapeshellarg((string) $db['port'])
                . ' -u ' . escapeshellarg((string) $db['username']) . ' ' . escapeshellarg((string) $db['database']) . ' < ' . escapeshellarg($tmp . '/database.sql'),
                null, ['MYSQL_PWD' => (string) $db['password']], null, 1800
            );
            $import->mustRun();

            if (is_file($tmp . '/.env')) {
                File::copy(base_path('.env'), base_path('.env.pre-restore'));
                File::copy($tmp . '/.env', base_path('.env'));
            }
        } finally {
            File::deleteDirectory($tmp);
        }

        Artisan::call('config:clear');
        Artisan::call('cache:clear');
    }
}
