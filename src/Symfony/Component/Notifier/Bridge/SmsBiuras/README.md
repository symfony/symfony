SmsBiuras Notifier
==================

Provides [SmsBiuras](https://www.smsbiuras.lt) integration for Symfony Notifier.

DSN example
-----------

```
SMSBIURAS_DSN=smsbiuras://UID:API_KEY@default?from=FROM&test_mode=0
```

where:
 - `UID` is your client code
 - `API_KEY` is your SmsBiuras api key
 - `FROM` is your sender
 - `TEST_MODE` the test parameter is used during system connection testing.
   Possible values: 0 (real SMS sent), 1 (test SMS, will not be delivered to the phone and will not be charged)

Sponsor
-------

This package is looking for a [backer][1].

Help Symfony by [sponsoring][3] its development!

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)

[1]: https://symfony.com/backers
[3]: https://symfony.com/sponsor
