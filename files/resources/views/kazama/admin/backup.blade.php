@extends('layouts.admin')
@section('title') Panel Backup @endsection
@section('content-header')
    <h1>Panel &amp; Cloud Backup<small>Kelola backup data panel, auto-backup, dan integrasi Telegram Bot / Cloud Storage.</small></h1>
    <ol class="breadcrumb"><li><a href="{{ route('admin.index') }}">Admin</a></li><li class="active">Panel Backup</li></ol>
@endsection
@section('content')
<div class="row">
  <div class="col-xs-12">
    <div class="box box-primary">
      <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-database"></i> Data Backup Panel</h3></div>
      <div class="box-body">
        <p class="text-muted">Arsip berisi database MySQL &amp; kunci enkripsi <code>.env</code>. Siap untuk migrasi antar VPS / instalasi ulang panel.</p>
        <form action="{{ route('kazama.admin.backup.store') }}" method="POST" style="display:inline">{{ csrf_field() }}
          <button class="btn btn-success"><i class="fa fa-plus-circle"></i> Buat Backup Baru</button>
        </form>
        <button class="btn btn-warning" data-toggle="modal" data-target="#kzRestore"><i class="fa fa-upload"></i> Upload &amp; Restore</button>
      </div>
      <div class="box-body table-responsive no-padding">
        <table class="table table-hover">
          <thead><tr><th>Nama File Backup</th><th>Ukuran</th><th>Dibuat (WIB)</th><th class="text-right">Aksi</th></tr></thead>
          <tbody>
          @forelse($files as $f)
            <tr>
              <td><code>{{ $f['name'] }}</code></td>
              <td>{{ number_format($f['size'] / 1048576, 2) }} MB</td>
              <td>{{ \Carbon\Carbon::createFromTimestamp($f['mtime'], config('kazama.timezone'))->format('d M Y H:i:s') }} WIB</td>
              <td class="text-right">
                <a class="btn btn-xs btn-primary" href="{{ route('kazama.admin.backup.download', $f['name']) }}"><i class="fa fa-download"></i></a>
                <button class="btn btn-xs btn-warning kz-restore" data-name="{{ $f['name'] }}"><i class="fa fa-undo"></i></button>
                <form action="{{ route('kazama.admin.backup.delete', $f['name']) }}" method="POST" style="display:inline" onsubmit="return confirm('Hapus backup ini?')">{{ csrf_field() }}{{ method_field('DELETE') }}
                  <button class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-center text-muted">Belum ada backup.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-md-8">
    <div class="box box-info">
      <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-clock-o"></i> Auto-Backup Panel Otomatis</h3></div>
      <form action="{{ route('kazama.admin.backup.settings') }}" method="POST">{{ csrf_field() }}
      <div class="box-body">
        <div class="form-group"><label><input type="checkbox" name="auto" value="1" {{ $s['auto'] ? 'checked' : '' }}> Aktifkan auto-backup (dijalankan scheduler / cron panel)</label></div>
        <div class="row">
          <div class="form-group col-sm-4"><label>Frekuensi</label>
            <select name="frequency" class="form-control">
              @foreach(['hourly' => 'Setiap jam', 'daily' => 'Harian', 'weekly' => 'Mingguan'] as $k => $v)
                <option value="{{ $k }}" {{ $s['frequency'] === $k ? 'selected' : '' }}>{{ $v }}</option>
              @endforeach
            </select></div>
          <div class="form-group col-sm-3"><label>Jam (WIB)</label><input type="time" name="time" value="{{ $s['time'] }}" class="form-control"><p class="text-muted small">Untuk "setiap jam" hanya menit yang dipakai.</p></div>
          <div class="form-group col-sm-3"><label>Hari (mingguan)</label>
            <select name="weekday" class="form-control">
              @foreach(['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'] as $i => $d)
                <option value="{{ $i }}" {{ $s['weekday'] === $i ? 'selected' : '' }}>{{ $d }}</option>
              @endforeach
            </select></div>
          <div class="form-group col-sm-2"><label>Simpan</label><input type="number" name="keep" min="1" max="60" value="{{ $s['keep'] }}" class="form-control"></div>
        </div>
        <p class="text-muted">Backup berikutnya: <strong>{{ $s['next'] ?? '-' }}</strong></p>
        <hr>
        <h4><i class="fa fa-paper-plane"></i> Telegram Bot</h4>
        <div class="form-group"><label><input type="checkbox" name="telegram_enabled" value="1" {{ $s['tg_enabled'] ? 'checked' : '' }}> Kirim setiap backup ke Telegram (maks. 49 MB)</label></div>
        <div class="row">
          <div class="form-group col-sm-6"><label>Bot Token</label><input type="password" name="telegram_bot_token" value="{{ $s['tg_token'] }}" class="form-control" autocomplete="off"></div>
          <div class="form-group col-sm-6"><label>Chat ID</label><input type="text" name="telegram_chat_id" value="{{ $s['tg_chat'] }}" class="form-control"></div>
        </div>
      </div>
      <div class="box-footer"><button class="btn btn-primary pull-right">Simpan Pengaturan</button></div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="kzRestore" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form action="{{ route('kazama.admin.backup.restore') }}" method="POST" enctype="multipart/form-data">{{ csrf_field() }}
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Restore Backup</h4></div>
    <div class="modal-body">
      <div class="callout callout-danger">Restore akan <strong>menimpa seluruh database</strong> dan file <code>.env</code> panel.</div>
      <input type="hidden" name="name" id="kzRestoreName">
      <p id="kzRestoreLabel" class="text-muted"></p>
      <div class="form-group"><label>Atau upload arsip (.tar.gz)</label><input type="file" name="archive" accept=".gz"></div>
      <div class="form-group"><label>Ketik <code>RESTORE</code> untuk konfirmasi</label><input type="text" name="confirm" class="form-control" autocomplete="off"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-danger">Restore Sekarang</button></div>
  </form></div></div></div>
@endsection
@section('footer-scripts')
  @parent
  <script>
    $('.kz-restore').on('click', function () {
      $('#kzRestoreName').val($(this).data('name'));
      $('#kzRestoreLabel').text('File dipilih: ' + $(this).data('name'));
      $('#kzRestore').modal('show');
    });
  </script>
@endsection
