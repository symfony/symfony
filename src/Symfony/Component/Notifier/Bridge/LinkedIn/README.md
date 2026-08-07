LinkedIn Notifier
=================

Provides LinkedIn integration for Symfony Notifier.

DSN example
-----------

```
LINKEDIN_DSN=linkedin://ACCESS_TOKEN:USER_ID@default
```

where:
 - `ACCESS_TOKEN` is your LinkedIn access token
 - `USER_ID` is your LinkedIn user id (or organization id when posting as a company page)

To post as a LinkedIn organization (company page), set the optional `author` query parameter:

```
LINKEDIN_DSN=linkedin://ACCESS_TOKEN:ORGANIZATION_ID@default?author=organization
```

Supported `author` values are `person` (default) and `organization`.

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
