Mailchimp Mailer
================

Provides Mandrill integration for Symfony Mailer.

Configuration example:

```env
# SMTP
MAILER_DSN=mandrill+smtp://USERNAME:PASSWORD@default

# HTTP
MAILER_DSN=mandrill+https://KEY@default

# API
MAILER_DSN=mandrill+api://KEY@default
```

where:
 - `KEY` is your Mailchimp API key

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
