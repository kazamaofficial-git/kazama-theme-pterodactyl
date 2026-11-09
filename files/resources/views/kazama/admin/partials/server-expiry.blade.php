@php
    $kzExp = $server->getRawOriginal('kazama_expires_at');
    $kzLocal = $kzExp ? \Carbon\Carbon::parse($kzExp, 'UTC')->setTimezone(config('kazama.timezone'))->format('Y-m-d\TH:i') : '';
@endphp
<div class="row"><div class="col-md-6"><div class="box box-info">
  <div class="box-header with-border"><h3 class="box-title">Masa Aktif (Kazama Cloud)</h3></div>
  <form action="{{ route('admin.servers.view.details', $server->id) }}" method="POST">
    {!! csrf_field() !!}{!! method_field('PATCH') !!}
    <input type="hidden" name="kazama_form" value="expiry">
    <div class="box-body">
      <p>Berlaku hingga: <strong>{{ $kzExp ? \Pterodactyl\Kazama\Support\Cadence::wib($kzExp) : 'tanpa batas' }}</strong>
        @if($server->kazama_expired)<span class="label label-danger">Kedaluwarsa</span>@endif</p>
      @if($server->kazama_suspended_by)
        <p class="text-muted">Disuspend oleh <strong>{{ $server->kazama_suspended_by }}</strong> &mdash; {{ $server->kazama_suspend_reason }}</p>
      @endif
      <div class="form-group"><label>Tanggal &amp; jam berakhir (WIB)</label><input type="datetime-local" name="kazama_expires_at" value="{{ $kzLocal }}" class="form-control"></div>
      <div class="form-group"><label>atau perpanjang (hari)</label><input type="number" name="kazama_extend_days" min="1" max="365" class="form-control" placeholder="30"></div>
    </div>
    <div class="box-footer"><button class="btn btn-info pull-right">Simpan Masa Aktif</button></div>
  </form>
</div></div></div>
