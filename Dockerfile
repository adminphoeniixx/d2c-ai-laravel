# ─── Stage 1: Build frontend assets ─────────────────
FROM node:20-alpine AS frontend

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY . .
RUN npm run build && rm -f public/hot

# ─── Stage 2: Install PHP dependencies ──────────────
FROM composer:2 AS vendor

WORKDIR /app
COPY . .
# Install ALL deps first (lock file has dev packages), run discovery, then strip dev
RUN composer install \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    && composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# ─── Stage 3: Production image ──────────────────────
FROM php:8.4-fpm-alpine

# Install system deps + PHP extensions
RUN apk add --no-cache \
    nginx \
    supervisor \
    libpq-dev \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    && docker-php-ext-install \
    pdo_pgsql \
    pgsql \
    mbstring \
    bcmath \
    zip \
    intl \
    pcntl \
    && rm -rf /var/cache/apk/*

# Configure PHP for production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && sed -i 's/memory_limit = .*/memory_limit = 256M/' "$PHP_INI_DIR/php.ini" \
    && sed -i 's/upload_max_filesize = .*/upload_max_filesize = 20M/' "$PHP_INI_DIR/php.ini" \
    && sed -i 's/post_max_size = .*/post_max_size = 25M/' "$PHP_INI_DIR/php.ini" \
    && sed -i 's/expose_php = .*/expose_php = Off/' "$PHP_INI_DIR/php.ini"

# Configure PHP-FPM
RUN sed -i 's/;clear_env = no/clear_env = no/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/pm.max_children = .*/pm.max_children = 20/' /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www

# Copy app code
COPY . .

# Remove dev artifacts
RUN rm -f public/hot \
    && rm -f bootstrap/cache/packages.php \
    && rm -f bootstrap/cache/services.php

# Copy vendor from composer stage
COPY --from=vendor /app/vendor ./vendor

# Copy built frontend assets
COPY --from=frontend /app/public/build ./public/build

# Ensure critical files exist
RUN mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/testing \
    storage/logs \
    storage/app/public \
    bootstrap/cache

# Set permissions — run AFTER all COPY stages
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 777 storage bootstrap/cache \
    && chmod -R 777 storage/logs

# Nginx config
COPY docker/nginx.conf /etc/nginx/http.d/default.conf

# Supervisor config (runs nginx + php-fpm)
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Entrypoint script
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 9827

# Default env for production Docker — use stderr so logs go to docker logs, not files
ENV LOG_CHANNEL=stderr
ENV LOG_LEVEL=error

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
