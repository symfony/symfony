Fake Chat Notifier
==================

Provides Fake Chat (as email or log during development) integration for Symfony Notifier.

#### DSN example for email

```
FAKE_CHAT_DSN=fakechat+email://default?to=TO&from=FROM
```

where:
 - `TO` is email who receive Chat message during development
 - `FROM` is email who send Chat message during development

To use a custom mailer transport:
```
FAKE_CHAT_DSN=fakechat+email://mailchimp?to=TO&from=FROM
```

#### DSN example for logger

```
 FAKE_CHAT_DSN=fakechat+logger://default
 ```

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
