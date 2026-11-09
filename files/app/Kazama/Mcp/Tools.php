<?php

namespace Pterodactyl\Kazama\Mcp;

use Illuminate\Support\Facades\DB;
use Pterodactyl\Kazama\Services\ExpiryService;
use Pterodactyl\Kazama\Services\Notifier;
use Pterodactyl\Kazama\Services\TrashService;
use Pterodactyl\Kazama\Support\Cadence;
use Pterodactyl\Kazama\Support\Restrict;
use Pterodactyl\Kazama\Support\ServerAccess;
use Pterodactyl\Models\Server;

/**
 * Registry alat MCP. Setiap alat: nama, grup, role (user|admin|super), write, confirm, backup, deskripsi, parameter, handler.
 * Alat bertipe 'server' otomatis mendapat parameter `server` yang WAJIB disebut pengguna (AI tidak boleh menebak).
 */
final class Tools
{
    private static ?array $all = null;

    public const GROUPS = [
        'account' => 'Akun', 'server' => 'Server', 'files' => 'File', 'trash' => 'Trash', 'backups' => 'Backup', 'databases' => 'Database',
        'schedules' => 'Jadwal', 'network' => 'Jaringan', 'startup' => 'Startup', 'settings' => 'Pengaturan', 'subusers' => 'Subuser', 'admin' => 'Admin',
    ];

    // ---- helper skema parameter ----
    private static function s(string $d, bool $req = true, ?array $enum = null): array
    {
        return array_filter(['type' => 'string', 'description' => $d, 'enum' => $enum, '_req' => $req]);
    }

    private static function i(string $d, bool $req = true): array
    {
        return ['type' => 'integer', 'description' => $d, '_req' => $req];
    }

    private static function b(string $d, bool $req = false): array
    {
        return ['type' => 'boolean', 'description' => $d, '_req' => $req];
    }

    private static function a(string $d, bool $req = true): array
    {
        return ['type' => 'array', 'items' => ['type' => 'string'], 'description' => $d, '_req' => $req];
    }

    private static function o(string $d, bool $req = false): array
    {
        return ['type' => 'object', 'description' => $d, '_req' => $req];
    }

    private static function add(array &$t, string $name, string $group, string $role, bool $write, string $desc, array $params, callable $run, array $o = []): void
    {
        $t[$name] = [
            'name' => $name, 'group' => $group, 'role' => $role, 'write' => $write, 'desc' => $desc, 'params' => $params, 'run' => $run,
            'scope' => $o['scope'] ?? (preg_match('/^admin_server_(update|suspend|unsuspend|expiry|delete)$/', $name) ? 'server' : ($group === 'account' || $group === 'admin' ? 'none' : 'server')),
            'confirm' => $o['confirm'] ?? false, 'backup' => $o['backup'] ?? false, 'console' => $o['console'] ?? null,
        ];
    }

