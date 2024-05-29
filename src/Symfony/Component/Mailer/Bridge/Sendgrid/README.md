Sendgrid Bridge
===============

Provides Sendgrid integration for Symfony Mailer.

Configuration example:

```env
# SMTP
MAILER_DSN=sendgrid+smtp://KEY@default

# API
MAILER_DSN=sendgrid+api://KEY@default
```

where:
 - `KEY` is your Sendgrid API Key


Webhook
-------

Create a route:

```yaml
framework:
    webhook:
        routing:
            sendgrid:
                service: mailer.webhook.request_parser.sendgrid
                secret: '!SENDGRID_VALIDATION_SECRET!' # Leave blank if you dont want to use the signature validation
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

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
