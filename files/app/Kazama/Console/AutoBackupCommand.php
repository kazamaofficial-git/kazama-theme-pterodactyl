<?php

namespace Pterodactyl\Kazama\Console;

use Illuminate\Console\Command;
use Pterodactyl\Kazama\Services\AutoBackupRunner;

class AutoBackupCommand extends Command
{
    protected $signature = 'kazama:auto-backup';
    protected $description = 'Kazama: jalankan backup otomatis server yang jadwalnya sudah tiba (WIB).';

    public function handle(AutoBackupRunner $runner): int
    {
        $this->info('Backup dijalankan: ' . $runner->run());

        return 0;
    }
}
