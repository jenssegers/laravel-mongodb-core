ARG PHP_VERSION=7.2
ARG COMPOSER_VERSION=1.8

FROM composer:${COMPOSER_VERSION}
FROM php:${PHP_VERSION}-cli

RUN apt-get update && \
    apt-get install -y git zip unzip && \
    pecl install mongodb && docker-php-ext-enable mongodb && \
    pecl install xdebug && docker-php-ext-enable xdebug

COPY --from=0 /usr/bin/composer /usr/local/bin/composer

WORKDIR /code

ADD composer.json composer.json
# composer.lock isn't exists in git repository
#ADD composer.lock composer.lock 

RUN composer global require hirak/prestissimo
RUN composer install --prefer-source --no-interaction
