# Image de production de ReGeev (PHP 8.3 + Apache)
FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libicu-dev unzip git \
    && docker-php-ext-install pdo_pgsql intl opcache \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    TRUSTED_PROXIES=REMOTE_ADDR \
    COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

# Les dépendances d'abord : cette couche reste en cache tant que composer.* ne change pas
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-progress

COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative \
    && mkdir -p var \
    && chown -R www-data:www-data var \
    && chmod +x docker/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["apache2-foreground"]
