FROM php:8.1-cli-bullseye

ENV DEBIAN_FRONTEND=noninteractive

# git is required to allow Composer to work with symfony/runtime
# ext-xsl is required by lorenzo/pinky through twig/inky-extra
# libonig-dev is required by the mbstring extension
# zip will allow Composer to install dependencies without using git clone,
# so that it won't hit the rate limits of Github
RUN apt-get update \
    && apt-get install --yes git libxslt-dev libzip-dev libonig-dev unzip \
    && apt-get clean ; rm -rf /var/lib/apt/lists/*

RUN pecl install apcu

# configure intl and xsl in 2 steps, otherwise it fails
RUN docker-php-ext-configure intl \
    && docker-php-ext-configure xsl \
    && docker-php-ext-install intl mbstring xsl zip \
    && docker-php-ext-enable apcu

# ensure that git is available
RUN git --version

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /symfony
