FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev libzip-dev zip unzip libicu-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# Every PHP process (FPM, queue, scheduler, `docker compose exec app php artisan …`) runs as
# www-data, remapped to the host user's UID/GID. One owner for everything in the bind-mounted
# source means no root-owned cache files and no "touch(): Utime failed" (setting an explicit
# mtime requires owning the file; ACL write access is not enough).
ARG UID=1000
ARG GID=1000
RUN groupmod -o -g "${GID}" www-data \
    && usermod -o -u "${UID}" -g "${GID}" www-data \
    && chown -R www-data:www-data /var/www

ENV COMPOSER_HOME=/tmp/composer

WORKDIR /var/www/html

USER www-data
