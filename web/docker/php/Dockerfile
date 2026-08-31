FROM php:8.5-fpm-alpine

# Устанавливаем базовые системные утилиты, нужные для работы Laravel и окружения
RUN apk add --no-cache \
        git \
        curl \
        zip \
        unzip \
        bash \
        supervisor \
        nodejs \
        npm

# Скачиваем официальный установщик расширений PHP
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# Устанавливаем все нужные расширения (включая зависимости для GD и Redis) одной командой
RUN install-php-extensions \
        pdo_mysql \
        mysqli \
        bcmath \
        gd \
        zip \
        intl \
        exif \
        pcntl \
        redis

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
