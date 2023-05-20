Termii Notifier
=============

Provides [Termii](https://www.termii.com) integration for Symfony Notifier.

DSN example
-----------

```
TERMII_DSN=termii://API_KEY@default?from=FROM&channel=CHANNEL
```

where:

 - `API_KEY` is your Termii API key
 - `FROM` is your sender
 - `CHANNEL` is your channel (generic, dnd, whatsapp)

Adding Options to a Message
---------------------------

With a Termii Message, you can use the `TermiiOptions` class to add
[message options](https://developer.termii.com/messaging#send-message).

```php
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Bridge\Termii\TermiiOptions;

$sms = new SmsMessage('+1411111111', 'My message');

$options = (new TermiiOptions())
    ->type('test_type')
    ->channel('test_channel')
    ->media('test_media_url', 'test_media_caption')
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
