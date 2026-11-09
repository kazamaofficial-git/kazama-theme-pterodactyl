<li class="header">KAZAMA CLOUD</li>
<li class="{{ \Request::routeIs('kazama.admin.logs*') ? 'active' : '' }}"><a href="{{ route('kazama.admin.logs') }}"><i class="fa fa-history"></i> <span>Logs</span></a></li>
<li class="{{ \Request::routeIs('kazama.admin.backup*') ? 'active' : '' }}"><a href="{{ route('kazama.admin.backup') }}"><i class="fa fa-database"></i> <span>Panel Backup</span></a></li>
<li class="{{ \Request::routeIs('kazama.admin.notifications*') ? 'active' : '' }}"><a href="{{ route('kazama.admin.notifications') }}"><i class="fa fa-bell"></i> <span>Notifikasi</span></a></li>
