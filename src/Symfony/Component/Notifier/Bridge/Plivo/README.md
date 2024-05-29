Plivo Notifier
==============

Provides [Plivo](https://www.plivo.com) integration for Symfony Notifier.

DSN example
-----------

```
PLIVO_DSN=plivo://AUTH_ID:AUTH_TOKEN@default?from=FROM
```

where:

 - `AUTH_ID` is your Plivo Auth ID
 - `AUTH_TOKEN` is your Plivo Auth Token
 - `FROM` is your sender

Adding Options to a Message
---------------------------

With a Plivo Message, you can use the `PlivoOptions` class to add
[message options](https://www.plivo.com/docs/sms/api/message#send-a-message).

```php
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Bridge\Plivo\PlivoOptions;

$sms = new SmsMessage('+1411111111', 'My message');

$options = (new PlivoOptions())
    ->log(true)
    ->method('POST')
    ->url('url')
    ->mediaUrls('media_urls')
    ->powerpackUuid('uuid')
    ->trackable(true)
    ->type('sms')
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
