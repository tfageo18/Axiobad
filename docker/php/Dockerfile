FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
        git \
        unzip \
        libicu-dev \
        libpq-dev \
        libzip-dev \
    && docker-php-ext-install \
        intl \
        pdo \
        pdo_pgsql \
        zip \
        opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html

COPY . .

RUN composer install --no-interaction --optimize-autoloader --no-scripts

RUN mkdir -p var/cache var/log public/uploads \
    && chown -R www-data:www-data var public/uploads

CMD ["php-fpm"]
