@extends('layouts.admin')
@section('title') Masa Aktif Server @endsection
@section('content-header')
    <h1>Masa Aktif Server<small>Server otomatis disuspend saat expired dan dihapus bila tidak diperpanjang.</small></h1>
    <ol class="breadcrumb"><li><a href="{{ route('admin.index') }}">Admin</a></li><li class="active">Masa Aktif</li></ol>
@endsection
@section('content')
<div class="row"><div class="col-xs-12"><div class="box box-primary">
  <div class="box-header with-border"><h3 class="box-title">Daftar Server</h3></div>
  <div class="box-body table-responsive no-padding">
    <table class="table table-hover">
      <thead><tr><th>Server</th><th>Pemilik</th><th>Berakhir (WIB)</th><th>Status</th><th class="text-right">Perpanjang</th></tr></thead>
      <tbody>
      @forelse($servers as $s)
        @php($exp = $s->getRawOriginal('kazama_expires_at'))
        <tr>
          <td><a href="{{ route('admin.servers.view', $s->id) }}">{{ $s->name }}</a></td>
          <td>{{ $s->user->email ?? '-' }}</td>
          <td>{{ \Pterodactyl\Kazama\Support\Cadence::wib($exp) }}</td>
          <td>
            @if(!$exp) <span class="label label-default">Tanpa batas</span>
            @elseif($s->kazama_expired) <span class="label label-danger">Expired / Suspend</span>
            @elseif(\Carbon\Carbon::parse($exp, 'UTC')->diffInDays(now('UTC'), false) > -4) <span class="label label-warning">Segera berakhir</span>
            @else <span class="label label-success">Aktif</span> @endif
          </td>
          <td class="text-right">
            <form action="{{ route('kazama.admin.expiry.extend', $s->id) }}" method="POST" class="form-inline">{{ csrf_field() }}
              <input type="number" name="days" value="30" min="1" max="365" class="form-control input-sm" style="width:80px"> hari
              <button class="btn btn-sm btn-success"><i class="fa fa-plus"></i></button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="text-center text-muted">Belum ada server.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="box-footer">{{ $servers->links() }}</div>
</div></div></div>
@endsection
