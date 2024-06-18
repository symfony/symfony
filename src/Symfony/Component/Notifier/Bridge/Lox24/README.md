LOX24 SMS Notifier
==================

Provides [LOX24 SMS Gateway](https://doc.lox24.eu/#tag/sms/operation/api_sms_post_collection) integration for Symfony
Notifier.

DSN example
-----------

```
LOX24_DSN=lox24://USER:TOKEN@default?from=FROM&type=TYPE&voice_lang=VOICE_LANGUAGE&delete_text=DELETE_TEXT&callback_data=CALLBACK_DATA
```

where:

 - `USER` (required) is LOX24 user ID.
 - `TOKEN` (required) is LOX24 API v2 token.
 - `FROM` (required) is the sender of the message.
 - `TYPE` (optional) type of message: `sms` (by default) or `voice` (voice call).
 - `VOICE_LANGUAGE` (optional) if `type` is `voice`, then you can set the language of the voice message. Possible
  values: `de`, `en`, `es`, `fr`, `it` or `auto` (by default) per auto-detection.
 - `DELETE_TEXT` (optional) delete SMS text from LOX24 database after sending SMS. Allowed values: `1` (true) or `0` (
  false). Default value: `0`.
 - `CALLBACK_DATA` (optional) additional data for the callback payload.

See your account info at https://account.lox24.eu

Send a Message
--------------

```php
use Symfony\Component\Notifier\Message\SmsMessage;

$sms = new SmsMessage('+1411111111', 'My message');

$texter->send($sms);
```

Advanced Message options
------------------------

```php
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Bridge\Lox24\Lox24Options;

$sms = new SmsMessage('+1411111111', 'My message');

$options = (new Lox24Options())
    // set 'voice' per voice call (text-to-speech)
    ->type('voice')
    // set the language of the voice message.
    // If not set or set 'auto', the automatic language detection by message text will be used
    ->voiceLanguage('en')
    // Date of the SMS delivery. If null or not set, the message will be sent immediately
    ->deliveryAt(new DateTime('2024-03-21 12:17:00'))
    // set True to delete the message from the LOX24 database after delivery
    ->deleteTextAfterSending(true)
    // pass any string to the callback object
    ->callbackData('some_data_per_callback');
    
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
