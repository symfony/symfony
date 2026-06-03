Scaleway Bridge
===============

Provides [Scaleway Transactional Email](https://www.scaleway.com/en/transactional-email-tem/) integration for Symfony Mailer.

Configuration example:

```env
# SMTP
MAILER_DSN=scaleway+smtp://PROJECT_ID:API_KEY@default

# API
MAILER_DSN=scaleway+api://PROJECT_ID:API_KEY@default
```

where:
 - `PROJECT_ID` is your Scaleway project ID
 - `API_KEY` is your Scaleway API secret key

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
