Postmark Bridge
===============

Provides Postmark integration for Symfony Mailer.

Configuration example:

```env
# SMTP
MAILER_DSN=postmark+smtp://ID@default

# API
MAILER_DSN=postmark+api://KEY@default
```

where:
 - `ID` is your Postmark Server Token
 - `KEY` is your Postmark Server Token

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
