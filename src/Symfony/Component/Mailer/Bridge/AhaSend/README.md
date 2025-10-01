AhaSend Bridge
==============

Provides AhaSend integration for Symfony Mailer.

Configuration example:

```env
# SMTP
MAILER_DSN=ahasend+smtp://USERNAME:PASSWORD@default

# API
MAILER_DSN=ahasend+api://API_KEY@default
```

where:
 - `USERNAME` is your AhaSend SMTP Credentials username
 - `PASSWORD` is your AhaSend SMTP Credentials password
 - `API_KEY` is your AhaSend API Key credential

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
