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
    /** @var array<string, mixed> */
    private array $overrides = [];

    /** @var array<string, mixed> */
    private array $context = [];

    /**
     * @param array<string, mixed> $overrides
     */
    public function setOverrides(array $overrides): void
    {
        $this->overrides = $overrides;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function setContext(array $context): void
    {
        $this->context = $context;
    }

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
                $this->overrides,
                [],
                $this->context,
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
$notification->setOverrides([
    'email' => [
        'from' => 'no-reply@toppy.nl',
        'senderName' => 'No-Reply',
    ],
]);
$notification->setContext([
    'tenant' => 'tenant-id',
    'app' => 'app-id',
]);

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
    ),
);
```

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
