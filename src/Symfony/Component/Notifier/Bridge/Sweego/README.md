Sweego Notifier
===============

Provides [Sweego](https://www.sweego.io/) integration for Symfony Notifier.

DSN example
-----------

```
SWEEGO_DSN=sweego://API_KEY@default?region=REGION&campaign_type=CAMPAIGN_TYPE&bat=BAT&campaign_id=CAMPAIGN_ID&shorten_urls=SHORTEN_URLS&shorten_with_protocol=SHORTEN_WITH_PROTOCOL
```

where:
 - `API_KEY` (required) is your Sweego API key
 - `REGION` (required) is the region of the phone number (e.g. `FR`, ISO 3166-1 alpha-2 country code)
 - `CAMPAIGN_TYPE` (required) is the type of the campaign (e.g. `transac`)
 - `BAT` (optional) is the test mode (e.g. `true`)
 - `CAMPAIGN_ID` (optional) is the campaign id (e.g. `string`)
 - `SHORTEN_URLS` (optional) is the shorten urls option (e.g. `true`)
 - `SHORTEN_WITH_PROTOCOL` (optional) is the shorten with protocol option (e.g. `true`)

Advanced Message options
------------------------

```php
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Bridge\Sweego\SweegoOptions;

$sms = new SmsMessage('+1411111111', 'My message');

$options = (new SweegoOptions())
    // False by default, set 'bat' to true enable test mode (no sms sent, only for testing purpose)
    ->bat(true)
    // Optional, used for tracking / filtering purpose on our platform; identity an SMS campaign and allow to see logs / stats only for this campaign
    ->campaignId('string')
    // True by default, we replace all url in the SMS content by a shortened url version (reduce the characters of the sms)
    ->shortenUrls(true)
    // True by default, add scheme to shortened url version
    ->shortenWithProtocol(true);

// Add the custom options to the sms message and send the message
$sms->options($options);

$texter->send($sms);
```

Webhook
-------

Configure the webhook routing:

```yaml
framework:
    webhook:
        routing:
            sweego_sms:
                service: notifier.webhook.request_parser.sweego
                secret: '%env(SWEEGO_WEBHOOK_SECRET)%'
```

And a consumer:

```php
#[AsRemoteEventConsumer(name: 'sweego_sms')]
class SweegoSmsEventConsumer implements ConsumerInterface
{
    public function consume(RemoteEvent|SmsEvent $event): void
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
