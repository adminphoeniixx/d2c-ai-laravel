# Pulsara Setup Guide

You already have a working Laravel 13 + Jetstream project (you registered and saw the dashboard). This zip contains all the Pulsara additions layered on top. Extract, install a few extra packages, run migrations, and you're done.

## Option A — Extract over existing project (recommended)

You already have `pulsara/` running with Docker Compose. Just overlay this zip:

```bash
# Stop containers (keep postgres volume)
docker compose down

# Unzip, from D2C PROJECT folder
unzip pulsara-merged.zip -d pulsara-merged

# Overlay onto existing project — this will overwrite some files
cp -rf pulsara-merged/. pulsara/
cd pulsara

# Review what .env changes you need (diff against old)
diff .env .env.example
# If you want the Pulsara env defaults, copy the example but preserve APP_KEY:
# First save your APP_KEY, then overwrite .env, then restore it
```

## Option B — Fresh start with this zip

```bash
cd "D2C PROJECT"
mv pulsara pulsara-backup
unzip pulsara-merged.zip
mv pulsara-merged pulsara
cd pulsara
cp .env.example .env
```

## Step 1: Install extra Composer packages

```bash
# Start containers
docker compose up -d

# Install Pulsara-specific packages (composer picks compatible versions automatically)
docker compose exec app composer require \
  stancl/tenancy \
  spatie/laravel-permission \
  spatie/laravel-activitylog \
  guzzlehttp/guzzle
```

When Tenancy asks to publish config, say **no** — we already have `config/tenancy.php`.
When Spatie Permission asks to publish migration, say **no** — run this instead:

```bash
docker compose exec app php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag="permission-migrations"
```

## Step 2: Install NPM packages

```bash
docker compose exec node npm install \
  chart.js \
  vue-chartjs \
  lucide-vue-next \
  pinia \
  laravel-echo \
  pusher-js
```

## Step 3: Generate APP_KEY if missing

```bash
docker compose exec app php artisan key:generate
```

## Step 4: Run migrations + seed

```bash
# Run central migrations (users, companies, integration_accounts, permissions)
docker compose exec app php artisan migrate --force

# Seed: admin user + 2 demo companies with tenant schemas
docker compose exec app php artisan db:seed --force
```

## Step 5: Open in browser

| URL | Credentials |
|---|---|
| http://localhost:8000 | Landing page |
| http://localhost:8000/register/company | Create new company |
| http://localhost:8000/login | Login form |
| http://localhost:8000/admin | admin@pulsara.test / password |
| http://localhost:8000/app/acme | owner@acme.test / password |
| http://localhost:8000/app/nova | owner@nova.test / password |

## Verification checklist

- [ ] Landing page loads with dark Velo theme
- [ ] Login works
- [ ] Admin user lands on `/admin/dashboard`
- [ ] Company owner lands on `/app/{slug}/...`
- [ ] Sidebar shows Phase 1 / Phase 2 / AI groups
- [ ] Profile page (Jetstream) still works

## Troubleshooting

**"Class 'Company' not found" during seed**
Run: `docker compose exec app composer dump-autoload`

**"SQLSTATE: relation tenant_xxx does not exist"**
The tenant seeder failed. Check: does `stancl/tenancy` config exist? Run: `docker compose exec app php artisan config:clear`

**Theme still looks like old Jetstream**
Restart Vite: `docker compose restart node`, then hard-refresh browser (Cmd+Shift+R)

**Vite error "chart.js module not found"**
npm install didn't finish: `docker compose exec node npm install`

**Can't log in as admin**
Check admin user exists: `docker compose exec app php artisan tinker` → `User::where('is_admin', true)->first()`

## Known limitations of this build

This is foundation + scaffolding. Working end-to-end pieces:

✓ Multi-tenant routing (`/app/{slug}/`)
✓ Admin panel with role-based permissions
✓ Company registration with auto-provisioned tenant schema
✓ Velo dark theme
✓ All Vue pages render (placeholder data where services aren't wired)

Deferred to later phases:

- Shopify + WooCommerce OAuth (controllers exist, need your Shopify Partner credentials)
- Real-time alerts (Reverb config exists, needs container + client subscription wiring)
- AI Copilot (controller stub, needs Laravel AI SDK agent)
- Horizon dashboard (needs Redis container)
- Octane/Swoole (needs Dockerfile update to include extension)

These are additive — you can build the full stack or add features one at a time.
