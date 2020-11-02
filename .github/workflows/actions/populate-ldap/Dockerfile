FROM osixia/openldap
LABEL maintainer="wshihadeh.devx@gmail.com"
ENV LDAP_ORGANISATION="Symfony"
ENV LDAP_DOMAIN="symfony.com"
ENV LDAP_ADMIN_PASSWORD="symfony"
ENV LDAP_BASE_DN="dc=symfony,dc=com"
ENV LDAP_SEED_INTERNAL_LDIF_PATH="/ldif-data"
RUN mkdir /ldif-data
COPY ./fixtures.ldif /ldif-data/50-fixtures.ldif
