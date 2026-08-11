# ===========================================
# STAGE 1: Node.js Dependencies (pnpm)
# ===========================================
FROM node:20-alpine AS node-deps
WORKDIR /app
RUN npm install -g pnpm
COPY package.json pnpm-lock.yaml* ./
RUN pnpm install --frozen-lockfile

# ===========================================
# STAGE 2: PHP Base (FrankenPHP + Tools)
# ===========================================
FROM dunglas/frankenphp:1.4-php8.4 AS php-base
WORKDIR /app

# Install Composer secara global
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install system dependencies
RUN apt-get update && apt-get install -y \
    curl unzip zip libpq-dev libicu-dev libzip-dev libmagickwand-dev \
    && rm -rf /var/lib/apt/lists/* && apt-get clean

# Extensions wajib untuk Octane & Laravel
RUN install-php-extensions gd zip intl pdo_mysql imagick bcmath exif pcntl opcache sodium ffi

# PHP upload limits — default FrankenPHP image ships upload_max_filesize=2M,
# which silently drops images >2MB and leaves orphan livewire-tmp metadata
# (Livewire 4 .json sidecar) so Filament validation 404s on the missing binary.
# Form allows 5MB/file; give headroom for that + multipart overhead.
RUN { \
        echo "upload_max_filesize = 20M"; \
        echo "post_max_size = 25M"; \
        echo "memory_limit = 512M"; \
        echo "max_file_uploads = 50"; \
        echo "max_input_time = 120"; \
    } > "$PHP_INI_DIR/conf.d/zz-uploads.ini"

# redis dibangun dari source GitHub — pecl.php.net channel metadata sedang rusak
# ("No releases available"), jadi PECL di-bypass total
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends git $PHPIZE_DEPS; \
    git clone --branch 6.1.0 --depth 1 https://github.com/phpredis/phpredis.git /tmp/phpredis; \
    cd /tmp/phpredis; \
    phpize; \
    ./configure; \
    make -j"$(nproc)"; \
    make install; \
    docker-php-ext-enable redis; \
    cd /; rm -rf /tmp/phpredis; \
    apt-get purge -y --auto-remove git $PHPIZE_DEPS; \
    rm -rf /var/lib/apt/lists/*

# Install Node.js & PM2 (dibutuhkan untuk build aset dan scheduler)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g pnpm pm2@5.3.0

# ===========================================
# STAGE 3: PHP Dependencies
# ===========================================
FROM php-base AS php-deps
WORKDIR /app
COPY composer.json composer.lock ./
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-reqs

# ===========================================
# STAGE 4: Node Build (Vite + Filament Check)
# ===========================================
FROM node:20-alpine AS node-build
WORKDIR /app
RUN npm install -g pnpm

# Salin package.json dan node_modules
COPY --from=node-deps /app/node_modules ./node_modules

# PENTING: Vite Filament membutuhkan folder vendor untuk compile theme
COPY --from=php-deps /app/vendor ./vendor
COPY . .

# Jalankan build aset
RUN pnpm run build

# ===========================================
# STAGE 5: Production
# ===========================================
FROM php-base AS production
WORKDIR /app

RUN groupadd -g 1000 app && useradd -m -u 1000 -g app -s /bin/bash app

RUN mkdir -p /config/caddy /data/caddy /etc/caddy /app/pm2-logs \
    && chown -R app:app /config /data /etc/caddy /app

COPY --from=php-deps /app/vendor ./vendor
COPY --from=node-build /app/public/build ./public/build
COPY --chown=app:app . .

# Patch Octane: skip download prompt saat versi tidak cocok (ada 2 confirm berbeda)
RUN OCTANE_CONCERN="vendor/laravel/octane/src/Commands/Concerns/InstallsFrankenPhpDependencies.php" \
    && sed -i "s/if (confirm('Should Octane download the latest/if (false \&\& confirm('Should Octane download the latest/" "$OCTANE_CONCERN" \
    && sed -i "s/if (confirm('Unable to locate FrankenPHP binary/if (false \&\& confirm('Unable to locate FrankenPHP binary/" "$OCTANE_CONCERN" \
    && echo "✅ Octane FrankenPHP patch applied" \
    && grep -n "if (false && confirm\|if (confirm" "$OCTANE_CONCERN"

RUN chmod -R 775 storage bootstrap/cache \
    && chown -R app:app storage bootstrap/cache

COPY --chown=app:app start-container.sh /usr/local/bin/start-container.sh
RUN chmod +x /usr/local/bin/start-container.sh

ENV OCTANE_SERVER=frankenphp
USER app
EXPOSE 80

HEALTHCHECK --interval=30s --timeout=10s --start-period=120s --retries=5 \
    CMD curl -f http://localhost:80/ || exit 1

ENTRYPOINT ["/usr/local/bin/start-container.sh"]