MessageMedia Notifier
=================

Provides [MessageMedia](https://messagemedia.com/) integration for Symfony Notifier.

DSN example
-----------

```
MESSAGEMEDIA_DSN=messagemedia://API_KEY:API_SECRET@default?from=FROM
```

where:
 - `API_KEY` is your API key
 - `API_SECRET` is your API secret
 - `FROM` is your registered sender ID (optional). Accepted values: 3-15 letters, could be alpha tag, shortcode or international phone number.
When phone number starts with a `+` sign, it needs to be url encoded in the DSN

Adding Options to a Message
---------------------------

With a MessageMedia Message, you can use the `MessageMediaOptions` class to add
[message options](https://messagemedia.github.io/documentation/#tag/Messages/operation/SendMessages).

```php
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Bridge\MessageMedia\MessageMediaOptions;

$sms = new SmsMessage('+1411111111', 'My message');

$options = (new MessageMediaOptions())
    ->media(['media'])
    ->callbackUrl('callback_url')
    ->format('format')
    ->deliveryReport(true)
    ->expiry(999)
    ->metadata(['metadata'])
    ->scheduled('scheduled')
    ->subject('subject');
    // ...
    ;

// Add the custom options to the sms message and send the message
$sms->options($options);

$texter->send($sms);
```

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
