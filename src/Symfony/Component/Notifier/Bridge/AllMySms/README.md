AllMySms Notifier
=================

Provides [AllMySms](https://www.allmysms.com/) integration for Symfony Notifier.

DSN example
-----------

```
ALLMYSMS_DSN=allmysms://LOGIN:APIKEY@default?from=FROM
```

where:
 - `LOGIN` is your user ID
 - `APIKEY` is your AllMySms API key
 - `FROM` is your sender (optional, default: 36180)

Adding Options to a Message
---------------------------

With a AllMySms Message, you can use the `AllMySmsOptions` class to add
[message options](https://doc.allmysms.com/api/allmysms_api_https_v9.0_EN.pdf).

```php
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Bridge\AllMySms\AllMySmsOptions;

$sms = new SmsMessage('+1411111111', 'My message');

$options = (new AllMySmsOptions())
    ->alerting(1)
    ->campaignName('API')
    ->cliMsgId('test_cli_msg_id')
    ->date('2023-05-23 23:47:25')
    ->simulate(1)
    ->uniqueIdentifier('unique_identifier')
    ->verbose(1)
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
