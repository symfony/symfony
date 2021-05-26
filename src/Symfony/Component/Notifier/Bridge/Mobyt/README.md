Mobyt Notifier
===============

Provides [Mobyt](https://www.mobyt.it/en/) integration for Symfony Notifier.

DSN example
-----------

```
MOBYT_DSN=mobyt://USER_KEY:ACCESS_TOKEN@default?from=FROM&type_quality=TYPE_QUALITY
```

where:
 - `USER_KEY` is your Mobyt user key
 - `ACCESS_TOKEN` is your Mobyt access token
 - `FROM` is the sender
 - `TYPE_QUALITY` is the quality of your message: `N` for high, `L` for medium, `LL` for low (default: `L`)

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
