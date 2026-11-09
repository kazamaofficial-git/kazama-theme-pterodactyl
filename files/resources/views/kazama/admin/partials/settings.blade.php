@php
    $kzSuper = \Pterodactyl\Kazama\Support\Restrict::isSuper(auth()->user());
    $kzS = fn ($k, $d = '') => \Pterodactyl\Kazama\Support\Settings::get($k, $d);
    $kzOn = fn ($k) => \Pterodactyl\Kazama\Support\Settings::bool($k, true);
@endphp
@if($kzSuper)
<div class="row"><div class="col-xs-12"><div class="box box-primary">
  <div class="box-header with-border"><h3 class="box-title">Kazama Cloud</h3></div>
  <form action="{{ route('admin.settings') }}" method="POST">
    {!! csrf_field() !!}{!! method_field('PATCH') !!}
    <input type="hidden" name="kazama_form" value="settings">
    <div class="box-body"><div class="row">
      <div class="form-group col-md-4"><label>Nama akses terbatas</label><input name="protect_name" class="form-control" value="{{ $kzS('protect_name', config('kazama.protect_name')) }}" required></div>
      <div class="form-group col-md-4"><label>Nama koneksi MCP otomatis</label><input name="mcp_default_name" class="form-control" value="{{ $kzS('mcp_default_name', config('kazama.mcp.default_name')) }}" required></div>
      <div class="form-group col-md-2"><label>Masa aktif baru (hari)</label><input type="number" name="active_days" class="form-control" value="{{ $kzS('active_days', config('kazama.server.active_days')) }}" required></div>
      <div class="form-group col-md-2"><label>Masa tenggang (hari)</label><input type="number" name="grace_days" class="form-control" value="{{ $kzS('grace_days', config('kazama.server.grace_days')) }}" required></div>
      <div class="form-group col-md-6"><label>Telegram admin</label><input type="url" name="contact_telegram" class="form-control" value="{{ $kzS('contact_telegram', config('kazama.contact.telegram')) }}"></div>
      <div class="form-group col-md-6"><label>WhatsApp admin</label><input type="url" name="contact_whatsapp" class="form-control" value="{{ $kzS('contact_whatsapp', config('kazama.contact.whatsapp')) }}"></div>
    </div>
    <hr>
    <div class="row">
      <div class="col-md-6"><h4>Login Google</h4>
        <label><input type="checkbox" name="oauth_google_enabled" value="1" {{ $kzOn('oauth_google_enabled') ? 'checked' : '' }}> Aktifkan</label>
        <div class="form-group"><label>Client ID</label><input name="oauth_google_id" class="form-control" value="{{ $kzS('oauth_google_id') }}"></div>
        <div class="form-group"><label>Client Secret <small class="text-muted">(kosongkan bila tidak diubah)</small></label><input type="password" name="oauth_google_secret" class="form-control" autocomplete="new-password"></div>
        <small class="text-muted">Callback: {{ \Pterodactyl\Kazama\Support\OAuth::redirectUri('google') }}</small>
      </div>
      <div class="col-md-6"><h4>Login GitHub</h4>
        <label><input type="checkbox" name="oauth_github_enabled" value="1" {{ $kzOn('oauth_github_enabled') ? 'checked' : '' }}> Aktifkan</label>
        <div class="form-group"><label>Client ID</label><input name="oauth_github_id" class="form-control" value="{{ $kzS('oauth_github_id') }}"></div>
        <div class="form-group"><label>Client Secret <small class="text-muted">(kosongkan bila tidak diubah)</small></label><input type="password" name="oauth_github_secret" class="form-control" autocomplete="new-password"></div>
        <small class="text-muted">Callback: {{ \Pterodactyl\Kazama\Support\OAuth::redirectUri('github') }}</small>
      </div>
    </div>
    <hr><label><input type="checkbox" name="registration_enabled" value="1" {{ \Pterodactyl\Kazama\Support\OAuth::registrationOpen() ? 'checked' : '' }}> Izinkan pendaftaran akun baru</label>
    </div>
    <div class="box-footer"><button class="btn btn-primary pull-right">Simpan Kazama</button></div>
  </form>
</div></div></div>
@endif
