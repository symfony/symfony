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
use Symfony\Component\Notifier\Bridge\Smsbox\Enum\Charset;
use Symfony\Component\Notifier\Bridge\Smsbox\Enum\Day;
use Symfony\Component\Notifier\Bridge\Smsbox\Enum\Encoding;
use Symfony\Component\Notifier\Bridge\Smsbox\Enum\Mode;
use Symfony\Component\Notifier\Bridge\Smsbox\Enum\Strategy;
use Symfony\Component\Notifier\Bridge\Smsbox\Enum\Udh;
use Symfony\Component\Notifier\Bridge\Smsbox\SmsboxOptions;
use Symfony\Component\Notifier\Message\SmsMessage;

$sms = new SmsMessage('+33123456789', 'Your %1% message %2%');
$options = (new SmsboxOptions())
    ->mode(Mode::Expert)
    ->strategy(Strategy::NotMarketingGroup)
    ->sender('Your sender')
    ->date('DD/MM/YYYY')
    ->hour('HH:MM')
    ->coding(Encoding::Unicode)
    ->charset(Charset::Iso1)
    ->udh(Udh::DisabledConcat)
    ->callback(true)
    ->allowVocal(true)
    ->maxParts(2)
    ->validity(100)
    ->daysMinMax(min: Day::Tuesday, max: Day::Friday)
    ->hoursMinMax(min: 8, max: 10)
    ->variable(['variable1', 'variable2'])
    ->dateTime(new \DateTime())
    ->destIso('FR');

$sms->options($options);
$texter->send($sms);
```
## Smsbox notifier also provides Webhooks support. 🚀
