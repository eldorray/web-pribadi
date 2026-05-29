#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PACKAGE_DIR="$ROOT_DIR/build"
PACKAGE_NAME="web-pribadi-cpanel.zip"

cd "$ROOT_DIR"

if [ ! -d vendor ]; then
    echo "Folder vendor tidak ditemukan. Jalankan composer install dulu."
    exit 1
fi

npm run build

mkdir -p "$PACKAGE_DIR"
rm -f "$PACKAGE_DIR/$PACKAGE_NAME"

zip -rq "$PACKAGE_DIR/$PACKAGE_NAME" \
    .htaccess \
    .env.cpanel.example \
    artisan \
    composer.json \
    composer.lock \
    app \
    bootstrap \
    config \
    database \
    docs/cpanel-deploy.md \
    public \
    resources \
    routes \
    storage \
    vendor \
    -x ".git/*" \
    -x ".env" \
    -x ".env.backup" \
    -x ".env.production" \
    -x ".DS_Store" \
    -x "*/.DS_Store" \
    -x "node_modules/*" \
    -x "tests/*" \
    -x "build/*" \
    -x "database/database.sqlite" \
    -x "public/storage/*" \
    -x "public/htaccess *" \
    -x "storage/logs/*" \
    -x "storage/app/private/livewire-tmp/*" \
    -x "storage/pail/*"

echo "Paket deploy siap: $PACKAGE_DIR/$PACKAGE_NAME"
