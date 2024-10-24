Sweego Bridge
=============

Provides Sweego integration for Symfony Mailer.

Configuration example:

```env
# SMTP
MAILER_DSN=sweego+smtp://LOGIN:PASSWORD@HOST:PORT
```

where:
 - `LOGIN` is your Sweego SMTP login
 - `PASSWORD` is your Sweego SMTP password
 - `HOST` is your Sweego SMTP host
 - `PORT` is your Sweego SMTP port

```env
# API
MAILER_DSN=sweego+api://API_KEY@default
```

where:
 - `API_KEY` is your Sweego API Key

Webhook
-------

Configure the webhook routing:

```yaml
framework:
    webhook:
        routing:
            sweego_mailer:
                service: mailer.webhook.request_parser.sweego
                secret: '%env(SWEEGO_WEBHOOK_SECRET)%'
```

And a consumer:

```php
#[AsRemoteEventConsumer(name: 'sweego_mailer')]
class SweegoMailEventConsumer implements ConsumerInterface
{
    public function consume(RemoteEvent|AbstractMailerEvent $event): void
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
