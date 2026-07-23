Mailtrap Bridge
===============

Provides Mailtrap integration for Symfony Mailer.

Configuration example:

```env
# SMTP
MAILER_DSN=mailtrap+smtp://PASSWORD@default

# API (Live)
MAILER_DSN=mailtrap+api://TOKEN@default

# API (Sandbox)
MAILER_DSN=mailtrap+sandbox://TOKEN@default?inboxId=INBOX_ID
```

where:
 - `PASSWORD` is your Mailtrap SMTP Password
 - `TOKEN` is your Mailtrap Server Token
 - `INBOX_ID` is your Mailtrap sandbox inbox's ID

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
