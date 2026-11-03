#!/usr/bin/env bash
# Kazama Cloud Theme - installer. Jalankan sebagai root dari folder repo:  bash install.sh [/var/www/pterodactyl]
set -euo pipefail

PANEL="${1:-/var/www/pterodactyl}"
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PANEL/storage/kazama-theme-backup-$STAMP"

[ -f "$PANEL/artisan" ] || { echo "Panel Pterodactyl tidak ditemukan di $PANEL"; exit 1; }
command -v php >/dev/null || { echo "php tidak ada"; exit 1; }
command -v python3 >/dev/null || { echo "python3 tidak ada"; exit 1; }
command -v node >/dev/null || { echo "node tidak ada (butuh Node 18+)"; exit 1; }
command -v yarn >/dev/null || npm i -g yarn

echo "==> Backup file yang akan ditimpa ke $BACKUP"
mkdir -p "$BACKUP"
( cd "$HERE/files" && find . -type f | while read -r f; do
    if [ -f "$PANEL/$f" ]; then mkdir -p "$BACKUP/$(dirname "$f")"; cp -a "$PANEL/$f" "$BACKUP/$f"; fi
  done )
for f in config/app.php resources/scripts/routers/AuthenticationRouter.tsx resources/scripts/components/server/settings/SettingsContainer.tsx resources/views/layouts/admin.blade.php resources/views/templates/wrapper.blade.php; do
    [ -f "$PANEL/$f" ] && { mkdir -p "$BACKUP/$(dirname "$f")"; cp -an "$PANEL/$f" "$BACKUP/$f"; }
done

echo "==> Maintenance mode"
cd "$PANEL"; php artisan down || true

echo "==> Menyalin file theme"
cp -a "$HERE/files/." "$PANEL/"
python3 "$HERE/scripts/patch.py" "$PANEL"

# Bahasa panel: set default Indonesia bila belum diatur
grep -q '^APP_LOCALE=' .env 2>/dev/null && sed -i 's/^APP_LOCALE=.*/APP_LOCALE=id/' .env || echo 'APP_LOCALE=id' >> .env
grep -q '^APP_TIMEZONE=' .env 2>/dev/null && sed -i 's/^APP_TIMEZONE=.*/APP_TIMEZONE=UTC/' .env || true
grep -q '^KAZAMA_' .env || cat >> .env <<'ENV'

# --- Kazama Cloud ---
KAZAMA_CONTACT_TELEGRAM=https://t.me/DhilzzKZ
KAZAMA_CONTACT_WHATSAPP=
KAZAMA_REGISTRATION=true
KAZAMA_GOOGLE_CLIENT_ID=
KAZAMA_GOOGLE_CLIENT_SECRET=
KAZAMA_GOOGLE_REDIRECT=
ENV

echo "==> Migrasi database & cache"
php artisan migrate --force
php artisan kazama:backfill --days=30 || true
php artisan config:clear; php artisan view:clear; php artisan route:clear; php artisan cache:clear

echo "==> Build frontend (butuh beberapa menit)"
export NODE_OPTIONS=--openssl-legacy-provider
yarn install --frozen-lockfile 2>/dev/null || yarn install
yarn build:production

chown -R www-data:www-data "$PANEL" 2>/dev/null || chown -R nginx:nginx "$PANEL" 2>/dev/null || true
php artisan queue:restart || true
php artisan up
echo
echo "Selesai. Backup file asli: $BACKUP"
echo "Pastikan cron scheduler aktif:  * * * * * php $PANEL/artisan schedule:run >> /dev/null 2>&1"
echo "Isi KAZAMA_GOOGLE_CLIENT_ID/SECRET di .env, redirect URI Google: <APP_URL>/auth/google/callback"
