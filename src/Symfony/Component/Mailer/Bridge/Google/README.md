Google Mailer
=============

Provides Google Gmail integration for Symfony Mailer.

Configuration example:

```env
# SMTP
MAILER_DSN=gmail+smtp://USERNAME:APP-PASSWORD@default

# Gmail API, with OAuth2 service account authentication (domain-wide delegation)
MAILER_DSN=gmail+api://SERVICE-ACCOUNT-EMAIL:BASE64-ENCODED-PRIVATE-KEY@default?user=USER-EMAIL
```

For the `gmail+api` transport:

 * `SERVICE-ACCOUNT-EMAIL` is the service account e-mail address;
 * `BASE64-ENCODED-PRIVATE-KEY` is the service account RSA private key (PEM),
   base64-encoded so that it fits in the DSN (`base64_encode($pem)`);
 * `USER-EMAIL` is the address of the user to impersonate, which is also the
   address the messages are sent from: the Gmail API always sends as the
   authenticated user, so a custom envelope sender is not supported.

Envelope
--------

The Gmail API takes the recipients from the message itself, it has no envelope field. The
envelope is therefore written into the headers of the message sent to Google: addresses it
drops are removed from `To` and `Cc`, and the ones it adds travel in `Bcc`, which Gmail
honors for delivery and strips from the delivered message. An envelope sender cannot be
expressed, Gmail always sends as the impersonated user.

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
