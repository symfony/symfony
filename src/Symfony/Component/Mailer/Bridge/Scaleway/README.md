Scaleway Bridge
===============

Provides [Scaleway Transactional Email](https://www.scaleway.com/en/transactional-email-tem/) integration for Symfony Mailer.

Configuration example:

```env
# SMTP
MAILER_DSN=scaleway+smtp://PROJECT_ID:API_KEY@default

# API
MAILER_DSN=scaleway+api://PROJECT_ID:API_KEY@default
```

where:
 - `PROJECT_ID` is your Scaleway project ID
 - `API_KEY` is your Scaleway API secret key

Webhook
-------

Scaleway delivers email events through [Scaleway Topics and Events][topics-and-events],
which signs each message instead of sharing a secret. That's why the `secret`
option is not needed for this provider.

The bridge verifies the signature with the certificate referenced by the
`SigningCertURL` field of the message, after checking that the Scaleway
certificate authority bundled with the bridge issued it. Fetching the
certificate requires the [HttpClient component][http-client]. In Symfony
applications, the certificate is cached in the `cache.app` pool.

The first time Scaleway calls the endpoint, it sends a subscription confirmation
message and the bridge confirms it automatically. That message contains no
email event, so your application responds to it with an HTTP 406 status code.
This is expected: the subscription is confirmed by the request the bridge makes
to Scaleway, not by that response.

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
[topics-and-events]: https://www.scaleway.com/en/docs/topics-and-events/reference-content/verifying-webhooks/
[http-client]: https://symfony.com/http-client
