GatewayApi Notifier
===================

Provides GatewayApi integration for Symfony Notifier.

DSN example
-----------

```
GATEWAYAPI_DSN=gatewayapi://TOKEN@default?from=FROM
```

where:
 - `TOKEN` is API Token (OAuth)
 - `FROM` is sender name

See your account info at https://gatewayapi.com

Adding Options to a Message
---------------------------

With a GatewayApi Message, you can use the `GatewayApiOptions` class to add
[message options](https://gatewayapi.com/docs/apis/rest/).

```php
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Bridge\GatewayApi\GatewayApiOptions;

$sms = new SmsMessage('+1411111111', 'My message');

$options = (new GatewayApiOptions())
    ->class('standard')
    ->callbackUrl('https://my-callback-url')
    ->userRef('user_ref')
    ->label('label')
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
