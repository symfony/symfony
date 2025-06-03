Novu Notifier
=============

Provides [Novu](https://novu.co/) integration for Symfony Notifier.

DSN example
-----------

```
NOVU_DSN=novu://API_KEY@default
```

Notification example
--------------------

```php
class NovuNotification extends Notification implements PushNotificationInterface
{
    public function asPushMessage(
        NovuSubscriberRecipient|RecipientInterface $recipient,
        ?string $transport = null,
    ): ?PushMessage {
        return new PushMessage(
            $this->getSubject(),
            $this->getContent(),
            new NovuOptions(
                $recipient->getSubscriberId(),
                $recipient->getFirstName(),
                $recipient->getLastName(),
                $recipient->getEmail(),
                $recipient->getPhone(),
                $recipient->getAvatar(),
                $recipient->getLocale(),
                $recipient->getOverrides(),
                [],
            ),
        );
    }
}
```

```php
$notification = new NovuNotification;
$notification->subject('test');
$notification->channels(['push']);
$notification->content(
    json_encode(
        [
            'param1' => 'Lorum Ipsum',
        ]
    )
);

$this->notifier->send(
    $notification,
    new NovuSubscriberRecipient(
        "123",
        'Wouter',
        'van der Loop',
        'woutervdl@toppy.nl',
        null,
        null,
        null,
        [
            'email' => [
                'from' => 'no-reply@toppy.nl',
                'senderName' => 'No-Reply',
            ],
        ],
    ),
);
```

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
