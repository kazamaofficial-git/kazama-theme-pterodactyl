<?php

namespace Pterodactyl\Kazama\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Pterodactyl\Kazama\Services\PanelBackupService;
use Pterodactyl\Kazama\Support\Cadence;
use Pterodactyl\Kazama\Support\Settings;

class PanelBackupCommand extends Command
{
    protected $signature = 'kazama:panel-backup {--auto : hanya jalan jika jadwal auto-backup panel sudah tiba}';
    protected $description = 'Kazama: backup database + .env panel (tar.gz).';

    public function handle(PanelBackupService $service): int
    {
        if ($this->option('auto')) {
            $next = Settings::get('panel_auto_next');
            if (!Settings::bool('panel_auto_enabled') || !$next || Carbon::parse($next, 'UTC')->isFuture()) {
                return 0;
            }
            Settings::set('panel_auto_next', Cadence::next(
                Settings::get('panel_auto_frequency', 'daily'),
                Settings::get('panel_auto_time', '00:00'),
                (int) Settings::get('panel_auto_weekday', 1)
            )->format('Y-m-d H:i:s'));
        }

        $this->info('Backup dibuat: ' . $service->create());

        return 0;
    }
}
