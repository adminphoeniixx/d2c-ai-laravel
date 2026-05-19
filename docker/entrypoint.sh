#!/bin/sh
set -e

cd /var/www

# Fix storage permissions (root creates files that www-data can't write to)
chmod -R 777 storage bootstrap/cache
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
chmod -R 777 storage

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

# Fix permissions again after all the artisan commands
chmod -R 777 storage

echo "Pulsara ready"

exec "$@"
