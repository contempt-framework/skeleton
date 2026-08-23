# syntax=docker/dockerfile:1.7

FROM composer:2 AS composer

FROM php:8.5-cli-alpine AS build
WORKDIR /app

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --classmap-authoritative

COPY bin ./bin
COPY config ./config
COPY public ./public
COPY src ./src

RUN composer dump-autoload --no-dev --classmap-authoritative --no-interaction \
    && APP_ENV=prod CONTEMPT_LOAD_DOTENV=0 php bin/contempt build \
    && composer check-platform-reqs --no-dev

FROM php:8.5-fpm-alpine AS runtime
WORKDIR /app

RUN docker-php-ext-install opcache

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-application.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY --from=build --chown=www-data:www-data /app /app

RUN chmod -R a-w /app \
    && chmod -R a+rX /app

USER www-data
EXPOSE 9000
CMD ["php-fpm", "-F"]

FROM nginx:1.29-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/public /app/public

HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
    CMD wget -q -O /dev/null http://127.0.0.1:8080/health/ready || exit 1
