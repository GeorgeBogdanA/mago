# PHP-FPM image for symfony-api (development). The code is bind-mounted, not copied.
FROM php:8.4-fpm-bookworm

# Run PHP-FPM with the host user's UID/GID, so files written in var/ stay owned by you.
ARG UID=1000
ARG GID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libicu-dev libzip-dev unzip git libfcgi-bin \
    && docker-php-ext-install pdo_pgsql intl opcache zip \
    && apt-get clean \
    && groupmod -o -g "${GID}" www-data \
    && usermod -o -u "${UID}" -g "${GID}" www-data

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/zz-healthcheck.conf /usr/local/etc/php-fpm.d/zz-healthcheck.conf

ENV COMPOSER_HOME=/tmp/composer
WORKDIR /var/www/app
USER www-data
