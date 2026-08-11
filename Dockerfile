# ==========================================
# STAGE 1: Build Composer Dependencies
# ==========================================
FROM composer:2 AS vendor
WORKDIR /app

# Copy composer files
COPY composer.json composer.lock* ./

# Install dependencies
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-reqs

# ==========================================
# STAGE 2: Build Frontend Assets (Vite/Tailwind)
# ==========================================
FROM node:20-alpine AS frontend
WORKDIR /app

# Copy package files
COPY package*.json bun.lock* ./

# Install npm dependencies
RUN npm install

# Copy application files (for tailwind scanning)
COPY . .

# Build assets
RUN npm run build

# ==========================================
# STAGE 3: Production Server
# ==========================================
# Using serversideup/php which is highly optimized for Laravel (Nginx + PHP-FPM)
FROM serversideup/php:8.3-fpm-nginx

# Set environment variables
ENV APP_ENV=production
ENV PHP_OPCACHE_ENABLE=1

# Copy application files with proper ownership
COPY --chown=www-data:www-data . /var/www/html

# Copy built vendor and frontend assets from previous stages
COPY --chown=www-data:www-data --from=vendor /app/vendor /var/www/html/vendor
COPY --chown=www-data:www-data --from=frontend /app/public/build /var/www/html/public/build

# Make sure storage and bootstrap/cache are writable
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Dokploy will handle passing the .env variables during runtime.
# By default, serversideup/php serves from /var/www/html/public 
