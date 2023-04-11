Firebase Notifier
=================

Provides [Firebase](https://firebase.google.com) integration for Symfony Notifier.

DSN example
-----------

```
FIREBASE_DSN=firebase://USERNAME:PASSWORD@default
```

where:
 - `USERNAME` is your Firebase username
 - `PASSWORD` is your Firebase password

Adding Interactions to a Message
--------------------------------

With a Firebase message, you can use the `AndroidNotification`, `IOSNotification` or `WebNotification` classes to add
[message options](https://firebase.google.com/docs/cloud-messaging/xmpp-server-ref.html).

```php
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Bridge\Firebase\Notification\AndroidNotification;

$chatMessage = new ChatMessage('');

// Create AndroidNotification options
$androidOptions = (new AndroidNotification('/topics/news', []))
    ->icon('myicon')
    ->sound('default')
    ->tag('myNotificationId')
    ->color('#cccccc')
    ->clickAction('OPEN_ACTIVITY_1')
    // ...
    ;

// Add the custom options to the chat message and send the message
$chatMessage->options($androidOptions);

$chatter->send($chatMessage);
```

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
