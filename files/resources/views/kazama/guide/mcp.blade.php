<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Panduan MCP &middot; {{ $brand }}</title>
<style>
:root{color-scheme:dark}*{box-sizing:border-box}
body{margin:0;background:#0b0b12;color:#e8e8f0;font:15px/1.65 system-ui,sans-serif}
.w{max-width:860px;margin:0 auto;padding:28px 18px 60px}
h1{font-size:28px;margin:0 0 6px}h2{font-size:19px;margin:34px 0 10px;padding-top:6px;border-top:1px solid #1f1f31}h3{font-size:15px;margin:18px 0 6px;color:#c4b5fd}
p,li{color:#c9c9d8}code,pre{font-family:ui-monospace,Menlo,monospace;font-size:13px}
code{background:#1a1a2a;padding:2px 6px;border-radius:6px;color:#e9d5ff}
pre{background:#10101a;border:1px solid #26263a;border-radius:12px;padding:14px;overflow:auto;color:#e8e8f0}
.pill{display:inline-block;padding:3px 10px;border-radius:99px;background:rgba(124,58,237,.18);color:#c4b5fd;font-size:12px;font-weight:600;margin-right:6px}
.box{background:#14141f;border:1px solid #26263a;border-radius:14px;padding:14px 16px;margin:14px 0}
.warn{border-color:#7c5a12;background:#1d1708}
table{width:100%;border-collapse:collapse;font-size:13px}td,th{padding:7px 8px;border-bottom:1px solid #1f1f31;text-align:left;vertical-align:top}
a{color:#a78bfa}.nav a{margin-right:14px}
</style></head><body><div class="w">
<div class="nav"><a href="/">&larr; Dashboard</a><a href="/mcp">Menu MCP</a></div>
<h1>Panduan MCP</h1>
<p>Hubungkan AI favorit Anda ke <strong>{{ $brand }}</strong> untuk menyalakan/mematikan server, mengelola file, backup, database, jadwal, dan lainnya dengan bahasa sehari-hari. Halaman ini publik; bila Anda sudah login, isinya disesuaikan dengan akun Anda.</p>

@if($personal)
<div class="box"><span class="pill">{{ ['super'=>'Admin utama','admin'=>'Admin','limited'=>$protect,'user'=>'User'][$role] }}</span>
 Akses bawaan: <strong>{{ $access === 'read' ? 'Baca saja' : 'Penuh' }}</strong> &middot; Alat tersedia untuk Anda: <strong>{{ count($tools) }}</strong>
 @if($role==='limited')<p>Akun Anda memakai <strong>{{ $protect }}</strong>: alat admin hanya menjangkau server &amp; user yang Anda buat sendiri, dan tidak bisa menghapus server/user.</p>@endif
 @if($role==='admin')<p>Anda admin: tersedia alat admin (buat/ubah user &amp; server, suspend, masa aktif). Penghapusan server hanya untuk admin utama.</p>@endif
 @if($role==='user')<p>Anda user biasa: AI hanya bisa mengelola server milik Anda (dan server yang dibagikan kepada Anda sesuai izin subuser).</p>@endif
</div>
@else
<div class="box">Anda belum login. <a href="/auth/login">Masuk</a> untuk melihat panduan yang disesuaikan dengan peran dan pengaturan akun Anda.</div>
@endif

<h2>1. Alamat server MCP</h2>
<pre>{{ $endpoint }}</pre>

<h2>2. Cara menghubungkan</h2>
<h3>Otomatis (OAuth) &mdash; disarankan</h3>
<p>Tambahkan alamat di atas sebagai remote MCP server di aplikasi AI Anda. Saat diminta login, halaman persetujuan {{ $brand }} akan terbuka: atur akses, server, batas harian, lalu <em>Izinkan</em>. Nama koneksi default: <code>{{ $defaultName }}</code>.</p>
<h3>Manual (token)</h3>
<p>Buka menu <strong>MCP &rarr; Hubungkan &rarr; Manual</strong>, pilih platform, salin token <code>kzm_...</code> (hanya tampil sekali), lalu pasang sebagai header <code>Authorization: Bearer TOKEN</code>.</p>

<h2>3. Contoh konfigurasi per platform</h2>
<div class="box warn">Format konfigurasi tiap aplikasi bisa berubah antar versi. Bila tidak cocok, cek dokumentasi resmi aplikasinya; yang dibutuhkan hanya URL <code>{{ $endpoint }}</code> dan (untuk manual) header Bearer.</div>
<h3>Claude (claude.ai / Desktop)</h3><p>Pengaturan &rarr; Konektor &rarr; Tambah konektor kustom &rarr; tempel URL, lalu login.</p>
<h3>Claude Code</h3><pre>claude mcp add --transport http kazama {{ $endpoint }}
# manual:
claude mcp add --transport http kazama {{ $endpoint }} --header "Authorization: Bearer TOKEN"</pre>
<h3>ChatGPT</h3><p>Pengaturan &rarr; Konektor (mode developer) &rarr; buat konektor MCP &rarr; URL di atas, autentikasi OAuth.</p>
<h3>Codex</h3><pre># ~/.codex/config.toml
[mcp_servers.kazama]
url = "{{ $endpoint }}"
bearer_token_env_var = "KAZAMA_MCP_TOKEN"</pre>
<h3>Cursor</h3><pre>// .cursor/mcp.json
{"mcpServers":{"kazama":{"url":"{{ $endpoint }}","headers":{"Authorization":"Bearer TOKEN"}}}}</pre>
<h3>VS Code</h3><pre>// .vscode/mcp.json
{"servers":{"kazama":{"type":"http","url":"{{ $endpoint }}","headers":{"Authorization":"Bearer TOKEN"}}}}</pre>
<h3>Gemini CLI</h3><pre>// ~/.gemini/settings.json
{"mcpServers":{"kazama":{"httpUrl":"{{ $endpoint }}","headers":{"Authorization":"Bearer TOKEN"}}}}</pre>
<h3>Antigravity</h3><pre>// mcp_config.json
{"mcpServers":{"kazama":{"serverUrl":"{{ $endpoint }}","headers":{"Authorization":"Bearer TOKEN"}}}}</pre>
<h3>OpenCode</h3><pre>// opencode.json
{"mcp":{"kazama":{"type":"remote","url":"{{ $endpoint }}","headers":{"Authorization":"Bearer TOKEN"}}}}</pre>

<h2>4. Pengaturan koneksi</h2>
<table>
<tr><td><strong>Akses</strong></td><td>Penuh, atau Baca saja (alat yang mengubah data disembunyikan).</td></tr>
<tr><td><strong>Server</strong></td><td>Semua server, atau hanya yang dipilih.</td></tr>
<tr><td><strong>Batas aksi per hari</strong></td><td>Tanpa batas, 20, 50, 100, atau 200 (reset 00:00 WIB).</td></tr>
<tr><td><strong>Putuskan otomatis</strong></td><td>Tidak pernah, 7, 30, atau 90 hari tanpa dipakai.</td></tr>
<tr><td><strong>Cadangkan otomatis</strong></td><td>Default aktif: sebelum aksi berisiko (tulis file, restore, instal ulang, dll.) dibuat backup dulu; bila gagal, aksi dibatalkan.</td></tr>
</table>

<h2>5. Cara AI bekerja (aturan keselamatan)</h2>
<ul>
<li>AI <strong>selalu menanyakan server</strong> bila Anda tidak menyebutnya &mdash; tidak menebak.</li>
<li>Bila permintaan kurang jelas, AI bertanya dulu &mdash; tidak asal membuat.</li>
<li>Aksi berisiko (hapus, restore, kill, instal ulang, ganti password, dll.) butuh persetujuan eksplisit Anda.</li>
<li>File yang dihapus masuk <strong>Trash</strong> 7 hari dan bisa dipulihkan.</li>
<li>Setiap aksi tampil di console server, contoh <code>[Claude-MCP]: Restart Server</code>, dan di tab Aktivitas.</li>
<li>Semua aksi dijalankan dengan izin akun Anda sendiri; AI tidak bisa melebihi hak akses Anda.</li>
</ul>

<h2>6. Daftar alat {{ $personal ? '(untuk akun Anda)' : '' }}</h2>
@foreach($groups as $gid => $glabel)
  @php($rows = collect($tools)->filter(fn($t) => $t['group'] === $gid))
  @if($rows->count())
  <h3>{{ $glabel }}</h3>
  <table>@foreach($rows as $t)<tr><td><code>{{ $t['name'] }}</code></td><td>{{ $t['desc'] }}@if($t['confirm']) <span class="pill">konfirmasi</span>@endif @if(!$t['write']) <span class="pill">baca</span>@endif</td></tr>@endforeach</table>
  @endif
@endforeach

<p style="margin-top:40px;color:#7a7a92;font-size:12px">&copy; {{ date('Y') }} {{ $brand }} &middot; Theme oleh {{ config('kazama.developer') }} &middot; <a href="{{ config('kazama.developer_telegram') }}">Telegram</a></p>
</div></body></html>
