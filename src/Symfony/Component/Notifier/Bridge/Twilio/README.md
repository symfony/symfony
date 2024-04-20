Twilio Notifier
===============

Provides [Twilio](https://www.twilio.com) integration for Symfony Notifier.

DSN example
-----------

```
TWILIO_DSN=twilio://SID:TOKEN@default?from=FROM
```

where:
 - `SID` is your Twillio ID
 - `TOKEN` is your Twilio token
 - `FROM` is your sender

Adding Options to a Message
---------------------------

With a Twilio Message, you can use the `TwilioOptions` class to add message options.

```php
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Bridge\Twilio\TwilioOptions;

$sms = new SmsMessage('+1411111111', 'My message');

$options = (new TwilioOptions())
    ->webhookUrl('test_webhook_url')
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
