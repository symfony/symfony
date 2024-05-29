ClickSend Notifier
==================

Provides [ClickSend](https://www.clicksend.com/) integration for Symfony Notifier.

DSN example
-----------

```
CLICKSEND_DSN=clicksend://API_USERNAME:API_KEY@default?from=FROM&source=SOURCE&list_id=LIST_ID&from_email=FROM_EMAIL
```

where:

 - `API_USERNAME` is your ClickSend API username
 - `API_KEY` is your ClickSend API key
 - `FROM` is your sender (optional)
 - `SOURCE` is your source method of sending (optional)
 - `LIST_ID` is your recipient list ID (optional)
 - `FROM_EMAIL` is your from email where replies must be emailed (optional)

Adding Options to a Message
---------------------------

With a ClickSend Message, you can use the `ClickSendOptions` class to add
[message options](https://developers.clicksend.com/docs/rest/v3/#send-sms/).

```php
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Bridge\ClickSend\ClickSendOptions;

$sms = new SmsMessage('+1411111111', 'My message');

$options = (new ClickSendOptions())
    ->country('country')
    ->customString('custom_string')
    ->fromEmail('from_email')
    ->listId('list_id')
    ->schedule(999)
    ->source('source')
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
