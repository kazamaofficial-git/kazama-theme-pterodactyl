@extends('layouts.admin')
@section('title') Logs @endsection
@section('content-header')
    <h1>Logs<small>Siapa melakukan apa (WIB).</small></h1>
    <ol class="breadcrumb"><li><a href="{{ route('admin.index') }}">Admin</a></li><li class="active">Logs</li></ol>
@endsection
@section('content')
<div class="row"><div class="col-xs-12"><div class="box box-primary">
  <div class="box-header with-border">
    <form method="GET" class="form-inline">
      <select name="source" class="form-control"><option value="">Semua sumber</option>@foreach(['admin'=>'Admin','user'=>'User','mcp'=>'MCP','system'=>'Sistem'] as $k=>$v)<option value="{{ $k }}" {{ request('source')===$k?'selected':'' }}>{{ $v }}</option>@endforeach</select>
      <input name="actor" value="{{ request('actor') }}" class="form-control" placeholder="Nama pelaku">
      <input name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari aksi / server / detail">
      <button class="btn btn-primary">Filter</button>
    </form>
  </div>
  <div class="box-body table-responsive no-padding"><table class="table table-hover">
    <thead><tr><th>Waktu</th><th>Pelaku</th><th>Aksi</th><th>Server</th><th>Target</th><th>Detail</th><th>Status</th></tr></thead>
    <tbody>
    @forelse($logs as $l)
      <tr>
        <td style="white-space:nowrap">{{ \Pterodactyl\Kazama\Support\Cadence::wib($l->created_at) }}</td>
        <td><strong>{{ $l->actor_name }}</strong> <span class="label label-{{ ['admin'=>'primary','user'=>'default','mcp'=>'info','system'=>'warning'][$l->source] ?? 'default' }}">{{ $l->source }}</span></td>
        <td><code>{{ $l->action }}</code></td>
        <td>{{ $l->server_name }}</td>
        <td>{{ $l->target }}</td>
        <td style="max-width:360px;word-break:break-word"><small>{{ $l->detail }}</small></td>
        <td><span class="label label-{{ $l->status==='ok'?'success':($l->status==='pending'?'warning':'danger') }}">{{ $l->status }}</span></td>
      </tr>
    @empty
      <tr><td colspan="7" class="text-center text-muted">Belum ada log.</td></tr>
    @endforelse
    </tbody></table></div>
  <div class="box-footer">{{ $logs->links() }}</div>
</div></div></div>
@endsection
@section('footer-scripts')
    @parent
@endsection
