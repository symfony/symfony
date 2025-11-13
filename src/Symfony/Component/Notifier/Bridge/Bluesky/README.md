Bluesky Notifier
================

Provides [Bluesky](https://bsky.app/) integration for Symfony Notifier.

DSN example
-----------

```
BLUESKY_DSN=bluesky://nyholm.bsky.social:p4ssw0rd@bsky.social
```

Adding Options to a Message
---------------------------

Use a `BlueskyOptions` object to add options to the message:

```php
use Symfony\Component\Notifier\Bridge\Bluesky\BlueskyOptions;
use Symfony\Component\Notifier\Message\ChatMessage;

$message = new ChatMessage('My message');

// Add website preview card to the message
$options = (new BlueskyOptions())
    ->attachCard('https://example.com', new File('image.jpg'))
    // You can also add media to the message
    //->attachMedia(new File($command->fileName), 'description')
    ;

// Add the custom options to the Bluesky message and send the message
$message->options($options);

$chatter->send($message);
```

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
