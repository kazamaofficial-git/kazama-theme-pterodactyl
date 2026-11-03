#!/usr/bin/env bash
# Kembalikan file asli dari backup installer:  bash uninstall.sh /var/www/pterodactyl /var/www/pterodactyl/storage/kazama-theme-backup-XXXX
set -euo pipefail
PANEL="${1:?panel dir}"; BACKUP="${2:?backup dir}"
cd "$PANEL"; php artisan down || true
cp -a "$BACKUP/." "$PANEL/"
rm -rf app/Kazama routes/kazama.php config/kazama.php resources/views/kazama public/kazama resources/scripts/kazama
sed -i '/KazamaServiceProvider/d' config/app.php
php artisan migrate:rollback --path=database/migrations/2026_11_03_000001_create_kazama_tables.php --force || true
rm -f database/migrations/2026_11_03_000001_create_kazama_tables.php
php artisan config:clear; php artisan view:clear; php artisan route:clear
export NODE_OPTIONS=--openssl-legacy-provider; yarn build:production
php artisan up
