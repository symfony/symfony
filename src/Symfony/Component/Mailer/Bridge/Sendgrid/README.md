Sendgrid Bridge
===============

Provides Sendgrid integration for Symfony Mailer.

Configuration example:

```env
# SMTP
MAILER_DSN=sendgrid+smtp://KEY@default?region=REGION

# API
MAILER_DSN=sendgrid+api://KEY@default?region=REGION
```

where:
 - `KEY` is your Sendgrid API Key
 - `REGION` is Sendgrid selected region (default to global)

Webhook
-------

Create a route:

```yaml
framework:
    webhook:
        routing:
            sendgrid:
                service: mailer.webhook.request_parser.sendgrid
                secret: '!SENDGRID_VALIDATION_SECRET!' # Leave blank if you don't want to use the signature validation
```

And a consume:

```php
#[\Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer(name: 'sendgrid')]
class SendGridConsumer implements ConsumerInterface
{
    public function consume(RemoteEvent|MailerDeliveryEvent $event): void
    {
        // your code
    }
}
```

Suppression Groups
------------------

Create an e-mail and add the `SuppressionGroupHeader`:

```php
use Symfony\Component\Mailer\Bridge\Sendgrid\Header\SuppressionGroupHeader;
// [...]
$email = new Email();
$email->getHeaders()->add(new SuppressionGroupHeader(GROUP_ID, GROUPS_TO_DISPLAY));
```

where:
 - `GROUP_ID` is your Sendgrid suppression group ID
 - `GROUPS_TO_DISPLAY_ID` is an array of the Sendgrid suppression group IDs presented to the user

Scheduling
----------

When using the **API transport** (with a `sendgrid+api` DSN), you can schedule
your emails by providing a `\DateTimeInterface` object in a
`Symfony\Component\Mime\Header\DateHeader` named `Send-At`.

```php
$email = new \Symfony\Component\Mime\Email();
$email->getHeaders()->addDateHeader('Send-At', new \DateTimeImmutable('+3 hours'));
```
It will be mapped to the `send_at` parameter of the `[POST] /mail/send`
[API endpoint](https://www.twilio.com/docs/sendgrid/api-reference/mail-send/mail-send#request-body)

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
