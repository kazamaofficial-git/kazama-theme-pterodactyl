<?php

namespace Pterodactyl\Kazama\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Pterodactyl\Kazama\Support\Actor;
use Pterodactyl\Kazama\Support\KLog;
use Pterodactyl\Models\Server;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;

/** Tempat sampah server: file yang "dihapus" dipindah ke /.kazama-trash dan dihapus permanen setelah 7 hari. */
class TrashService
{
    public const DIR = '.kazama-trash';
    public const DAYS = 7;

    public function __construct(private DaemonFileRepository $files)
    {
    }

    public static function safe(string $path): bool
    {
        foreach (explode('/', str_replace('\\', '/', $path)) as $seg) {
            if ($seg === '..') {
                return false;
            }
        }

        return true;
    }

    /** Apakah penghapusan ini harus permanen (sedang membersihkan isi trash itu sendiri)? */
    public function isPermanent(string $root, array $files): bool
    {
        $root = trim($root, '/');
        if ($root === self::DIR || str_starts_with($root, self::DIR . '/')) {
            return true;
        }
        foreach ($files as $f) {
            if (trim($f, '/') === self::DIR) {
                return true;
            }
        }

        return false;
    }

    public function trash(Server $server, string $root, array $files): int
    {
        $repo = $this->files->setServer($server);
        $rootRel = trim($root, '/');
        $by = Actor::name();

        $meta = [];
        try {
            foreach ($repo->getDirectory('/' . $rootRel) as $entry) {
                $meta[$entry['name']] = $entry;
            }
        } catch (\Throwable) {
        }

        $dirName = now('UTC')->format('YmdHis') . '-' . Str::lower(Str::random(5));
        $trashRel = self::DIR . '/' . $dirName;
        try {
            $repo->createDirectory(self::DIR, '/');
        } catch (\Throwable) {
        }
        $repo->createDirectory($dirName, '/' . self::DIR);

        $moves = [];
        $rows = [];
        $expires = now('UTC')->addDays(self::DAYS)->format('Y-m-d H:i:s');
        foreach ($files as $f) {
            $name = basename(trim($f, '/'));
            $from = ltrim($rootRel . '/' . ltrim($f, '/'), '/');
            $moves[] = ['from' => $from, 'to' => $trashRel . '/' . $name];
            $entry = $meta[$name] ?? [];
            $rows[] = [
                'server_id' => $server->id, 'trash_dir' => $trashRel, 'name' => $name, 'original_path' => '/' . $from,
                'is_dir' => !empty($entry['directory']), 'size' => (int) ($entry['size'] ?? 0),
                'deleted_by' => mb_substr($by, 0, 120), 'deleted_at' => now('UTC')->format('Y-m-d H:i:s'), 'expires_at' => $expires,
            ];
        }

        $repo->renameFiles('/', $moves);
        DB::table('kazama_trash')->insert($rows);
        KLog::write('file.trash', ['server' => $server, 'target' => '/' . ltrim($rootRel . '/', '/'), 'detail' => implode(', ', array_map(fn ($m) => $m['from'], $moves))]);

        return count($rows);
    }

    public function list(Server $server): array
    {
        return DB::table('kazama_trash')->where('server_id', $server->id)->orderByDesc('id')->get()->all();
    }

    public function restore(Server $server, int $id): string
    {
        $row = DB::table('kazama_trash')->where('server_id', $server->id)->where('id', $id)->first();
        abort_unless($row, 404);
        $repo = $this->files->setServer($server);

        $target = ltrim($row->original_path, '/');
        $parent = trim(dirname($target), './');
        $name = basename($target);

        $siblings = null;
        try {
            $siblings = array_column($repo->getDirectory('/' . $parent), 'name');
        } catch (\Throwable) {
            $parent = '';
            try {
                $siblings = array_column($repo->getDirectory('/'), 'name');
            } catch (\Throwable) {
                $siblings = [];
            }
        }
        if (in_array($name, $siblings, true)) {
            $name = preg_replace('/(\.[^.]+)?$/', '-dipulihkan-' . now(config('kazama.timezone'))->format('His') . '$1', $name, 1);
        }
        $to = ltrim($parent . '/' . $name, '/');

        $repo->renameFiles('/', [['from' => $row->trash_dir . '/' . $row->name, 'to' => $to]]);
        DB::table('kazama_trash')->where('id', $id)->delete();
        $this->cleanDir($repo, $row->trash_dir);
        KLog::write('file.restore', ['server' => $server, 'target' => '/' . $to]);

        return '/' . $to;
    }

    public function purge(Server $server, int $id): void
    {
        $row = DB::table('kazama_trash')->where('server_id', $server->id)->where('id', $id)->first();
        abort_unless($row, 404);
        $this->purgeRow($server, $row);
        KLog::write('file.purge', ['server' => $server, 'target' => $row->original_path]);
    }

    public function purgeRow(Server $server, object $row): void
    {
        $repo = $this->files->setServer($server);
        $repo->deleteFiles('/', [$row->trash_dir . '/' . $row->name]);
        DB::table('kazama_trash')->where('id', $row->id)->delete();
        $this->cleanDir($repo, $row->trash_dir);
    }

    private function cleanDir(DaemonFileRepository $repo, string $dir): void
    {
        if (!DB::table('kazama_trash')->where('trash_dir', $dir)->exists()) {
            try {
                $repo->deleteFiles('/', [$dir]);
            } catch (\Throwable) {
            }
        }
    }

    /** Dipanggil scheduler: hapus permanen entri yang melewati 7 hari. */
    public function purgeExpired(): int
    {
        $n = 0;
        $rows = DB::table('kazama_trash')->where('expires_at', '<', now('UTC')->format('Y-m-d H:i:s'))->orderBy('id')->limit(500)->get();
        foreach ($rows as $row) {
            try {
                $server = Server::withoutGlobalScopes()->find($row->server_id);
                if (!$server) {
                    DB::table('kazama_trash')->where('id', $row->id)->delete();
                    continue;
                }
                $this->purgeRow($server, $row);
                ++$n;
            } catch (\Throwable $e) {
                Log::warning("[kazama:trash] gagal purge #{$row->id}: " . $e->getMessage());
            }
        }

        return $n;
    }
}
