#!/bin/sh

# Migrasi database
php artisan migrate --force

# Membuat symlink untuk storage
php artisan storage:link

# Clear cache
php artisan config:cache
php artisan route:cache

# Jalankan perintah yang diberikan ke container ini
exec "$@"
