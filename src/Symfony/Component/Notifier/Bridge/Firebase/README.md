Firebase Notifier
=================

Provides [Firebase](https://firebase.google.com) integration for Symfony Notifier.

JWT DSN Example (HTTP v1)
-----------

```
FIREBASE_DSN=firebase://<CLIENT_EMAIL>?project_id=<PROJECT_ID>&private_key_id=<PRIVATE_KEY_ID>&private_key=<PRIVATE_KEY>
FIREBASE_DSN=firebase://firebase-adminsdk@stag.iam.gserviceaccount.com?project_id=<PROJECT_ID>&private_key_id=<PRIVATE_KEY_ID>&private_key=<PRIVATE_KEY>
```

Since __"private_key"__ is long, you must write it in a single line with "\n". Example:
```
-----BEGIN RSA PRIVATE KEY-----\n.....\n....\n-----END RSA PRIVATE KEY-----
```

__Required Options:__
* client_email
* project_id
* private_key_id
* private_key


Adding Interactions to a Message
--------------------------------

With a Firebase message, you can use the `AndroidNotification`, `IOSNotification` or `WebNotification` classes to add
[message options](https://firebase.google.com/docs/cloud-messaging/xmpp-server-ref.html).

```php
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Bridge\Firebase\Notification\AndroidNotification;

$chatMessage = new ChatMessage('');

// Create AndroidNotification options
$androidOptions = (new AndroidNotification('/topics/news', [], [], true))
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
