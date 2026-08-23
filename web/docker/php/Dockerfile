FROM php:8.5-fpm-alpine

# Системные зависимости для сборки PHP-расширений
RUN apk add --no-cache \
        git \
        curl \
        zip \
        unzip \
        bash \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libzip-dev \
        icu-dev \
        oniguruma-dev \
        nodejs \
        npm \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mysqli \
        bcmath \
        gd \
        zip \
        intl \
        exif \
        pcntl

# Composer из официального образа
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Непривилегированный пользователь под UID хоста (по умолчанию 1000)
ARG UID=1000
ARG GID=1000
RUN addgroup -g ${GID} laravel \
    && adduser -D -u ${UID} -G laravel laravel

WORKDIR /var/www

USER laravel

EXPOSE 9000
CMD ["php-fpm"]
