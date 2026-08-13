SMS Proxima Notifier
--------------------

Provides [SMS Proxima](https://sms-proxima.com) integration for Symfony Notifier.

DSN example
-----------

```
SMS_PROXIMA_DSN=sms-proxima://TOKEN@default?from=FROM
```

where:

 - `TOKEN` is your SMS Proxima API token
 - `FROM` is the sender name shown on the recipient's phone, 11 characters at most

Options
-------

```php
use Symfony\Component\Notifier\Bridge\SmsProxima\SmsProximaOptions;
use Symfony\Component\Notifier\Message\SmsMessage;

$sms = new SmsMessage('+33612345678', 'Hello!');

$sms->options((new SmsProximaOptions())
    ->sandbox(true)                      // test mode, nothing is sent and no credit is used
    ->stop(false)                        // disable the STOP mention, transactional messages only
    ->timeToSend('2026-12-25 10:00')     // schedule the message
    ->idempotencyKey('unique-uuid-here') // prevent duplicate sends
);

$texter->send($sms);
```

See SMS Proxima documentation at https://sms-proxima.com/api-sms

Sponsor
-------

This package is looking for a [backer][1].

Help Symfony by [sponsoring][3] its development!

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)

[1]: https://symfony.com/backers
[3]: https://symfony.com/sponsor
