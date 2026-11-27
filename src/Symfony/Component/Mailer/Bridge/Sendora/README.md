Sendora Bridge
==============

Provides [Sendora](https://sendorahq.com) integration for Symfony Mailer.

Configuration example:

```env
# API
MAILER_DSN=sendora+api://KEY@default

# same thing, shorter
MAILER_DSN=sendora://KEY@default

# SMTP, port 465 with TLS from the start
MAILER_DSN=sendora+smtp://KEY@default

# SMTP, port 587 with STARTTLS
MAILER_DSN=sendora+smtp://KEY@default:587

# a stream of your own, instead of the server's default transactional stream
MAILER_DSN=sendora+api://KEY@default?stream=STREAM_ID
```

where:
 - `KEY` is a server key of your Sendora server, `sk_…`. With the key of a test server,
   `sk_test_…`, Sendora checks each email and keeps it in its log, but delivers it to nobody
 - `STREAM_ID` is the id of one of the server's streams, such as a broadcast stream

Both transports set the email's tag from its `TagHeader`, its metadata from its `MetadataHeader`s
and its open and click tracking from its `TrackingHeader`. An email has one tag, so two different
tags fail the send. Without a `TrackingHeader`, the stream's tracking settings apply, and turning
tracking on fails until it is switched on in Sendora's dashboard.

After a send, the sent message's `getMessageId()` is the id that Sendora's message log and its
webhooks use.

Webhook
-------

Create a webhook in Sendora with the URL of your route, such as
`https://example.com/webhook/sendora`, and give its signing secret to the route:

```yaml
framework:
    webhook:
        routing:
            sendora:
                service: mailer.webhook.request_parser.sendora
                secret: '%env(SENDORA_WEBHOOK_SECRET)%'
```

And a consumer:

```php
use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;
use Symfony\Component\RemoteEvent\RemoteEvent;

#[AsRemoteEventConsumer(name: 'sendora')]
class SendoraConsumer implements ConsumerInterface
{
    public function consume(RemoteEvent $event): void
    {
        if ($event instanceof MailerDeliveryEvent) {
            // handle delivered, bounced and deferred emails
        } elseif ($event instanceof MailerEngagementEvent) {
            // handle spam complaints, unsubscribes, opens and clicks
        } else {
            // handle the other events by their name, such as "inbound" for a received email
        }
    }
}
```

The parser checks the `Sendora-Signature` header of each request, and rejects a signature made
more than five minutes before or after the current time. A delivery or engagement event has the
id of the email it is about, and any other event the id of the delivery. Every payload has the
delivery's id in `id`. Sendora may deliver an event more than once, so key your handling on it.

To roll the secret, add a second secret to the webhook in Sendora. Sendora then signs each
delivery with both, so you can change the route's secret before you delete the old one.

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
