Bandwidth Notifier
==================

Provides [Bandwidth](https://www.bandwidth.com) integration for Symfony Notifier.

DSN example
-----------

```
BANDWIDTH_DSN=bandwidth://USERNAME:PASSWORD@default?from=FROM&account_id=ACCOUNT_ID&application_id=APPLICATION_ID&priority=PRIORITY
```

where:

- `USERNAME` is your Bandwidth username
- `PASSWORD` is your Bandwidth password
- `FROM` is your sender
- `ACCOUNT_ID` is your account ID
- `APPLICATION_ID` is your application ID
- `PRIORITY` is your priority (optional)

Adding Options to a Message
---------------------------

With a Bandwidth Message, you can use the `BandwidthOptions` class to add
[message options](https://dev.bandwidth.com/apis/messaging/#tag/Messages/operation/createMessage).

```php
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Bridge\Bandwidth\BandwidthOptions;

$sms = new SmsMessage('+1411111111', 'My message');

$options = (new BandwidthOptions())
    ->media(['foo'])
    ->tag('tag')
    ->accountId('account_id')
    ->applicationId('application_id')
    ->expiration('test_expiration')
    ->priority('default')
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
