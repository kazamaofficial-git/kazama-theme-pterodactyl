#!/usr/bin/env python3
"""Patch idempotent untuk file inti Pterodactyl. Pemakaian: patch.py <direktori panel>"""
import re, sys, pathlib

root = pathlib.Path(sys.argv[1])
MARK = 'kazama'

def edit(rel, fn):
    p = root / rel
    if not p.exists():
        print(f'[LEWATI] {rel} tidak ditemukan'); return
    s = p.read_text()
    n = fn(s)
    if n is None:
        print(f'[SUDAH]  {rel}'); return
    if n == s:
        print(f'[GAGAL]  {rel} (pola tidak cocok, patch manual diperlukan)'); return
    p.write_text(n); print(f'[OK]     {rel}')

# 1. Service provider
def provider(s):
    if 'KazamaServiceProvider' in s: return None
    return re.sub(r"(\n\s*Pterodactyl\\Providers\\ViewComposerServiceProvider::class,)",
                  r"\1\n        Pterodactyl\\Kazama\\KazamaServiceProvider::class,", s, count=1) \
        if 'ViewComposerServiceProvider::class' in s else \
        s.replace("'providers' => [", "'providers' => [\n        Pterodactyl\\Kazama\\KazamaServiceProvider::class,", 1)
edit('config/app.php', provider)

# 2. Routing frontend auth: ganti halaman login + tambah register
def authrouter(s):
    if 'RegisterContainer' in s: return None
    s = s.replace("import LoginContainer from '@/components/auth/LoginContainer';",
                  "import LoginContainer from '@/components/auth/LoginContainer';\nimport RegisterContainer from '@/components/auth/RegisterContainer';", 1)
    return s.replace("<Route path={`${path}/login`} component={LoginContainer} exact />",
                     "<Route path={`${path}/login`} component={LoginContainer} exact />\n                <Route path={`${path}/register`} component={RegisterContainer} exact />", 1)
edit('resources/scripts/routers/AuthenticationRouter.tsx', authrouter)

# 3. Kotak Auto-Backup di Settings server
def settings(s):
    if 'AutoBackupBox' in s: return None
    s = s.replace("import ServerContext", "import ServerContext", 1)
    s = re.sub(r"(import [^\n]+\n)(?!import)", r"\1import AutoBackupBox from '@/components/server/settings/AutoBackupBox';\n", s, count=1)
    return re.sub(r"(<ReinstallServerBox\s*/>)", r"<AutoBackupBox />\n                    \1", s, count=1) \
        if '<ReinstallServerBox' in s else s
edit('resources/scripts/components/server/settings/SettingsContainer.tsx', settings)

# 4. Layout admin: CSS, menu Kazama, footer + copyright
def layout(s):
    if 'kazama::admin.partials' in s: return None
    s = s.replace('</head>', "    @include('kazama::admin.partials.assets')\n    </head>", 1)
    s = re.sub(r'(<ul class="sidebar-menu">)', r"\1\n                        @include('kazama::admin.partials.menu')", s, count=1)
    s = s.replace('</body>', "    @include('kazama::admin.partials.footer')\n    </body>", 1)
    return s
edit('resources/views/layouts/admin.blade.php', layout)

# 5. Judul / nama brand di halaman auth & wrapper
def wrapper(s):
    return None if 'Kazama' in s else s.replace('<title>{{ config(\'app.name\', \'Pterodactyl\') }}</title>',
        '<title>{{ config(\'app.name\', \'Kazama Cloud\') }}</title>')
edit('resources/views/templates/wrapper.blade.php', wrapper)
