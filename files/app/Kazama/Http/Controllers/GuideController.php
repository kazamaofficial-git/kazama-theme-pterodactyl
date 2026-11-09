<?php

namespace Pterodactyl\Kazama\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Pterodactyl\Kazama\Mcp\Connections;
use Pterodactyl\Kazama\Mcp\Platforms;
use Pterodactyl\Kazama\Mcp\Tools;
use Pterodactyl\Kazama\Support\Restrict;

class GuideController extends Controller
{
    public function mcp(Request $request)
    {
        $user = $request->user();
        $role = 'guest';
        $access = 'full';
        $tools = null;
        if ($user) {
            $role = Restrict::isSuper($user) ? 'super' : ($user->root_admin ? (Restrict::applies($user) ? 'limited' : 'admin') : 'user');
            $access = Connections::defaults($user->id)['access'];
            $tools = Tools::visible($user, (object) ['access' => $access]);
        }

        return view('kazama::guide.mcp', [
            'user' => $user, 'role' => $role, 'access' => $access, 'endpoint' => url('/api/mcp'), 'brand' => config('kazama.brand', 'Kazama Cloud'),
            'protect' => Restrict::name(), 'defaultName' => Connections::defaultName(), 'platforms' => Platforms::LIST,
            'groups' => Tools::GROUPS, 'tools' => $tools ?? Tools::all(), 'personal' => (bool) $user,
        ]);
    }
}
