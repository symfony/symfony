FROM k0pernikus/php-dev-base

RUN apk update \
    && apk add --no-cache --virtual .build-deps \
        openldap-dev \
        autoconf \
        gcc \
        g++ \
        make \
    && apk add --no-cache \
        openldap \
    && docker-php-ext-configure ldap \
    && docker-php-ext-install -j$(nproc) ldap \
    && docker-php-ext-enable ldap \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/*

RUN apk update \
    && apk add --no-cache --virtual .build-deps \
        libxslt-dev \
        autoconf \
        gcc \
        g++ \
        make \
    && apk add --no-cache \
        libxslt \
    && docker-php-ext-install -j$(nproc) xsl \
    && docker-php-ext-enable xsl \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/*



WORKDIR /var/www
CMD ["php-fpm"]
EXPOSE 9000
