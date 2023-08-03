Mailjet Bridge
==============

Provides Mailjet integration for Symfony Mailer.

Configuration example:

```env
# SMTP
MAILER_DSN=mailjet+smtp://ACCESS_KEY:SECRET_KEY@default

# API
MAILER_DSN=mailjet+api://ACCESS_KEY:SECRET_KEY@default
MAILER_DSN=mailjet+api://ACCESS_KEY:SECRET_KEY@default?sandbox=true
```

where:
 - `ACCESS_KEY` is your Mailjet access key
 - `SECRET_KEY` is your Mailjet secret key

Webhook
-------

When you [setup your webhook URL](https://app.mailjet.com/account/triggers) on Mailjet you must not group events by unchecking the checkboxes.

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
