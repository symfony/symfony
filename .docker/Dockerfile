FROM php:8.1-cli-alpine3.15

RUN apk add --no-cache libxslt-dev \
    && docker-php-ext-configure xsl \
    && docker-php-ext-install xsl

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /symfony
