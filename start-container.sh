#!/bin/bash
set -e

echo "================================================"
echo "🚀  Portal News — Container Startup"
echo "================================================"

# ── 1. Storage & Directory Setup ──────────────────────────────────────────────
echo ""
echo "📁 [1/7] Menyiapkan direktori storage..."
mkdir -p storage/framework/{sessions,views,cache}
mkdir -p storage/logs
mkdir -p /app/pm2-logs
php artisan storage:link --force || true

# ── 2. Database Migration ──────────────────────────────────────────────────────
echo ""
echo "🗄️  [2/7] Menjalankan migrasi database..."
php artisan migrate --force --no-interaction || {
    echo "⚠️  Migrasi gagal — lanjutkan tanpa migrasi"
}

# ── 3. Clear semua cache lama (hindari stale cache) ──────────────────────────
echo ""
echo "🧹 [3/7] Membersihkan cache lama..."
php artisan config:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true
php artisan event:clear 2>/dev/null || true
php artisan cache:clear >/dev/null 2>&1 || true

# Bersihkan data Pulse dari deployment sebelumnya
if php artisan list 2>/dev/null | grep -q "pulse:clear"; then
    echo "📊 Membersihkan data Pulse lama..."
    php artisan pulse:clear --force || true
fi

# ── 4. Rebuild cache production ───────────────────────────────────────────────
echo ""
echo "📦 [4/7] Membangun ulang cache production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
# Republish Filament assets — image build runs composer with --no-scripts,
# so filament:upgrade (which publishes JS/CSS) never fires. Without this the
# stale git-tracked public/js/filament/* mismatches vendor and throws
# "e is not a function" in notifications.js at runtime.
php artisan filament:assets
php artisan filament:cache-components

# Cache icons jika package tersedia
if php artisan list | grep -q "icons:cache"; then
    php artisan icons:cache
fi

# Reset cache permission Spatie (mencegah stale permission cache)
if php artisan list | grep -q "permission:cache-reset"; then
    echo "🔐 Reset Spatie Permission Cache..."
    php artisan permission:cache-reset >/dev/null 2>&1 || true
fi

# ── 5. Optimize akhir ─────────────────────────────────────────────────────────
echo ""
echo "⚡ [5/7] Optimasi akhir..."
php artisan optimize

# ── 6. PM2 Scheduler & Queue Worker ──────────────────────────────────────────
echo ""
echo "⚙️  [6/7] Memulai PM2 (scheduler & queue worker)..."
if [ -f "ecosystem.config.cjs" ]; then
    # Jika PM2 sudah berjalan → restart, jika belum → start
    if pm2 list | grep -q "laravel-scheduler"; then
        echo "   ↻ PM2 sudah berjalan — melakukan reload..."
        pm2 reload ecosystem.config.cjs --update-env
    else
        echo "   ▶ PM2 belum berjalan — memulai proses baru..."
        pm2 start ecosystem.config.cjs --update-env
    fi
    pm2 save --force
else
    echo "⚠️  ecosystem.config.cjs tidak ditemukan, PM2 dilewati."
fi

# ── 7. FrankenPHP Octane Server ───────────────────────────────────────────────
echo ""
echo "🔥 [7/7] Menjalankan FrankenPHP / Laravel Octane..."
echo "================================================"
exec php artisan octane:start \
    --server=frankenphp \
    --host=0.0.0.0 \
    --port=80 \
    --admin-port=2019 \
    --workers=auto \
    --max-requests=500 \
    --no-interaction