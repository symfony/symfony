Ring Central Notifier
=====================

Provides [Ring Central](https://www.ringcentral.com) integration for Symfony Notifier.

DSN example
-----------

```
RINGCENTRAL_DSN=ringcentral://API_TOKEN@default?from=FROM
```

where:

 - `API_TOKEN` is your Ring Central OAuth 2 token
 - `FROM` is your sender

Adding Options to a Message
---------------------------

With a Ring Central Message, you can use the `RingCentralOptions` class to add
[message options](https://developers.ringcentral.com/api-reference/SMS/createSMSMessage).

```php
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Bridge\RingCentral\RingCentralOptions;

$sms = new SmsMessage('+1411111111', 'My message');

$options = (new RingCentralOptions())
    ->country(
        'test_country_id',
        'country_iso_code',
        'country_name',
        'country_uri',
        'country_calling_code'
    )
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
