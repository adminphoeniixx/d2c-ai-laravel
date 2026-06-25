#!/bin/sh
set -e

cd /var/www

# Create all required storage subdirectories upfront — some may not exist
# if this is a fresh volume mount (Easypanel mounts a persistent volume
# over /var/www/storage, which replaces what was baked into the image).
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/testing \
    storage/logs \
    storage/app/public \
    storage/app/exports \
    storage/app/banking_uploads \
    bootstrap/cache

# Generate key if not set
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

# Remove stale hot file (forces production assets)
rm -f public/hot

# Cache config + routes + views for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run central migrations (safe — only runs pending ones)
php artisan migrate --force 2>/dev/null || true

# Provision tenant schemas for ALL companies
php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

foreach (\App\Models\Company::all() as \$c) {
    try { \$c->database()->manager()->createDatabase(\$c); } catch (\Throwable \$e) {}
    try {
        tenancy()->initialize(\$c);
        \Illuminate\Support\Facades\Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--force' => true,
            '--realpath' => true,
        ]);
        echo \$c->slug . ': migrated' . PHP_EOL;
        tenancy()->end();
    } catch (\Throwable \$e) {
        echo \$c->slug . ': ' . \$e->getMessage() . PHP_EOL;
        try { tenancy()->end(); } catch (\Throwable \$x) {}
    }
}
" || true

# Link storage
php artisan storage:link 2>/dev/null || true

# Create supervisor log dir
mkdir -p /var/log/supervisor

# Fix permissions AFTER all artisan commands — those commands create
# bootstrap/cache files and storage framework files as root, which
# php-fpm (www-data) can't overwrite on the next request. Doing this
# last ensures every file created above is accessible to www-data.
# Also explicitly chown so ownership is correct, not just mode bits.
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache
# Logs and framework dirs need full write access
chmod -R 777 storage/logs storage/framework storage/app

echo "Pulsara ready"

# Signal queue workers to restart so they pick up new code after deploy
php artisan queue:restart 2>/dev/null || true

exec "$@"
