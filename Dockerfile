# syntax=docker/dockerfile:1.7

FROM php:8.4-fpm-alpine

WORKDIR /var/www/html

ARG COMPOSER_INSTALL_FLAGS="--no-interaction --prefer-dist --no-progress --optimize-autoloader"

RUN apk add --no-cache \
        bash \
        curl \
        git \
        unzip \
        icu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        oniguruma-dev \
        libxml2-dev \
        postgresql16-dev \
        postgresql16-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        pgsql \
        zip \
    && rm -rf /tmp/* /var/cache/apk/*

RUN apk add --no-cache --virtual .redis-build-deps $PHPIZE_DEPS \
    && pecl install redis-6.3.0 \
    && docker-php-ext-enable redis \
    && apk del .redis-build-deps \
    && rm -rf /tmp/pear /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY docker/php/conf.d/testocms.ini /usr/local/etc/php/conf.d/99-testocms.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

COPY . /var/www/html

RUN mkdir -p storage/app/private storage/app/public storage/app/purifier \
        storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && composer install ${COMPOSER_INSTALL_FLAGS} \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm", "-F"]
