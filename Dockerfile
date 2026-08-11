# ==========================================
# STAGE 1: Build Composer Dependencies
# ==========================================

FROM composer:2 AS vendor

WORKDIR /app

# Copy Composer files
COPY composer.json composer.lock ./

# Install PHP dependencies
# Scripts disabled because Laravel application files
# are not available in this build stage yet.
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-reqs \
    --no-scripts


# ==========================================
# STAGE 2: Build Frontend Assets
# ==========================================

FROM node:20-alpine AS frontend

WORKDIR /app

# Copy package files
COPY package.json package-lock.json* ./

# Install frontend dependencies
RUN npm install

# Copy application source
COPY . .

# Build Vite/Tailwind
RUN npm run build


# ==========================================
# STAGE 3: Production Server
# ==========================================

FROM serversideup/php:8.4-fpm-nginx

ENV APP_ENV=production
ENV PHP_OPCACHE_ENABLE=1

WORKDIR /var/www/html

# Copy application source
COPY --chown=www-data:www-data . .

# Copy Composer dependencies
COPY --chown=www-data:www-data \
    --from=vendor /app/vendor \
    /var/www/html/vendor

# Copy compiled frontend assets
COPY --chown=www-data:www-data \
    --from=frontend /app/public/build \
    /var/www/html/public/build

# Ensure required Laravel directories exist
RUN mkdir -p \
    /var/www/html/storage/framework/cache \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/bootstrap/cache

# Set permissions
RUN chown -R www-data:www-data \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache

RUN chmod -R 775 \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache

# Run Laravel package discovery now that
# the complete application is available.
RUN php artisan package:discover --ansi

# Dokploy/runtime provides environment variables.