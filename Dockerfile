FROM composer:2 AS composer-cli

FROM php:8.4-apache-bookworm AS dependencies
COPY --from=composer-cli /usr/bin/composer /usr/local/bin/composer
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /srv/whatsmyip
COPY composer.json LICENSE ./
COPY config/ config/
COPY src/ src/
COPY web/composer.json web/composer.lock web/
WORKDIR /srv/whatsmyip/web
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN --mount=type=cache,id=whatsmyip-composer,target=/tmp/composer-cache,sharing=locked \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY web/ ./
RUN composer dump-autoload --no-dev --optimize --no-interaction \
    && composer check-platform-reqs --no-dev

FROM php:8.4-apache-bookworm AS production
WORKDIR /srv/whatsmyip/web
COPY --from=dependencies /srv/whatsmyip /srv/whatsmyip
COPY web/deploy/apache.conf /etc/apache2/sites-available/000-default.conf
COPY web/deploy/production.ini /usr/local/etc/php/conf.d/whatsmyip.ini
COPY --chmod=0755 web/deploy/start.sh /usr/local/bin/start-whatsmyip
RUN chmod -R a+rX,go-w /srv/whatsmyip \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
ENV APP_ENV=production APP_DEBUG=false SESSION_SECURE_COOKIE=true LOG_CHANNEL=stderr PORT=8080
EXPOSE 8080
CMD ["start-whatsmyip"]
