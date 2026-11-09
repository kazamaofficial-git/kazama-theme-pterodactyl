<?php

namespace Pterodactyl\Kazama\Console;

use Illuminate\Console\Command;
use Pterodactyl\Kazama\Mcp\Connections;

class McpPruneCommand extends Command
{
    protected $signature = 'kazama:mcp-prune';
    protected $description = 'Kazama: putuskan koneksi MCP yang melewati batas "Putuskan otomatis".';

    public function handle(): int
    {
        $this->info('Koneksi diputuskan: ' . Connections::pruneExpired());

        return 0;
    }
}
