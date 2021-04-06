Fake Chat Notifier
==================

Provides Fake Chat (as email during development) integration for Symfony Notifier.

#### DSN example

```
FAKE_CHAT_DSN=fakechat+email://MAILER_SERVICE_ID?to=TO&from=FROM
```

where:
 - `MAILER_SERVICE_ID` is mailer service id (use `mailer` by default)
 - `TO` is email who receive Chat message during development
 - `FROM` is email who send Chat message during development

Resources
---------

  * [Contributing](https://symfony.com/doc/current/contributing/index.html)
  * [Report issues](https://github.com/symfony/symfony/issues) and
    [send Pull Requests](https://github.com/symfony/symfony/pulls)
    in the [main Symfony repository](https://github.com/symfony/symfony)
