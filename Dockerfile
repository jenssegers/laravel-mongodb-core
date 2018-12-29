ARG PHP_VERSION=7.2
ARG COMPOSER_VERSION=1.8

FROM composer:${COMPOSER_VERSION}
FROM php:${PHP_VERSION}-cli

RUN set -eux; \
    if [ $(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;") = "7.3" ]; \
    then \
        pecl install xdebug-beta; \
    else \
        pecl install xdebug; \       
    fi && \
    docker-php-ext-enable xdebug

RUN apt-get update && \
    apt-get install -y git zip unzip && \
    pecl install mongodb && docker-php-ext-enable mongodb

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

WORKDIR /code
