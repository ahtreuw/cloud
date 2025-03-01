FROM composer:2.7.6 as composer
FROM php:8.3-fpm-alpine3.17
RUN echo "UTC" > /etc/timezone
RUN sed -i 's/https/http/' /etc/apk/repositories
RUN apk update && apk add git curl zip libzip-dev unzip bash openssh-keygen \
    && docker-php-ext-install zip
WORKDIR /app
COPY --from=composer /usr/bin/composer /usr/bin/composer
