<link rel="stylesheet" href="/kazama/admin.css?v=1">
@if(\Pterodactyl\Kazama\Support\Restrict::applies(auth()->user()))
<script>
document.addEventListener('DOMContentLoaded', function () {
    var allow = ['/admin', '/admin/users', '/admin/servers', '/admin/kazama/expiry'];
    var headers = ['KAZAMA CLOUD', 'BASIC ADMINISTRATION', 'MANAGEMENT'];
    document.querySelectorAll('.sidebar-menu > li').forEach(function (li) {
        if (li.classList.contains('header')) {
            if (headers.indexOf(li.textContent.trim()) === -1) li.style.display = 'none';
            return;
        }
        var a = li.querySelector('a'); if (!a) return;
        var p = (a.getAttribute('href') || '').replace(location.origin, '').replace(/\/$/, '');
        if (allow.indexOf(p) === -1) li.style.display = 'none';
    });
});
</script>
@endif
