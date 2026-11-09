
FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    icu-dev \
    libzip-dev \
    postgresql-dev \
    && docker-php-ext-install \
        intl \
        pdo_pgsql \
        zip \
        opcache

WORKDIR /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

CMD ["php-fpm"]
