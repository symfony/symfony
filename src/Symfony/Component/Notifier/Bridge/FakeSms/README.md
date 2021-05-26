Fake SMS Notifier
=================

Provides Fake SMS (as email during development) integration for Symfony Notifier.

#### DSN example

```
FAKE_SMS_DSN=fakesms+email://default?to=TO&from=FROM
```

where:
 - `TO` is email who receive SMS during development
 - `FROM` is email who send SMS during development

To use a custom mailer transport:
```
FAKE_SMS_DSN=fakesms+email://mailchimp?to=TO&from=FROM
```

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
