<?php

namespace Pterodactyl\Kazama\Console;

use Illuminate\Console\Command;
use Pterodactyl\Kazama\Services\TrashService;

class TrashPurgeCommand extends Command
{
    protected $signature = 'kazama:trash-purge';
    protected $description = 'Kazama: hapus permanen isi Trash server yang sudah lebih dari 7 hari.';

    public function handle(TrashService $trash): int
    {
        $this->info('Dihapus permanen: ' . $trash->purgeExpired());

        return 0;
    }
}
