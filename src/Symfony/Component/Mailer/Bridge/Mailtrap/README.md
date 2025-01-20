Mailtrap Bridge
===============

Provides Mailtrap integration for Symfony Mailer.

Configuration example:

```env
# SMTP
MAILER_DSN=mailtrap+smtp://PASSWORD@default

# API
MAILER_DSN=mailtrap+api://TOKEN@default
```

where:
 - `PASSWORD` is your Mailtrap SMTP Password
 - `TOKEN` is your Mailtrap Server Token

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
