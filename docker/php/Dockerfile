FROM composer:2.9.5 AS vendor

ARG SERVICE_PATH
WORKDIR /app
COPY ${SERVICE_PATH}/composer.json ${SERVICE_PATH}/composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --prefer-dist --optimize-autoloader

FROM php:8.5.11-cli-alpine3.23

ARG SERVICE_PATH
RUN apk add --no-cache icu-dev libzip-dev supervisor \
    && docker-php-ext-install -j$(nproc) bcmath intl pcntl pdo_mysql zip

RUN addgroup -S app && adduser -S -G app -u 1000 app
WORKDIR /app
COPY --chown=app:app ${SERVICE_PATH}/ ./
COPY --from=vendor --chown=app:app /app/vendor ./vendor
COPY --chown=app:app docker/php/supervisord-payment.conf /etc/supervisor/conf.d/payment.conf
COPY --chown=app:app docker/php/start-commerce.sh docker/php/start-payment.sh /usr/local/bin/
RUN php artisan package:discover --ansi \
    && chmod +x /usr/local/bin/start-commerce.sh /usr/local/bin/start-payment.sh \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chown -R app:app storage bootstrap/cache

USER app
EXPOSE 8000
