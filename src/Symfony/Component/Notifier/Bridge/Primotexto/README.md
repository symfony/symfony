Primotexto Notifier
===================

Provides [Primotexto](https://www.primotexto.com/) integration for Symfony Notifier.

DSN example
-----------

```
PRIMOTEXTO_DSN=primotexto://APIKEY@default?from=FROM
```

where:
  - `APIKEY` is your Primotexto API key
  - `FROM` is your sender name

Adding Options to a Message
---------------------------

With a Primotexto Message, you can use the `PrimotextoOptions` class to add
[message options](https://www.primotexto.com/api/sms/notification.asp).

```php
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Bridge\Primotexto\PrimotextoOptions;

$sms = new SmsMessage('+1411111111', 'My message');

$options = (new PrimotextoOptions())
    ->campaignName('Code de confirmation')
    ->category('codeConfirmation')
    ->campaignDate(1398177000000)
    // ...
    ;

// Add the custom options to the sms message and send the message
$sms->options($options);

$texter->send($sms);
```

Resources
---------

 * [Primotexto error codes](https://www.primotexto.com/api/plus/code_erreurs)

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
