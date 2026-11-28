# Build of the OSS Scanner (https://github.com/anthropics/oss-scanner), with the repository root as context.
# It mirrors the PHP 8.4 job of .github/workflows/unit-tests.yml: the same extensions, ini values, terminal width,
# optional commands and Composer configuration, the dependencies installed by `composer update` and PHPUnit by
# `./phpunit install`.
# The scan runs without network access: every dependency is installed here.
# Composer reads the version of the root package and of its path repositories from the Git checkout.
FROM php:8.4-cli-trixie

ENV COLUMNS=120 \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates git unzip zopfli ffmpeg \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions amqp apcu bcmath brotli calendar exif ffi gd gettext gmp igbinary intl ldap memcached \
    mongodb pcntl pdo_mysql pdo_pgsql redis relay shmop soap sockets sysvmsg sysvsem sysvshm xsl zip zstd

RUN printf '%s\n' date.timezone=UTC memory_limit=-1 default_socket_timeout=10 session.gc_probability=0 \
    apc.enable_cli=1 zend.assertions=1 intl.default_locale=en intl.error_level=0 \
    > /usr/local/etc/php/conf.d/zz-unit-tests.ini

COPY . /src
WORKDIR /src

RUN mkdir -p "$(composer config --global home)" \
    && cp .github/composer-config.json "$(composer config --global home)/config.json" \
    && composer update --no-progress --ignore-platform-req=php+ \
    && ./phpunit install

RUN for c in Component/HttpFoundation Component/Routing Component/Security/Http Component/HtmlSanitizer; do \
        ./phpunit --exclude-group tty --exclude-group benchmark --exclude-group intl-data --exclude-group integration \
            --exclude-group transient --exclude-group network --exclude-group not-root src/Symfony/$c \
            || echo "WARNING: PHPUnit reported failures in $c"; \
    done
