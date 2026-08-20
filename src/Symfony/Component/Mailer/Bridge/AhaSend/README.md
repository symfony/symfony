AhaSend Bridge
==============

Provides AhaSend integration for Symfony Mailer.

Configuration example:

```env
# SMTP
MAILER_DSN=ahasend+smtp://USERNAME:PASSWORD@default

# API
MAILER_DSN=ahasend+api://API_KEY:ACCOUNT_ID@default

# API (legacy v1, deprecated)
MAILER_DSN=ahasend+api://API_KEY@default
```

where:
 - `USERNAME` is your AhaSend SMTP Credentials username
 - `PASSWORD` is your AhaSend SMTP Credentials password
 - `API_KEY` is your AhaSend API key
 - `ACCOUNT_ID` is your AhaSend account ID; without it, the key is used with the legacy v1 API

Sending emails through the AhaSend v1 API is deprecated since Symfony 8.2. An
`ahasend+api://` DSN without an account ID still selects that legacy API, but
only for the API keys created before v2: a v2 API key (prefixed with `aha-sk-`)
without an account ID is rejected. Use a v2 API key and add your account ID to
the DSN instead.

> [!NOTE]
> Since Symfony 8.2, the ID of the [remote events][webhook] received from
> AhaSend is the `Message-ID` of the message, which the v2 API returns when
> sending it, so those events can be matched with the messages you sent. This
> applies to all AhaSend webhooks, whatever the transport used to send the
> messages; it used to be the internal ID of the event, which is still available
> in the payload under `data.id`.

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
[webhook]: https://symfony.com/doc/current/webhook.html
