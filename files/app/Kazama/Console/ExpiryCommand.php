<?php

namespace Pterodactyl\Kazama\Console;

use Illuminate\Console\Command;
use Pterodactyl\Kazama\Services\ExpiryService;

class ExpiryCommand extends Command
{
    protected $signature = 'kazama:expiry';
    protected $description = 'Kazama: suspend server kedaluwarsa, hapus setelah masa tenggang, kirim peringatan.';

    public function handle(ExpiryService $service): int
    {
        $s = $service->run();
        $this->info("Peringatan: {$s['warned']}, disuspend: {$s['suspended']}, dihapus: {$s['deleted']}");

        return 0;
    }
}
