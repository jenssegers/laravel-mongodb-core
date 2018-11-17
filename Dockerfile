ARG PHP_VERSION=7.2

FROM php:${PHP_VERSION}-cli

RUN apt-get update && \
    apt-get install -y git zip unzip && \
    pecl install mongodb && docker-php-ext-enable mongodb && \
    pecl install xdebug && docker-php-ext-enable xdebug

RUN curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/ \
    && ln -s /usr/local/bin/composer.phar /usr/local/bin/composer

ENV PATH="~/.composer/vendor/bin:./vendor/bin:${PATH}"
