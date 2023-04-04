Crowdin Translation Provider
============================

Provides Crowdin integration for Symfony Translation.

DSN example
-----------

```
// .env file
CROWDIN_DSN=crowdin://PROJECT_ID:API_TOKEN@ORGANIZATION_DOMAIN.default
```

where:
 - `PROJECT_ID` is your Crowdin Project ID
 - `API_TOKEN` is your Personal Access API Token
 - `ORGANIZATION_DOMAIN` is your Crowdin Enterprise Organization domain (required only for Crowdin Enterprise usage)

[Generate Personal Access Token on Crowdin](https://support.crowdin.com/account-settings/#api)

[Generate Personal Access Token on Crowdin Enterprise](https://support.crowdin.com/enterprise/personal-access-tokens/)

Sponsor
-------

This bridge for Symfony 6.3 is [backed][1] by [Crowdin][2].

Crowdin is a cloud-based localization management software helping teams to go global and stay agile.

Help Symfony by [sponsoring][3] its development!

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)

[1]: https://symfony.com/backers
[2]: https://crowdin.com
[3]: https://symfony.com/sponsor
