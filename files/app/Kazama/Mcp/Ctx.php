<?php

namespace Pterodactyl\Kazama\Mcp;

use Pterodactyl\Models\Server;

/** Konteks satu panggilan alat: koneksi, user, server terpilih, dan klien HTTP loopback. */
final class Ctx
{
    public ?Server $server = null;
    public PanelHttp $http;

    public function __construct(public object $conn, public $user)
    {
        $this->http = new PanelHttp($conn, $user);
    }

    public function client(string $method, string $path, array $opt = [])
    {
        return $this->http->call('client', $method, $path, $opt);
    }

    public function srv(string $method, string $sub, array $opt = [])
    {
        return $this->http->call('client', $method, 'servers/' . $this->server->uuid . '/' . ltrim($sub, '/'), $opt);
    }

    public function app(string $method, string $path, array $opt = [])
    {
        return $this->http->call('app', $method, $path, $opt);
    }
}
