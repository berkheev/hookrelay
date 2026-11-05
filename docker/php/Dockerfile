# syntax=docker/dockerfile:1

ARG PHP_VERSION=8.3

FROM php:${PHP_VERSION}-cli-bookworm AS base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN install-php-extensions \
        swoole \
        redis \
        pdo_pgsql \
        pcntl \
        sockets \
        bcmath \
        intl \
        zip \
        opcache \
    && apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-hookrelay.ini

WORKDIR /app
ENV COMPOSER_HOME=/tmp/composer

FROM base AS dev

RUN install-php-extensions pcov

ARG UID=1000
ARG GID=1000
RUN groupadd -g ${GID} app && useradd -m -u ${UID} -g app app
USER app

EXPOSE 8000
CMD ["php", "artisan", "octane:start", "--server=swoole", "--host=0.0.0.0", "--port=8000"]

FROM base AS prod

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 8000
HEALTHCHECK --interval=15s --timeout=3s --retries=3 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1:8000/health") === false ? 1 : 0);'

CMD ["php", "artisan", "octane:start", "--server=swoole", "--host=0.0.0.0", "--port=8000"]
