FROM php:8.1-cli-bullseye

ENV DEBIAN_FRONTEND=noninteractive

# git is required to allow Composer to work with symfony/runtime
# ext-xsl is required by lorenzo/pinky through twig/inky-extra
RUN apt-get update \
    && apt-get install --yes git libxslt-dev \
    && apt-get clean ; rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure xsl \
    && docker-php-ext-install xsl

# ensure that git is available
RUN git --version

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /symfony
