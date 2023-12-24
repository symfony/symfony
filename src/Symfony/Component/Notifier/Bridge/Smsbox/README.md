SMSBOX Notifier
---------------

Provides [SMSBOX](https://www.smsbox.net/en/) integration for Symfony Notifier.

DSN example
-----------

```
SMSBOX_DSN=smsbox://APIKEY@default?mode=MODE&strategy=STRATEGY&sender=SENDER
```

where:

- `APIKEY` is your SMSBOX api key
- `MODE` is the sending mode
- `STRATEGY` is the type of your message
- `SENDER` is the sender name

## You can add numerous options to a message

With a SMSBOX Message, you can use the SmsboxOptions class and use the setters to add [message options](https://www.smsbox.net/en/tools-development#developer-space)

```php
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Bridge\Smsbox\SmsboxOptions;

$sms = new SmsMessage('+33123456789', 'Your %1% message %2%');
$options = (new SmsboxOptions())
    ->mode(SmsboxOptions::MESSAGE_MODE_EXPERT)
    ->strategy(SmsboxOptions::MESSAGE_STRATEGY_NOT_MARKETING_GROUP)
    ->sender('Your sender')
    ->date('DD/MM/YYYY')
    ->hour('HH:MM')
    ->coding(SmsboxOptions::MESSAGE_CODING_UNICODE)
    ->charset(SmsboxOptions::MESSAGE_CHARSET_UTF8)
    ->udh(SmsboxOptions::MESSAGE_UDH_DISABLED_CONCAT)
    ->callback(true)
    ->allowVocal(true)
    ->maxParts(2)
    ->validity(100)
    ->daysMinMax(min: SmsboxOptions::MESSAGE_DAYS_TUESDAY, max: SmsboxOptions::MESSAGE_DAYS_FRIDAY)
    ->hoursMinMax(min: 8, max: 10)                                                      
    ->variable(['variable1', 'variable2'])
    ->dateTime(new \DateTime())
    ->destIso('FR');

$sms->options($options);
$texter->send($sms);
```