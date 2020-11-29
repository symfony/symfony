Amazon Notifier
=============

Provides Amazon SNS integration for Symfony Notifier.

DSN example
-----------

```
// .env file
AMAZON_DSN=sns://ACCESS_ID:ACCESS_KEY@default?region=eu-west-3
```

Chatter usage
-----------
```php
function sendMessage(ChatterInterface $chatter)
{
    $options = new AmazonSnsOptions('arn:topic');
    $message = new ChatMessage('Hello', $options);
    $chatter->send($message);
}
```

Texter usage
-----------
```php
function sendMessage(TexterInterface $texter)
{
    $message = new SmsMessage('+33600000000', 'Hello');
    $texter->send($message);
}
```

Resources
---------

  * [Contributing](https://symfony.com/doc/current/contributing/index.html)
  * [Report issues](https://github.com/symfony/symfony/issues) and
    [send Pull Requests](https://github.com/symfony/symfony/pulls)
    in the [main Symfony repository](https://github.com/symfony/symfony)
