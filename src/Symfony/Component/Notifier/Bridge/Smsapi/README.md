SMSAPI Notifier
===============

Provides [Smsapi](https://smsapi.pl) integration for Symfony Notifier.
This bridge can also be used with https://smsapi.com.

DSN example
-----------

```
SMSAPI_DSN=smsapi://TOKEN@default?from=FROM
```

// for https://smsapi.com set the correct endpoint:
```
SMSAPI_DSN=smsapi://TOKEN@api.smsapi.com?from=FROM
```

where:
 - `TOKEN` is your API Token (OAuth)
 - `FROM` is the sender name

See your account info at https://smsapi.pl or https://smsapi.com

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
