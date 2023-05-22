Smsmode Notifier
================

Provides [Smsmode](https://www.smsmode.com/) integration for Symfony Notifier.

DSN example
-----------

```
SMSMODE_DSN=smsmode://API_KEY@default?from=FROM
```

where:

 - `API_KEY` is your Smsmode API key
 - `FROM` is your sender ID

Adding Options to a Message
---------------------------

With a Smsmode Message, you can use the `SmsmodeOptions` class to add
[message options](https://dev.smsmode.com/sms/v1/message).

```php
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Bridge\Smsmode\SmsmodeOptions;

$sms = new SmsMessage('+1411111111', 'My message');

$options = (new SmsmodeOptions())
    ->refClient('ref_client')
    ->sentDate('sent_date')
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
