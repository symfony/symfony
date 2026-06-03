Mailomat Bridge
===============

Provides [Mailomat](https://mailomat.swiss) integration for Symfony Mailer.

Mailer
-------

Configuration example:

```env
# .env.local

# SMTP
MAILER_DSN=mailomat+smtp://USERNAME:PASSWORD@default

# API
MAILER_DSN=mailomat+api://KEY@default
```

Where:
 - `USERNAME` is your Mailomat SMTP username (must use your full email address)
 - `PASSWORD` is your Mailomat SMTP password
 - `KEY` is your Mailomat API key


Webhook
-------

Create a route:

```yaml
framework:
    webhook:
        routing:
            mailomat:
                service: mailer.webhook.request_parser.mailomat
                secret: '%env(WEBHOOK_MAILOMAT_SECRET)%'
```

The configuration:

```env
# .env.local

WEBHOOK_MAILOMAT_SECRET=your-mailomat-webhook-secret
```

And a consumer:

```php
#[\Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer(name: 'mailomat')]
class MailomatConsumer implements ConsumerInterface
{
    public function consume(AbstractMailerEvent $event): void
    {
        // your code
    }
}
```

Where:
- `WEBHOOK_MAILOMAT_SECRET` is your Mailomat Webhook secret

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
