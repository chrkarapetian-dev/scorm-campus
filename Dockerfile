# Front-end assets (Vite + Tailwind)
FROM node:22-alpine AS assets
WORKDIR /build
COPY package.json package-lock.json .npmrc vite.config.js ./
COPY resources ./resources
RUN npm ci --no-audit --no-fund && npm run build

# PHP dependencies
FROM composer:2 AS vendor
WORKDIR /build
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist --ignore-platform-reqs
COPY app ./app
COPY database ./database
COPY bootstrap ./bootstrap
COPY routes ./routes
RUN composer dump-autoload --no-dev --no-scripts --optimize --ignore-platform-reqs

# Application (PHP-FPM)
FROM php:8.4-fpm-alpine AS app
RUN apk add --no-cache libzip libzip-dev \
    && docker-php-ext-install pdo_mysql zip opcache \
    && apk del libzip-dev
COPY docker/php/app.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/pool.conf /usr/local/etc/php-fpm.d/zz-pool.conf
WORKDIR /var/www/html
COPY . .
COPY --from=vendor /build/vendor ./vendor
COPY --from=assets /build/public/build ./public/build
RUN install -m 0755 docker/php/entrypoint.sh /usr/local/bin/app-entrypoint \
    && php artisan package:discover --ansi \
    && mkdir -p storage/app/scorm \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data
EXPOSE 9000
ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]

# Web server (Nginx): serves public/ and hands the rest to the app container
FROM nginx:1.28-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/html/public
COPY --from=assets /build/public/build /var/www/html/public/build
