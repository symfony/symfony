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

Features
--------

### Attachments

The bridge supports both regular attachments and inline attachments (for embedding images in HTML emails):

```php
use Symfony\Component\Mime\Email;

$email = new Email();
$email
    ->to('to@example.com')
    ->from('from@example.com')
    ->subject('Email with attachments')
    ->text('Here is the text version')
    ->html('<p>Here is the HTML content</p>')
    // Regular attachment
    ->attach('Hello world!', 'test.txt', 'text/plain')
    // Inline attachment (embedded image)
    ->embed(fopen('image.jpg', 'r'), 'image.jpg', 'image/jpeg')
;
```

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

Sponsor
-------

This bridge for Symfony 7.2 is [backed][1] by [Sweego][2] itself!

Sweego is a European email and SMS sending platform for developers and product builders.
Easily create, deliver, and monitor your emails and notifications.

Help Symfony by [sponsoring][3] its development!

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)

[1]: https://symfony.com/backers
[2]: https://www.sweego.io/
[3]: https://symfony.com/sponsor
