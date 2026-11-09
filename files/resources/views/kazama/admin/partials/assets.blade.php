<link rel="stylesheet" href="/kazama/admin.css?v=2">
<style>
.sidebar-menu li.kz-locked a{opacity:.45;cursor:not-allowed}
.sidebar-menu li.kz-locked a:after{content:"\f023";font-family:FontAwesome;float:right;font-size:11px;margin-top:3px}
.kz-protect{display:inline-block;margin:6px 15px;padding:3px 9px;border-radius:99px;background:rgba(124,58,237,.18);color:#c4b5fd;font-size:11px;font-weight:600}
</style>
@php($kzUser = auth()->user())
@if(\Pterodactyl\Kazama\Support\Restrict::applies($kzUser))
<script>
document.addEventListener('DOMContentLoaded', function () {
    var allow = @json(\Pterodactyl\Kazama\Support\Restrict::SIDEBAR_ALLOW);
    var menu = document.querySelector('.sidebar-menu');
    if (menu) {
        var b = document.createElement('li'); b.innerHTML = '<span class="kz-protect"><i class="fa fa-shield"></i> {{ e(\Pterodactyl\Kazama\Support\Restrict::name()) }}</span>';
        menu.insertBefore(b, menu.firstChild);
    }
    document.querySelectorAll('.sidebar-menu > li').forEach(function (li) {
        var a = li.querySelector('a'); if (!a) return;
        var p = (a.getAttribute('href') || '').replace(location.origin, '').replace(/\/$/, '');
        if (allow.indexOf(p) === -1) {
            li.classList.add('kz-locked');
            a.setAttribute('title', '{{ e(\Pterodactyl\Kazama\Support\Restrict::name()) }}: akses dikunci');
            a.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); });
        }
    });
});
</script>
@endif
<script>
// Setiap suspend / hapus server wajib diberi keterangan; nama admin dicatat otomatis di server & Logs.
document.addEventListener('submit', function (e) {
    var f = e.target; if (!f || !f.action || f.dataset.kzAsked) return;
    var act = f.querySelector('input[name=action]');
    var isSuspend = /\/manage\/suspension$/.test(f.action) && act && act.value === 'suspend';
    var isDelete = /\/servers\/view\/\d+\/delete$/.test(f.action);
    if (!isSuspend && !isDelete) return;
    e.preventDefault();
    var r = window.prompt(isSuspend ? 'Keterangan suspend (ditampilkan ke pemilik server):' : 'Keterangan penghapusan (ditampilkan ke pemilik server selama 1 hari):');
    if (r === null) return;
    r = r.trim(); if (!r) { alert('Keterangan wajib diisi.'); return; }
    var i = document.createElement('input'); i.type = 'hidden'; i.name = 'kazama_reason'; i.value = r; f.appendChild(i);
    f.dataset.kzAsked = '1'; f.submit();
}, true);
</script>
