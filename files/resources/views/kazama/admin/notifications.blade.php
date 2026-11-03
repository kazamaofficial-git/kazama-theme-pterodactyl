@extends('layouts.admin')
@section('title') Notifikasi @endsection
@section('content-header')
    <h1>Notifikasi<small>Kirim info ke dashboard user.</small></h1>
    <ol class="breadcrumb"><li><a href="{{ route('admin.index') }}">Admin</a></li><li class="active">Notifikasi</li></ol>
@endsection
@section('content')
<div class="row">
  <div class="col-md-4"><div class="box box-primary">
    <div class="box-header with-border"><h3 class="box-title">Kirim Notifikasi</h3></div>
    <form action="{{ route('kazama.admin.notifications.send') }}" method="POST">{{ csrf_field() }}
    <div class="box-body">
      <div class="form-group"><label>Tujuan</label><select name="target" class="form-control" id="kzTarget"><option value="all">Semua user</option><option value="email">Satu user (email)</option></select></div>
      <div class="form-group" id="kzEmail" style="display:none"><label>Email user</label><input type="email" name="email" class="form-control"></div>
      <div class="form-group"><label>Jenis</label><select name="type" class="form-control"><option value="info">Info</option><option value="success">Sukses</option><option value="warning">Peringatan</option><option value="danger">Penting</option></select></div>
      <div class="form-group"><label>Judul</label><input type="text" name="title" class="form-control" maxlength="120" required></div>
      <div class="form-group"><label>Pesan</label><textarea name="message" class="form-control" rows="4" maxlength="1000" required></textarea></div>
    </div>
    <div class="box-footer"><button class="btn btn-primary pull-right">Kirim</button></div>
    </form>
  </div></div>
  <div class="col-md-8"><div class="box box-info">
    <div class="box-header with-border"><h3 class="box-title">50 Notifikasi Terakhir</h3></div>
    <div class="box-body table-responsive no-padding"><table class="table table-hover">
      <thead><tr><th>Waktu (WIB)</th><th>Judul</th><th>Tujuan</th><th></th></tr></thead>
      <tbody>
      @forelse($items as $n)
        <tr>
          <td>{{ \Pterodactyl\Kazama\Support\Cadence::wib($n->created_at) }}</td>
          <td><span class="label label-{{ ['info'=>'info','success'=>'success','warning'=>'warning','danger'=>'danger'][$n->type] ?? 'default' }}">{{ $n->type }}</span> <strong>{{ $n->title }}</strong><br><small class="text-muted">{{ $n->message }}</small></td>
          <td>{{ $n->user_id ? 'User #'.$n->user_id : 'Semua' }}</td>
          <td><form action="{{ route('kazama.admin.notifications.delete', $n->id) }}" method="POST">{{ csrf_field() }}{{ method_field('DELETE') }}<button class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button></form></td>
        </tr>
      @empty
        <tr><td colspan="4" class="text-center text-muted">Belum ada notifikasi.</td></tr>
      @endforelse
      </tbody></table></div>
  </div></div>
</div>
@endsection
@section('footer-scripts')
  @parent
  <script>$('#kzTarget').on('change', function () { $('#kzEmail').toggle(this.value === 'email'); });</script>
@endsection
