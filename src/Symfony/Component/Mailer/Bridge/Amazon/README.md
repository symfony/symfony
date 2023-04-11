Amazon Mailer
=============

Provides Amazon SES integration for Symfony Mailer.

Configuration example:

```env
# SMTP
MAILER_DSN=ses+smtp://USERNAME:PASSWORD@default?region=REGION&session_token=SESSION_TOKEN

# HTTP
MAILER_DSN=ses+https://ACCESS_KEY:SECRET_KEY@default?region=REGION&session_token=SESSION_TOKEN

# API
MAILER_DSN=ses+api://ACCESS_KEY:SECRET_KEY@default?region=REGION&session_token=SESSION_TOKEN
```

where:
 - `ACCESS_KEY` is your Amazon SES access key id
 - `SECRET_KEY` is your Amazon SES access key secret
 - `REGION` is Amazon SES selected region (optional, default `eu-west-1`)
 - `SESSION_TOKEN` is your Amazon SES session token (optional)

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
