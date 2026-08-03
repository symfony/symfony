TurboSMTP Bridge
================

Provides TurboSMTP integration for Symfony Mailer.

Configuration example:

```env
# SMTP
MAILER_DSN=turbosmtp+smtp://KEY:SECRET@default

# SMTP (EU region)
MAILER_DSN=turbosmtp+smtp://KEY:SECRET@pro.eu.turbo-smtp.com

# API
MAILER_DSN=turbosmtp+api://KEY:SECRET@default

# API (EU region)
MAILER_DSN=turbosmtp+api://KEY:SECRET@api.eu.turbo-smtp.com
```

where:
 - `KEY` is your TurboSMTP Consumer Key
 - `SECRET` is your TurboSMTP Consumer Secret

Use the `default` host to send through the non-EU endpoint
(`api.turbo-smtp.com` for the API, `pro.turbo-smtp.com` for SMTP). EU accounts
must set the corresponding EU host (`api.eu.turbo-smtp.com` or
`pro.eu.turbo-smtp.com`).

Webhook
-------

Configure the TurboSMTP Event Webhook in your TurboSMTP dashboard, under
Settings > Notifications, to point at your application, then create a route:

```yaml
framework:
    webhook:
        routing:
            turbosmtp:
                service: mailer.webhook.request_parser.turbosmtp
                secret: '%env(TURBOSMTP_WEBHOOK_SECRET)%' # optional, see below
```

And a consumer:

```php
use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;
use Symfony\Component\RemoteEvent\RemoteEvent;

#[AsRemoteEventConsumer(name: 'turbosmtp')]
class TurboSmtpConsumer implements ConsumerInterface
{
    public function consume(RemoteEvent $event): void
    {
        if ($event instanceof MailerDeliveryEvent) {
            // handle processed, delivered, deferred, bounce and dropped events
        } elseif ($event instanceof MailerEngagementEvent) {
            // handle open, click, unsubscribe and spam events
        }
    }
}
```

TurboSMTP does not sign its webhook requests. Authenticating them is optional:
if a route `secret` is set, requests are expected to carry matching HTTP Basic
credentials, otherwise no check is performed. To enable it, set HTTP Basic
credentials on the webhook URL in your TurboSMTP dashboard
(`https://USERNAME:PASSWORD@example.com/webhook/turbosmtp`) and configure the
same `USERNAME:PASSWORD` pair as the route secret. Requests whose credentials
do not match are then rejected with a 403.

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
