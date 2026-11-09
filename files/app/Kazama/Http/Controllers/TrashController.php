<?php

namespace Pterodactyl\Kazama\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\DeleteFileRequest;
use Pterodactyl\Kazama\Services\TrashService;
use Pterodactyl\Kazama\Support\ServerAccess;
use Pterodactyl\Models\Server;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;

class TrashController extends Controller
{
    public function __construct(private TrashService $trash, private DaemonFileRepository $files)
    {
    }

    /**
     * Menggantikan POST /api/client/servers/{server}/files/delete:
     * file dipindah ke tempat sampah (/.kazama-trash) dan dihapus permanen setelah 7 hari.
     */
    public function delete(DeleteFileRequest $request, Server $server): JsonResponse
    {
        $root = (string) $request->input('root', '/');
        $files = array_values($request->input('files', []));

        foreach (array_merge([$root], $files) as $p) {
            abort_unless(TrashService::safe($p), 422, 'Path tidak valid.');
        }

        if ($this->trash->isPermanent($root, $files)) {
            $this->files->setServer($server)->deleteFiles($root, $files);
            if (in_array(TrashService::DIR, array_map(fn ($f) => trim($f, '/'), $files), true)) {
                DB::table('kazama_trash')->where('server_id', $server->id)->delete();
            }
        } else {
            $this->trash->trash($server, $root, $files);
        }

        Activity::event('server:file.delete')->property('directory', $root)->property('files', $files)->property('trash', true)->log();

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    private function row(object $r): array
    {
        $exp = Carbon::parse($r->expires_at, 'UTC');

        return [
            'id' => (int) $r->id, 'name' => $r->name, 'originalPath' => $r->original_path, 'isDir' => (bool) $r->is_dir,
            'size' => (int) $r->size, 'deletedBy' => $r->deleted_by,
            'deletedAt' => Carbon::parse($r->deleted_at, 'UTC')->toIso8601String(), 'expiresAt' => $exp->toIso8601String(),
            'daysLeft' => max(0, (int) ceil(($exp->getTimestamp() - time()) / 86400)),
        ];
    }

    public function index(Request $request, string $uuid): JsonResponse
    {
        $server = ServerAccess::get($request->user(), $uuid, 'file.delete');

        return new JsonResponse(['data' => array_map([$this, 'row'], $this->trash->list($server)), 'days' => TrashService::DAYS]);
    }

    public function restore(Request $request, string $uuid, int $id): JsonResponse
    {
        $server = ServerAccess::get($request->user(), $uuid, 'file.delete');

        return new JsonResponse(['restoredTo' => $this->trash->restore($server, $id)]);
    }

    public function purge(Request $request, string $uuid, int $id): JsonResponse
    {
        $server = ServerAccess::get($request->user(), $uuid, 'file.delete');
        $this->trash->purge($server, $id);

        return new JsonResponse(['ok' => true]);
    }

    public function empty(Request $request, string $uuid): JsonResponse
    {
        $server = ServerAccess::get($request->user(), $uuid, 'file.delete');
        foreach ($this->trash->list($server) as $row) {
            $this->trash->purgeRow($server, $row);
        }

        return new JsonResponse(['ok' => true]);
    }
}