    public static function all(): array
    {
        if (self::$all !== null) {
            return self::$all;
        }
        $t = [];
        $S = 'server';
        $root = fn (array $a) => $a['root'] ?? '/';

        /* ===== Akun ===== */
        self::add($t, 'account_info', 'account', 'user', false, 'Show the connected user\'s account profile.', [], fn (Ctx $c) => $c->client('GET', 'account'));
        self::add($t, 'account_activity', 'account', 'user', false, 'Recent account activity log.', ['per_page' => self::i('Items (max 50)', false)], fn (Ctx $c, $a) => $c->client('GET', 'account/activity', ['query' => ['per_page' => min(50, $a['per_page'] ?? 20)]]));
        self::add($t, 'ssh_keys_list', 'account', 'user', false, 'List the account\'s SSH keys.', [], fn (Ctx $c) => $c->client('GET', 'account/ssh-keys'));
        self::add($t, 'ssh_key_add', 'account', 'user', true, 'Add an SSH public key to the account.', ['name' => self::s('Label'), 'public_key' => self::s('Public key (ssh-ed25519 ... / ssh-rsa ...)')], fn (Ctx $c, $a) => $c->client('POST', 'account/ssh-keys', ['json' => ['name' => $a['name'], 'public_key' => $a['public_key']]]));
        self::add($t, 'ssh_key_remove', 'account', 'user', true, 'Remove an SSH key by fingerprint.', ['fingerprint' => self::s('Key fingerprint')], fn (Ctx $c, $a) => $c->client('POST', 'account/ssh-keys/remove', ['json' => ['fingerprint' => $a['fingerprint']]]), ['confirm' => true]);
        self::add($t, 'api_keys_list', 'account', 'user', false, 'List the account\'s API keys (secrets are never shown).', [], fn (Ctx $c) => $c->client('GET', 'account/api-keys'));
        self::add($t, 'api_key_create', 'account', 'user', true, 'Create a client API key. The secret is returned ONCE; tell the user to store it safely.', ['description' => self::s('Description'), 'allowed_ips' => self::a('Allowed IPs (empty = any)', false)], fn (Ctx $c, $a) => $c->client('POST', 'account/api-keys', ['json' => ['description' => $a['description'], 'allowed_ips' => $a['allowed_ips'] ?? []]]), ['confirm' => true]);
        self::add($t, 'api_key_delete', 'account', 'user', true, 'Delete an API key by identifier.', ['identifier' => self::s('Key identifier')], fn (Ctx $c, $a) => $c->client('DELETE', 'account/api-keys/' . rawurlencode($a['identifier'])), ['confirm' => true]);
        self::add($t, 'account_update_email', 'account', 'user', true, 'Change the account email (requires the current password). SENSITIVE: confirm with the user first.', ['email' => self::s('New email'), 'password' => self::s('Current password')], fn (Ctx $c, $a) => $c->client('PUT', 'account/email', ['json' => ['email' => $a['email'], 'password' => $a['password']]]), ['confirm' => true]);
        self::add($t, 'account_update_password', 'account', 'user', true, 'Change the account password. SENSITIVE: confirm with the user first.', ['current_password' => self::s('Current password'), 'password' => self::s('New password (min 8)')], fn (Ctx $c, $a) => $c->client('PUT', 'account/password', ['json' => ['current_password' => $a['current_password'], 'password' => $a['password'], 'password_confirmation' => $a['password']]]), ['confirm' => true]);

        /* ===== Server ===== */
        self::add($t, 'list_servers', 'server', 'user', false, 'List every server this connection may use (name, identifier, status, address, limits, expiry/suspension info). Call this when the user did not name a server, then ASK which one.', [], function (Ctx $c) {
            $rows = [];
            foreach (McpServer::allowedServers($c) as $s) {
                $rows[] = self::serverInfo($s);
            }

            return ['servers' => $rows, 'note' => $rows ? 'Ask the user which server to use if they did not say.' : 'No servers available. Tell the user to contact the admin to buy a server.'];
        }, ['scope' => 'none']);
        self::add($t, 'server_details', 'server', 'user', false, 'Details of one server (Pterodactyl data + Kazama expiry/suspension).', [], fn (Ctx $c) => ['panel' => $c->srv('GET', ''), 'kazama' => self::serverInfo($c->server)]);
        self::add($t, 'server_resources', 'server', 'user', false, 'Live state and CPU/RAM/disk/network usage of one server.', [], fn (Ctx $c) => $c->srv('GET', 'resources'));
        self::add($t, 'server_activity', 'server', 'user', false, 'Recent activity log of one server.', ['per_page' => self::i('Items (max 50)', false)], fn (Ctx $c, $a) => $c->srv('GET', 'activity', ['query' => ['per_page' => min(50, $a['per_page'] ?? 20)]]));
        self::add($t, 'power', 'server', 'user', true, 'Send a power signal to a server: start, stop, restart or kill (kill is forced and needs confirmation).', ['signal' => self::s('Power signal', true, ['start', 'stop', 'restart', 'kill'])], fn (Ctx $c, $a) => $c->srv('POST', 'power', ['json' => ['signal' => $a['signal']]]), [
            'confirm' => fn ($a) => ($a['signal'] ?? '') === 'kill',
            'console' => fn ($a) => ['start' => 'Start Server', 'stop' => 'Stop Server', 'restart' => 'Restart Server', 'kill' => 'Kill Server'][$a['signal'] ?? ''] ?? 'Power',
        ]);
        self::add($t, 'send_command', 'server', 'user', true, 'Send a console command to a running server.', ['command' => self::s('Command, without leading slash')], fn (Ctx $c, $a) => $c->srv('POST', 'command', ['json' => ['command' => $a['command']]]), ['console' => fn ($a) => 'Kirim perintah: ' . mb_substr($a['command'] ?? '', 0, 80)]);
        self::add($t, 'server_expiry', 'server', 'user', false, 'Active-until date and suspension info of a server (WIB).', [], fn (Ctx $c) => self::serverInfo($c->server));

        /* ===== File ===== */
        self::add($t, 'files_list', 'files', 'user', false, 'List a directory.', ['directory' => self::s('Directory path, e.g. "/" or "/plugins"', false)], fn (Ctx $c, $a) => $c->srv('GET', 'files/list', ['query' => ['directory' => $a['directory'] ?? '/']]));
        self::add($t, 'files_read', 'files', 'user', false, 'Read a text file (large files are truncated).', ['path' => self::s('Full file path')], function (Ctx $c, $a) {
            $r = $c->srv('GET', 'files/contents', ['query' => ['file' => $a['path']]]);
            $text = is_string($r) ? $r : json_encode($r);

            return mb_strlen($text) > 100000 ? mb_substr($text, 0, 100000) . "\n…[dipotong: file terlalu besar]" : $text;
        });
        self::add($t, 'files_write', 'files', 'user', true, 'Create or overwrite a file with the given full content. Make sure you know the exact desired content; ask the user if unclear.', ['path' => self::s('Full file path'), 'content' => self::s('Complete new file content')], fn (Ctx $c, $a) => $c->srv('POST', 'files/write', ['query' => ['file' => $a['path']], 'raw' => $a['content']]), ['backup' => true, 'console' => fn ($a) => 'Tulis file ' . ($a['path'] ?? '')]);
        self::add($t, 'files_create_folder', 'files', 'user', true, 'Create a folder.', ['root' => self::s('Parent directory'), 'name' => self::s('Folder name')], fn (Ctx $c, $a) => $c->srv('POST', 'files/create-folder', ['json' => ['root' => $a['root'], 'name' => $a['name']]]), ['console' => fn ($a) => 'Buat folder ' . ($a['root'] ?? '') . '/' . ($a['name'] ?? '')]);
        self::add($t, 'files_rename', 'files', 'user', true, 'Rename or move files inside a root directory.', ['root' => self::s('Root directory'), 'from' => self::s('Current name (relative to root)'), 'to' => self::s('New name/path (relative to root)')], fn (Ctx $c, $a) => $c->srv('PUT', 'files/rename', ['json' => ['root' => $a['root'], 'files' => [['from' => $a['from'], 'to' => $a['to']]]]]), ['backup' => true, 'console' => fn ($a) => "Ubah nama {$a['from']} → {$a['to']}"]);
        self::add($t, 'files_copy', 'files', 'user', true, 'Duplicate a file (creates a unique copy next to it).', ['location' => self::s('Full file path')], fn (Ctx $c, $a) => $c->srv('POST', 'files/copy', ['json' => ['location' => $a['location']]]), ['console' => fn ($a) => 'Salin file ' . ($a['location'] ?? '')]);
        self::add($t, 'files_delete', 'files', 'user', true, 'Delete files. They are moved to the server Trash and removed permanently after 7 days (restorable with trash_restore). Deleting inside the Trash is permanent.', ['root' => self::s('Root directory'), 'files' => self::a('Names to delete (relative to root)')], fn (Ctx $c, $a) => $c->srv('POST', 'files/delete', ['json' => ['root' => $a['root'], 'files' => $a['files']]]), ['confirm' => true, 'console' => fn ($a) => 'Hapus (ke Trash): ' . implode(', ', array_slice($a['files'] ?? [], 0, 5))]);
        self::add($t, 'files_compress', 'files', 'user', true, 'Compress files into an archive.', ['root' => self::s('Root directory'), 'files' => self::a('Names to compress')], fn (Ctx $c, $a) => $c->srv('POST', 'files/compress', ['json' => ['root' => $a['root'], 'files' => $a['files']]]), ['console' => fn ($a) => 'Kompres ' . implode(', ', array_slice($a['files'] ?? [], 0, 5))]);
        self::add($t, 'files_decompress', 'files', 'user', true, 'Extract an archive (may overwrite files).', ['root' => self::s('Directory'), 'file' => self::s('Archive file name')], fn (Ctx $c, $a) => $c->srv('POST', 'files/decompress', ['json' => ['root' => $a['root'], 'file' => $a['file']]]), ['backup' => true, 'console' => fn ($a) => 'Ekstrak ' . ($a['file'] ?? '')]);
        self::add($t, 'files_pull', 'files', 'user', true, 'Download a remote file into the server.', ['url' => self::s('Source URL'), 'directory' => self::s('Target directory'), 'filename' => self::s('File name', false)], fn (Ctx $c, $a) => $c->srv('POST', 'files/pull', ['json' => array_filter(['url' => $a['url'], 'directory' => $a['directory'], 'filename' => $a['filename'] ?? null, 'foreground' => true])]), ['backup' => true, 'console' => fn ($a) => 'Unduh ' . ($a['url'] ?? '')]);
        self::add($t, 'files_chmod', 'files', 'user', true, 'Change file permissions.', ['root' => self::s('Root directory'), 'file' => self::s('File name'), 'mode' => self::s('Octal mode, e.g. 644')], fn (Ctx $c, $a) => $c->srv('POST', 'files/chmod', ['json' => ['root' => $a['root'], 'files' => [['file' => $a['file'], 'mode' => $a['mode']]]]]), ['console' => fn ($a) => "chmod {$a['mode']} {$a['file']}"]);

        /* ===== Trash ===== */
        self::add($t, 'trash_list', 'trash', 'user', false, 'List deleted files in the server Trash (kept 7 days).', [], fn (Ctx $c) => ['items' => array_map(fn ($r) => ['id' => (int) $r->id, 'name' => $r->name, 'original_path' => $r->original_path, 'deleted_by' => $r->deleted_by, 'deleted_at' => Cadence::wib($r->deleted_at), 'expires_at' => Cadence::wib($r->expires_at)], app(TrashService::class)->list($c->server))]);
        self::add($t, 'trash_restore', 'trash', 'user', true, 'Restore an item from the Trash to its original location.', ['id' => self::i('Trash item id from trash_list')], fn (Ctx $c, $a) => ['restored_to' => app(TrashService::class)->restore($c->server, (int) $a['id'])], ['console' => fn ($a) => 'Pulihkan dari Trash #' . ($a['id'] ?? '')]);
        self::add($t, 'trash_purge', 'trash', 'user', true, 'Permanently delete a Trash item.', ['id' => self::i('Trash item id')], function (Ctx $c, $a) {
            app(TrashService::class)->purge($c->server, (int) $a['id']);

            return ['ok' => true];
        }, ['confirm' => true]);

        /* ===== Backup ===== */
        self::add($t, 'backups_list', 'backups', 'user', false, 'List server backups.', [], fn (Ctx $c) => $c->srv('GET', 'backups'));
        self::add($t, 'backup_create', 'backups', 'user', true, 'Create a server backup.', ['name' => self::s('Backup name', false), 'ignored' => self::s('Ignore patterns, one per line', false), 'locked' => self::b('Lock the backup')], fn (Ctx $c, $a) => $c->srv('POST', 'backups', ['json' => array_filter(['name' => $a['name'] ?? null, 'ignored' => $a['ignored'] ?? null, 'is_locked' => $a['locked'] ?? false], fn ($v) => $v !== null)]), ['console' => fn ($a) => 'Buat backup']);
        self::add($t, 'backup_restore', 'backups', 'user', true, 'Restore a backup (replaces server files; set truncate to wipe first).', ['backup' => self::s('Backup UUID'), 'truncate' => self::b('Delete all files before restoring')], fn (Ctx $c, $a) => $c->srv('POST', 'backups/' . rawurlencode($a['backup']) . '/restore', ['json' => ['truncate' => $a['truncate'] ?? false]]), ['confirm' => true, 'backup' => true, 'console' => fn ($a) => 'Restore backup ' . ($a['backup'] ?? '')]);
        self::add($t, 'backup_delete', 'backups', 'user', true, 'Delete a backup.', ['backup' => self::s('Backup UUID')], fn (Ctx $c, $a) => $c->srv('DELETE', 'backups/' . rawurlencode($a['backup'])), ['confirm' => true]);
        self::add($t, 'backup_lock', 'backups', 'user', true, 'Toggle the lock on a backup.', ['backup' => self::s('Backup UUID')], fn (Ctx $c, $a) => $c->srv('POST', 'backups/' . rawurlencode($a['backup']) . '/lock'));
        self::add($t, 'backup_download_url', 'backups', 'user', false, 'Get a temporary signed download URL for a backup.', ['backup' => self::s('Backup UUID')], fn (Ctx $c, $a) => $c->srv('GET', 'backups/' . rawurlencode($a['backup']) . '/download'));

        /* ===== Database ===== */
        self::add($t, 'databases_list', 'databases', 'user', false, 'List server databases (with passwords).', [], fn (Ctx $c) => $c->srv('GET', 'databases', ['query' => ['include' => 'password']]));
        self::add($t, 'database_create', 'databases', 'user', true, 'Create a database.', ['database' => self::s('Database name suffix'), 'remote' => self::s('Allowed connections, "%" for any')], fn (Ctx $c, $a) => $c->srv('POST', 'databases', ['query' => ['include' => 'password'], 'json' => ['database' => $a['database'], 'remote' => $a['remote']]]), ['console' => fn ($a) => 'Buat database ' . ($a['database'] ?? '')]);
        self::add($t, 'database_rotate_password', 'databases', 'user', true, 'Rotate a database password.', ['database' => self::s('Database id')], fn (Ctx $c, $a) => $c->srv('POST', 'databases/' . rawurlencode($a['database']) . '/rotate-password'), ['confirm' => true]);
        self::add($t, 'database_delete', 'databases', 'user', true, 'Delete a database permanently (data is lost).', ['database' => self::s('Database id')], fn (Ctx $c, $a) => $c->srv('DELETE', 'databases/' . rawurlencode($a['database'])), ['confirm' => true]);

        /* ===== Jadwal ===== */
        self::add($t, 'schedules_list', 'schedules', 'user', false, 'List schedules and their tasks.', [], fn (Ctx $c) => $c->srv('GET', 'schedules', ['query' => ['include' => 'tasks']]));
        self::add($t, 'schedule_create', 'schedules', 'user', true, 'Create a schedule (cron fields).', ['name' => self::s('Name'), 'minute' => self::s('Cron minute'), 'hour' => self::s('Cron hour'), 'day_of_month' => self::s('Cron day of month', false), 'month' => self::s('Cron month', false), 'day_of_week' => self::s('Cron day of week', false), 'only_when_online' => self::b('Run only while online'), 'is_active' => self::b('Enabled')], fn (Ctx $c, $a) => $c->srv('POST', 'schedules', ['json' => ['name' => $a['name'], 'minute' => $a['minute'], 'hour' => $a['hour'], 'day_of_month' => $a['day_of_month'] ?? '*', 'month' => $a['month'] ?? '*', 'day_of_week' => $a['day_of_week'] ?? '*', 'only_when_online' => $a['only_when_online'] ?? false, 'is_active' => $a['is_active'] ?? true]]), ['console' => fn ($a) => 'Buat jadwal ' . ($a['name'] ?? '')]);
        self::add($t, 'schedule_task_create', 'schedules', 'user', true, 'Add a task to a schedule.', ['schedule' => self::s('Schedule id'), 'action' => self::s('Task action', true, ['command', 'power', 'backup']), 'payload' => self::s('Command / power signal / ignored files'), 'time_offset' => self::i('Seconds to wait before running', false)], fn (Ctx $c, $a) => $c->srv('POST', 'schedules/' . rawurlencode($a['schedule']) . '/tasks', ['json' => ['action' => $a['action'], 'payload' => $a['payload'], 'time_offset' => $a['time_offset'] ?? 0, 'continue_on_failure' => false]]));
        self::add($t, 'schedule_run', 'schedules', 'user', true, 'Run a schedule now.', ['schedule' => self::s('Schedule id')], fn (Ctx $c, $a) => $c->srv('POST', 'schedules/' . rawurlencode($a['schedule']) . '/execute'), ['console' => fn ($a) => 'Jalankan jadwal #' . ($a['schedule'] ?? '')]);
        self::add($t, 'schedule_delete', 'schedules', 'user', true, 'Delete a schedule.', ['schedule' => self::s('Schedule id')], fn (Ctx $c, $a) => $c->srv('DELETE', 'schedules/' . rawurlencode($a['schedule'])), ['confirm' => true]);

        /* ===== Jaringan ===== */
        self::add($t, 'network_list', 'network', 'user', false, 'List allocations (IP:port).', [], fn (Ctx $c) => $c->srv('GET', 'network/allocations'));
        self::add($t, 'allocation_assign', 'network', 'user', true, 'Assign a new allocation if the server limit allows.', [], fn (Ctx $c) => $c->srv('POST', 'network/allocations'));
        self::add($t, 'allocation_primary', 'network', 'user', true, 'Make an allocation the primary one.', ['allocation' => self::s('Allocation id')], fn (Ctx $c, $a) => $c->srv('POST', 'network/allocations/' . rawurlencode($a['allocation']) . '/primary'), ['console' => fn ($a) => 'Ganti alokasi utama']);
        self::add($t, 'allocation_notes', 'network', 'user', true, 'Set a note on an allocation.', ['allocation' => self::s('Allocation id'), 'notes' => self::s('Note')], fn (Ctx $c, $a) => $c->srv('POST', 'network/allocations/' . rawurlencode($a['allocation']), ['json' => ['notes' => $a['notes']]]));
        self::add($t, 'allocation_delete', 'network', 'user', true, 'Remove a non-primary allocation.', ['allocation' => self::s('Allocation id')], fn (Ctx $c, $a) => $c->srv('DELETE', 'network/allocations/' . rawurlencode($a['allocation'])), ['confirm' => true]);

        /* ===== Startup & Settings ===== */
        self::add($t, 'startup_get', 'startup', 'user', false, 'Startup command, variables and docker images.', [], fn (Ctx $c) => $c->srv('GET', 'startup'));
        self::add($t, 'startup_set_variable', 'startup', 'user', true, 'Set a startup variable.', ['key' => self::s('Environment variable name'), 'value' => self::s('New value')], fn (Ctx $c, $a) => $c->srv('PUT', 'startup/variable', ['json' => ['key' => $a['key'], 'value' => $a['value']]]), ['backup' => true, 'console' => fn ($a) => 'Ubah variabel startup ' . ($a['key'] ?? '')]);
        self::add($t, 'docker_image_set', 'startup', 'user', true, 'Change the docker image.', ['docker_image' => self::s('Image from startup_get')], fn (Ctx $c, $a) => $c->srv('PUT', 'settings/docker-image', ['json' => ['docker_image' => $a['docker_image']]]), ['backup' => true, 'console' => fn ($a) => 'Ganti Docker image']);
        self::add($t, 'server_rename', 'settings', 'user', true, 'Rename a server and/or change its description.', ['name' => self::s('New name'), 'description' => self::s('New description', false)], fn (Ctx $c, $a) => $c->srv('POST', 'settings/rename', ['json' => array_filter(['name' => $a['name'], 'description' => $a['description'] ?? null], fn ($v) => $v !== null)]), ['console' => fn ($a) => 'Ganti nama server → ' . ($a['name'] ?? '')]);
        self::add($t, 'server_reinstall', 'settings', 'user', true, 'Reinstall a server (wipes install files per egg).', [], fn (Ctx $c) => $c->srv('POST', 'settings/reinstall'), ['confirm' => true, 'backup' => true, 'console' => fn ($a) => 'Instal ulang server']);

        /* ===== Subuser ===== */
        self::add($t, 'subusers_list', 'subusers', 'user', false, 'List subusers.', [], fn (Ctx $c) => $c->srv('GET', 'users'));
        self::add($t, 'subuser_create', 'subusers', 'user', true, 'Invite a subuser with permissions.', ['email' => self::s('Email'), 'permissions' => self::a('Permissions, e.g. control.console, file.read')], fn (Ctx $c, $a) => $c->srv('POST', 'users', ['json' => ['email' => $a['email'], 'permissions' => $a['permissions']]]), ['confirm' => true]);
        self::add($t, 'subuser_delete', 'subusers', 'user', true, 'Remove a subuser.', ['subuser' => self::s('Subuser uuid')], fn (Ctx $c, $a) => $c->srv('DELETE', 'users/' . rawurlencode($a['subuser'])), ['confirm' => true]);

        /* ===== Kazama: auto-backup per server ===== */
        self::add($t, 'auto_backup_get', 'backups', 'user', false, 'Scheduled auto-backup settings of a server (WIB).', [], function (Ctx $c) {
            $r = DB::table('kazama_auto_backups')->where('server_id', $c->server->id)->first();

            return $r ? ['enabled' => (bool) $r->enabled, 'frequency' => $r->frequency, 'time_wib' => $r->time, 'weekday' => (int) $r->weekday, 'next_run' => Cadence::wib($r->next_run_at)] : ['enabled' => false];
        });
        self::add($t, 'auto_backup_set', 'backups', 'user', true, 'Enable/disable the scheduled auto-backup of a server.', ['enabled' => self::b('Enabled', true), 'frequency' => self::s('hourly|daily|weekly', false, ['hourly', 'daily', 'weekly']), 'time' => self::s('HH:MM in WIB', false), 'weekday' => self::i('0=Sunday..6', false)], function (Ctx $c, $a) {
            $f = $a['frequency'] ?? 'daily';
            $time = $a['time'] ?? '02:00';
            abort_unless(preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time), 422, 'Format jam harus HH:MM');
            $next = !empty($a['enabled']) ? Cadence::next($f, $time, (int) ($a['weekday'] ?? 1))->format('Y-m-d H:i:s') : null;
            DB::table('kazama_auto_backups')->updateOrInsert(['server_id' => $c->server->id], ['enabled' => (bool) $a['enabled'], 'frequency' => $f, 'time' => $time, 'weekday' => (int) ($a['weekday'] ?? 1), 'next_run_at' => $next, 'updated_at' => now('UTC')->format('Y-m-d H:i:s')]);

            return ['ok' => true, 'next_run' => $next ? Cadence::wib($next) : null];
        }, ['console' => fn ($a) => 'Auto-backup ' . (!empty($a['enabled']) ? 'diaktifkan' : 'dimatikan')]);

