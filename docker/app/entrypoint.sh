#!/bin/sh
set -e

mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    collector/storage

chown -R www-data:www-data storage bootstrap/cache collector/storage 2>/dev/null || true
chmod -R 775 storage bootstrap/cache collector/storage

exec "$@"
