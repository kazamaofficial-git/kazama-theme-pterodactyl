@php($kzUser = auth()->user())
<li class="header">KAZAMA CLOUD</li>
@if(\Pterodactyl\Kazama\Support\Restrict::isSuper($kzUser))
<li class="{{ \Request::routeIs('kazama.admin.backup*') ? 'active' : '' }}"><a href="{{ route('kazama.admin.backup') }}"><i class="fa fa-database"></i> <span>Panel Backup</span></a></li>
<li class="{{ \Request::routeIs('kazama.admin.notifications*') ? 'active' : '' }}"><a href="{{ route('kazama.admin.notifications') }}"><i class="fa fa-bell"></i> <span>Notifikasi</span></a></li>
<li class="{{ \Request::routeIs('kazama.admin.settings*') ? 'active' : '' }}"><a href="{{ route('kazama.admin.settings') }}"><i class="fa fa-sliders"></i> <span>Pengaturan Kazama</span></a></li>
@endif
<li class="{{ \Request::routeIs('kazama.admin.expiry*') ? 'active' : '' }}"><a href="{{ route('kazama.admin.expiry') }}"><i class="fa fa-clock-o"></i> <span>Masa Aktif Server</span></a></li>