        /* ===== Admin (Application API + Kazama) ===== */
        self::add($t, 'admin_servers_list', 'admin', 'admin', false, 'Admin: list servers you manage (restricted admins only see servers they created). Returns numeric ids.', ['search' => self::s('Name filter', false)], function (Ctx $c, $a) {
            $rows = [];
            foreach (McpServer::allowedServers($c) as $s) {
                if (!empty($a['search']) && stripos($s->name, $a['search']) === false) {
                    continue;
                }
                $rows[] = ['id' => $s->id] + self::serverInfo($s) + ['owner_id' => $s->owner_id];
            }

            return ['servers' => $rows];
        });
        self::add($t, 'admin_users_list', 'admin', 'admin', false, 'Admin: list users (restricted admins see only users they created).', ['email' => self::s('Email filter', false), 'username' => self::s('Username filter', false), 'page' => self::i('Page', false)], fn (Ctx $c, $a) => $c->app('GET', 'users', ['query' => array_filter(['filter[email]' => $a['email'] ?? null, 'filter[username]' => $a['username'] ?? null, 'page' => $a['page'] ?? null, 'per_page' => 50])]));
        self::add($t, 'admin_user_create', 'admin', 'admin', true, 'Admin: create a regular user (never an admin).', ['email' => self::s('Email'), 'username' => self::s('Username'), 'first_name' => self::s('First name'), 'last_name' => self::s('Last name'), 'password' => self::s('Password (min 8)', false)], fn (Ctx $c, $a) => $c->app('POST', 'users', ['json' => array_filter(['email' => $a['email'], 'username' => $a['username'], 'first_name' => $a['first_name'], 'last_name' => $a['last_name'], 'password' => $a['password'] ?? null])]), ['confirm' => true]);
        self::add($t, 'admin_user_update', 'admin', 'admin', true, 'Admin: edit a user (only the fields you pass change).', ['user_id' => self::i('User id'), 'email' => self::s('Email', false), 'username' => self::s('Username', false), 'first_name' => self::s('First name', false), 'last_name' => self::s('Last name', false), 'password' => self::s('New password', false)], function (Ctx $c, $a) {
            $cur = $c->app('GET', 'users/' . (int) $a['user_id'])['attributes'] ?? null;
            if (!$cur) {
                throw new ToolError('User tidak ditemukan atau di luar akses Anda.');
            }
            $body = ['email' => $a['email'] ?? $cur['email'], 'username' => $a['username'] ?? $cur['username'], 'first_name' => $a['first_name'] ?? $cur['first_name'], 'last_name' => $a['last_name'] ?? $cur['last_name'], 'language' => $cur['language'] ?? 'en'];
            if (!empty($a['password'])) {
                $body['password'] = $a['password'];
            }

            return $c->app('PATCH', 'users/' . (int) $a['user_id'], ['json' => $body]);
        }, ['confirm' => true]);
        self::add($t, 'admin_nests_list', 'admin', 'admin', false, 'Admin: nests with eggs (docker images, startup) needed to create servers.', [], fn (Ctx $c) => $c->app('GET', 'nests', ['query' => ['include' => 'eggs']]));
        self::add($t, 'admin_nodes_list', 'admin', 'admin', false, 'Admin: list nodes.', [], fn (Ctx $c) => $c->app('GET', 'nodes'));
        self::add($t, 'admin_node_allocations', 'admin', 'admin', false, 'Admin: allocations of a node (find unassigned ones).', ['node_id' => self::i('Node id'), 'page' => self::i('Page', false)], fn (Ctx $c, $a) => $c->app('GET', 'nodes/' . (int) $a['node_id'] . '/allocations', ['query' => ['per_page' => 100, 'page' => $a['page'] ?? 1]]));
        self::add($t, 'admin_server_create', 'admin', 'admin', true, 'Admin: create a server. Ask the user for owner, egg, node/allocation and limits; use admin_nests_list and admin_node_allocations for ids. Server gets the default active period (30 days).', ['name' => self::s('Server name'), 'user' => self::i('Owner user id'), 'egg' => self::i('Egg id'), 'docker_image' => self::s('Docker image'), 'startup' => self::s('Startup command'), 'environment' => self::o('Egg variables as {KEY: value}', true), 'memory' => self::i('RAM MB (0 = unlimited)'), 'disk' => self::i('Disk MB (0 = unlimited)'), 'cpu' => self::i('CPU % (0 = unlimited)'), 'swap' => self::i('Swap MB', false), 'io' => self::i('IO weight 10-1000', false), 'databases' => self::i('Database limit', false), 'backups' => self::i('Backup limit', false), 'allocations' => self::i('Extra allocation limit', false), 'allocation_id' => self::i('Unassigned allocation id to use as primary')], fn (Ctx $c, $a) => $c->app('POST', 'servers', ['json' => [
            'name' => $a['name'], 'user' => (int) $a['user'], 'egg' => (int) $a['egg'], 'docker_image' => $a['docker_image'], 'startup' => $a['startup'], 'environment' => (object) $a['environment'],
            'limits' => ['memory' => (int) $a['memory'], 'swap' => (int) ($a['swap'] ?? 0), 'disk' => (int) $a['disk'], 'io' => (int) ($a['io'] ?? 500), 'cpu' => (int) $a['cpu']],
            'feature_limits' => ['databases' => (int) ($a['databases'] ?? 0), 'backups' => (int) ($a['backups'] ?? 3), 'allocations' => (int) ($a['allocations'] ?? 0)],
            'allocation' => ['default' => (int) $a['allocation_id']],
        ]]), ['confirm' => true]);
        self::add($t, 'admin_server_update', 'admin', 'admin', true, 'Admin: change a server\'s name/description/limits (only fields you pass change).', ['name' => self::s('Name', false), 'description' => self::s('Description', false), 'memory' => self::i('RAM MB', false), 'disk' => self::i('Disk MB', false), 'cpu' => self::i('CPU %', false), 'swap' => self::i('Swap MB', false), 'io' => self::i('IO', false), 'databases' => self::i('DB limit', false), 'backups' => self::i('Backup limit', false), 'allocations' => self::i('Allocation limit', false)], function (Ctx $c, $a) {
            $id = $c->server->id;
            $cur = $c->app('GET', "servers/{$id}")['attributes'] ?? null;
            if (!$cur) {
                throw new ToolError('Server tidak ditemukan di Application API.');
            }
            if (isset($a['name']) || isset($a['description'])) {
                $c->app('PATCH', "servers/{$id}/details", ['json' => ['name' => $a['name'] ?? $cur['name'], 'user' => $cur['user'], 'description' => $a['description'] ?? $cur['description'], 'external_id' => $cur['external_id']]]);
            }
            $lim = $cur['limits'];
            $fl = $cur['feature_limits'];
            $touch = array_intersect_key($a, array_flip(['memory', 'disk', 'cpu', 'swap', 'io', 'databases', 'backups', 'allocations']));
            if ($touch) {
                $c->app('PATCH', "servers/{$id}/build", ['json' => ['allocation' => $cur['allocation'], 'memory' => $a['memory'] ?? $lim['memory'], 'swap' => $a['swap'] ?? $lim['swap'], 'disk' => $a['disk'] ?? $lim['disk'], 'io' => $a['io'] ?? $lim['io'], 'cpu' => $a['cpu'] ?? $lim['cpu'], 'threads' => $lim['threads'], 'oom_disabled' => $cur['container']['oom_disabled'] ?? true,
                    'feature_limits' => ['databases' => $a['databases'] ?? $fl['databases'], 'backups' => $a['backups'] ?? $fl['backups'], 'allocations' => $a['allocations'] ?? $fl['allocations']]]]);
            }

            return ['ok' => true];
        }, ['confirm' => true, 'console' => fn ($a) => 'Ubah konfigurasi server']);
        self::add($t, 'admin_server_suspend', 'admin', 'admin', true, 'Admin: suspend a server. A reason is REQUIRED and is shown to the owner together with your name.', ['reason' => self::s('Reason shown to the owner')], fn (Ctx $c, $a) => $c->app('POST', "servers/{$c->server->id}/suspend", ['reason' => $a['reason']]), ['confirm' => true, 'console' => fn ($a) => 'Server disuspend: ' . ($a['reason'] ?? '')]);
        self::add($t, 'admin_server_unsuspend', 'admin', 'admin', true, 'Admin: unsuspend a server.', [], fn (Ctx $c) => $c->app('POST', "servers/{$c->server->id}/unsuspend"), ['console' => fn ($a) => 'Server diaktifkan kembali']);
        self::add($t, 'admin_server_expiry', 'admin', 'admin', true, 'Admin: set or extend the active-until date of a server. Use extend_days or expires_at (WIB, YYYY-MM-DD HH:MM).', ['extend_days' => self::i('Days to add (1-365)', false), 'expires_at' => self::s('Exact date-time in WIB, YYYY-MM-DD HH:MM', false)], function (Ctx $c, $a) {
            $svc = app(ExpiryService::class);
            if (!empty($a['expires_at'])) {
                $d = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $a['expires_at'], config('kazama.timezone'));
                abort_unless($d, 422, 'Format tanggal harus YYYY-MM-DD HH:MM');
                $new = $svc->set($c->server, $d->utc());
            } elseif (!empty($a['extend_days'])) {
                abort_unless($a['extend_days'] >= 1 && $a['extend_days'] <= 365, 422, 'extend_days 1-365');
                $new = $svc->extend($c->server, (int) $a['extend_days']);
            } else {
                throw new ToolError('Isi extend_days atau expires_at.');
            }

            return ['expires_at' => Cadence::wib($new)];
        }, ['confirm' => true, 'console' => fn ($a) => 'Masa aktif diperbarui']);
        self::add($t, 'admin_server_delete', 'admin', 'super', true, 'Super admin only: delete a server. A reason is REQUIRED; the owner sees it with your name for 1 day.', ['reason' => self::s('Reason shown to the owner')], fn (Ctx $c, $a) => $c->app('DELETE', "servers/{$c->server->id}/force", ['reason' => $a['reason']]), ['confirm' => true, 'backup' => false]);
        self::add($t, 'admin_notify', 'admin', 'super', true, 'Super admin only: send a dashboard notification.', ['title' => self::s('Title'), 'message' => self::s('Message'), 'type' => self::s('info|success|warning|danger', false, ['info', 'success', 'warning', 'danger']), 'user_email' => self::s('Target user email (omit = everyone)', false)], function (Ctx $c, $a) {
            $uid = null;
            if (!empty($a['user_email'])) {
                $uid = DB::table('users')->where('email', $a['user_email'])->value('id');
                if (!$uid) {
                    throw new ToolError('User dengan email itu tidak ditemukan.');
                }
            }
            Notifier::send($uid, $a['title'], $a['message'], $a['type'] ?? 'info');

            return ['ok' => true];
        }, ['confirm' => true]);
        self::add($t, 'admin_logs', 'admin', 'super', false, 'Super admin only: recent Kazama logs (who did what).', ['limit' => self::i('Max 100', false), 'source' => self::s('admin|user|mcp|system', false)], fn (Ctx $c, $a) => ['logs' => DB::table('kazama_logs')->when(!empty($a['source']), fn ($q) => $q->where('source', $a['source']))->orderByDesc('id')->limit(min(100, $a['limit'] ?? 30))->get(['actor_name', 'source', 'action', 'server_name', 'target', 'detail', 'status', 'created_at'])->map(fn ($r) => (array) $r + ['time' => Cadence::wib($r->created_at)])->all()]);

        return self::$all = $t;
    }

    public static function serverInfo(Server $s): array
    {
        $alloc = DB::table('allocations')->where('id', $s->allocation_id)->first(['ip', 'ip_alias', 'port']);
        $exp = $s->getRawOriginal('kazama_expires_at');
        $by = DB::table('users')->where('id', $s->kazama_created_by)->value('username');

        return [
            'name' => $s->name, 'identifier' => $s->uuidShort, 'uuid' => $s->uuid, 'status' => $s->status ?? 'ok',
            'suspended' => $s->status === Server::STATUS_SUSPENDED, 'address' => $alloc ? (($alloc->ip_alias ?: $alloc->ip) . ':' . $alloc->port) : null,
            'limits' => ['memory_mb' => $s->memory, 'disk_mb' => $s->disk, 'cpu_percent' => $s->cpu],
            'active_until_wib' => $exp ? Cadence::wib($exp) : null,
            'suspended_by' => $s->kazama_suspended_by, 'suspend_reason' => $s->kazama_suspend_reason,
            'created_by' => $by ?: 'Sistem',
        ];
    }

    /** Alat yang terlihat untuk koneksi ini (role akun + mode akses). */
    public static function visible($user, object $conn): array
    {
        $out = [];
        foreach (self::all() as $name => $tool) {
            if ($tool['role'] === 'admin' && !$user->root_admin) {
                continue;
            }
            if ($tool['role'] === 'super' && !Restrict::isSuper($user)) {
                continue;
            }
            if ($tool['write'] && $conn->access === 'read') {
                continue;
            }
            $out[$name] = $tool;
        }

        return $out;
    }

    /** Skema JSON untuk tools/list. */
    public static function schema(array $tool): array
    {
        $props = [];
        $req = [];
        if ($tool['scope'] === 'server') {
            $props['server'] = ['type' => 'string', 'description' => 'Which server (name, 8-char identifier or UUID). The USER must tell you; if they did not, call list_servers and ASK — never guess.'];
        }
        foreach ($tool['params'] as $k => $p) {
            $isReq = $p['_req'] ?? false;
            unset($p['_req']);
            $props[$k] = $p;
            if ($isReq) {
                $req[] = $k;
            }
        }
        if ($tool['confirm']) {
            $props['confirmed'] = ['type' => 'boolean', 'description' => 'Set true ONLY after the user explicitly confirmed this action in this conversation.'];
        }

        $schema = ['type' => 'object', 'properties' => (object) $props];
        if ($req) {
            $schema['required'] = $req;
        }

        return $schema;
    }

    public static function catalog($user, object $conn): array
    {
        return array_values(array_map(fn ($t) => [
            'name' => $t['name'], 'group' => $t['group'], 'groupLabel' => self::GROUPS[$t['group']] ?? $t['group'], 'role' => $t['role'],
            'write' => $t['write'], 'needsConfirm' => (bool) $t['confirm'], 'autoBackup' => (bool) $t['backup'], 'description' => $t['desc'],
        ], self::visible($user, $conn)));
    }
}
