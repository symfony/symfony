Mercure Notifier
================

Provides [Mercure](https://github.com/symfony/mercure) integration for Symfony Notifier.

DSN example
-----------

```
MERCURE_DSN=mercure://HUB_ID?topic=TOPIC
```

where:
 - `HUB_ID` is the Mercure hub id
 - `TOPIC` is the topic IRI (optional, default: `https://symfony.com/notifier`. Could be either a single topic: `topic=https://foo` or multiple topics: `topic[]=/foo/1&topic[]=https://bar`)


Adding Options to a Chat Message
--------------------------------

With a Mercure Chat Message, you can use the `MercureOptions` class to add
message options.

```php
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Bridge\Mercure\MercureOptions;

$chatMessage = new ChatMessage('Contribute To Symfony');

$options = new MercureOptions(
    ['/topic/1', '/topic/2'],
    true,
    'id',
    'type',
    1,
    ['tag' => '1234', 'body' => 'TEST']
);

// Add the custom options to the chat message and send the message
$chatMessage->options($options);

$chatter->send($chatMessage);
```

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
