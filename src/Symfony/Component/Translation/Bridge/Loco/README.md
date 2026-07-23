Loco Translation Provider
=========================

Provides Loco integration for Symfony Translation.

DSN example
-----------

```
// .env file
LOCO_DSN=loco://API_KEY@default?status=translated,blank-translation
```

where:
 - `API_KEY` is your Loco project API key

 **DSN query parameters**

 - `status`: translations status, default to `translated,blank-translation`

[more information on Loco website](https://localise.biz/help/developers/api-keys)

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
