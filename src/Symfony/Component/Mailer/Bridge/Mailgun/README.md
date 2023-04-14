Mailgun Mailer
==============

Provides Mailgun integration for Symfony Mailer.

Configuration example:

```env
# SMTP
MAILER_DSN=mailgun+smtp://USERNAME:PASSWORD@default?region=REGION

# HTTP
MAILER_DSN=mailgun+https://KEY:DOMAIN@default?region=REGION

# API
MAILER_DSN=mailgun+api://KEY:DOMAIN@default?region=REGION
```

where:
 - `KEY` is your Mailgun API key
 - `DOMAIN` is your Mailgun sending domain
 - `REGION` is Mailgun selected region (optional)

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
