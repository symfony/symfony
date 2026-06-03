MessageBird Notifier
====================

Provides [MessageBird](https://www.messagebird.com/) integration for Symfony Notifier.

DSN example
-----------

```
MESSAGEBIRD_DSN=messagebird://TOKEN@default?from=FROM
```

where:
 - `TOKEN` is your MessageBird token
 - `FROM` is your sender

Adding Options to a Message
---------------------------

With a MessageBird Message, you can use the `MessageBirdOptions` class to add
[message options](https://developers.messagebird.com/api/sms-messaging/#send-outbound-sms).

```php
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Bridge\MessageBird\MessageBirdOptions;

$sms = new SmsMessage('+1411111111', 'My message');

$options = (new MessageBirdOptions())
    ->type('test_type')
    ->scheduledDatetime('test_scheduled_datetime')
    ->createdDatetime('test_created_datetime')
    ->dataCoding('test_data_coding')
    ->gateway(999)
    ->groupIds(['test_group_ids'])
    ->mClass(888)
    ->reference('test_reference')
    ->reportUrl('test_report_url')
    ->shortenUrls(true)
    ->typeDetails('test_type_details')
    ->validity(777)
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
