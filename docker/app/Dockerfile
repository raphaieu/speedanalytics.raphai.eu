# Produção — imagem única nginx + PHP-FPM (Coolify / VPS sem bind mounts)

FROM node:24-bookworm AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js tsconfig.json components.json ./
COPY resources ./resources
COPY public ./public

RUN npm run build

FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

FROM php:8.4-fpm-bookworm AS runtime

RUN apt-get update && apt-get install -y \
    curl git unzip libzip-dev \
    && docker-php-ext-install pdo_mysql zip pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=frontend /app/public/build ./public/build

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache collector/storage \
    && chown -R www-data:www-data storage bootstrap/cache collector/storage \
    && chmod -R 775 storage bootstrap/cache collector/storage

FROM runtime AS web

RUN apt-get update && apt-get install -y nginx supervisor \
    && rm -rf /var/lib/apt/lists/*

COPY docker/nginx/coolify.conf /etc/nginx/conf.d/default.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=90s --retries=3 \
    CMD curl -fsS http://127.0.0.1/up || exit 1

CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]

FROM runtime AS worker

CMD ["php", "artisan", "queue:work", "redis", "--sleep=3", "--tries=3", "--timeout=120"]
