<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Hubungkan MCP &middot; {{ config('kazama.brand') }}</title>
<style>
:root{color-scheme:dark}*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;background:#0b0b12;color:#e8e8f0;font:15px/1.5 system-ui,sans-serif}
.card{width:100%;max-width:520px;background:#14141f;border:1px solid #26263a;border-radius:16px;padding:26px}
h1{font-size:20px;margin:0 0 4px}p.m{color:#9a9ab0;margin:0 0 18px;font-size:13px}
label{display:block;font-size:12px;color:#9a9ab0;margin:14px 0 5px}
input[type=text],select{width:100%;padding:10px 12px;border-radius:10px;border:1px solid #2c2c44;background:#0e0e18;color:#fff;font:inherit}
.row{display:flex;gap:10px}.row>*{flex:1}
.srv{max-height:140px;overflow:auto;border:1px solid #2c2c44;border-radius:10px;padding:8px 10px;display:none}
.srv label{margin:4px 0;color:#e8e8f0;font-size:14px}
.sw{display:flex;align-items:center;justify-content:space-between;margin-top:14px}
.btns{display:flex;gap:10px;margin-top:22px}
button{flex:1;padding:12px;border-radius:10px;border:0;font:inherit;font-weight:600;cursor:pointer}
.ok{background:#7c3aed;color:#fff}.no{background:#22223a;color:#cfcfe0}
.err{background:#3b1220;border:1px solid #7f1d3a;padding:12px;border-radius:10px}
a{color:#a78bfa}
</style></head><body>
<div class="card">
@if($error)
  <h1>Permintaan tidak valid</h1><div class="err">{{ $error }}</div>
@else
  <h1>Hubungkan {{ $client->client_name }}</h1>
  <p class="m">Masuk sebagai <strong>{{ $user->username }}</strong>. Aplikasi ini akan bisa mengelola server &amp; akun Anda lewat MCP sesuai pengaturan di bawah. <a href="/guide/mcp" target="_blank">Panduan</a></p>
  <form method="POST" action="/api/mcp/oauth/authorize">
    {{ csrf_field() }}
    @foreach(['response_type','client_id','redirect_uri','code_challenge','code_challenge_method','state','scope'] as $k)
      @if(isset($query[$k]))<input type="hidden" name="{{ $k }}" value="{{ $query[$k] }}">@endif
    @endforeach
    <label>Nama koneksi</label><input type="text" name="name" value="{{ $name }}" maxlength="120">
    <label>Platform</label><select name="platform">@foreach($platforms as $id => $p)<option value="{{ $id }}" {{ $platform===$id?'selected':'' }}>{{ $p[1] }}</option>@endforeach</select>
    <div class="row">
      <div><label>Akses</label><select name="access"><option value="full" {{ $defaults['access']==='full'?'selected':'' }}>Penuh</option><option value="read" {{ $defaults['access']==='read'?'selected':'' }}>Baca saja</option></select></div>
      <div><label>Server</label><select name="servers_mode" id="mode"><option value="all" {{ $defaults['servers_mode']==='all'?'selected':'' }}>Semua server</option><option value="selected" {{ $defaults['servers_mode']==='selected'?'selected':'' }}>Hanya dipilih</option></select></div>
    </div>
    <div class="srv" id="srv">@forelse($servers as $s)<label><input type="checkbox" name="server_ids[]" value="{{ $s['id'] }}" {{ in_array($s['id'],$defaults['server_ids'])?'checked':'' }}> {{ $s['name'] }}</label>@empty<em>Belum ada server.</em>@endforelse</div>
    <div class="row">
      <div><label>Batas aksi per hari</label><select name="daily_limit">@foreach([0=>'Tanpa batas',20=>'20',50=>'50',100=>'100',200=>'200'] as $v=>$t)<option value="{{ $v }}" {{ (int)$defaults['daily_limit']===$v?'selected':'' }}>{{ $t }}</option>@endforeach</select></div>
      <div><label>Putuskan otomatis</label><select name="auto_disconnect_days">@foreach([0=>'Tidak pernah',7=>'7 hari',30=>'30 hari',90=>'90 hari'] as $v=>$t)<option value="{{ $v }}" {{ (int)$defaults['auto_disconnect_days']===$v?'selected':'' }}>{{ $t }}</option>@endforeach</select></div>
    </div>
    <div class="sw"><span>Cadangkan otomatis sebelum aksi berisiko</span><input type="checkbox" name="auto_backup" value="1" {{ $defaults['auto_backup']?'checked':'' }}></div>
    <div class="btns"><button class="no" name="decision" value="deny">Tolak</button><button class="ok" name="decision" value="approve">Izinkan</button></div>
  </form>
  <script>var m=document.getElementById('mode'),s=document.getElementById('srv');function f(){s.style.display=m.value==='selected'?'block':'none'}m.onchange=f;f();</script>
@endif
</div></body></html>
