Octopush Notifier
=================

Provides [Octopush](https://www.octopush.com) integration for Symfony Notifier.

DSN example
-----------

```
OCTOPUSH_DSN=octopush://USERLOGIN:APIKEY@default?from=FROM&type=TYPE
```

where:
 - `USERLOGIN` is your Octopush email
 - `APIKEY` is your Octopush token
 - `FROM` is your sender
 - `TYPE` is Octopush sms type:
   - with the legacy API: `XXX` = SMS LowCost; `FR` = SMS Premium; `WWW` = SMS World
   - with the API v1: `sms_low_cost` or `sms_premium`

The API v1 is used as soon as one of its types is given, and also accepts a `purpose`:

```
OCTOPUSH_DSN=octopush://USERLOGIN:APIKEY@default?from=FROM&type=sms_premium&purpose=alert
```

where `PURPOSE` is `alert` (transactional SMS, the default) or `wholesale` (marketing SMS).

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
