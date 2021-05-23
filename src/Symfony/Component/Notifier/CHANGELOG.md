CHANGELOG
=========

5.3
---

 * The component is not marked as `@experimental` anymore
 * [BC BREAK] Change signature of `Dsn::__construct()` method from:
   `public function __construct(string $scheme, string $host, ?string $user = null, ?string $password = null, ?int $port = null, array $options = [], ?string $path = null)`
   to:
   `public function __construct(string $dsn)`
 * [BC BREAK] Remove `Dsn::fromString()` method
 * [BC BREAK] Change the return type of `AbstractTransportFactory::getEndpoint()` from `?string` to `string`
 * Add `DSN::getRequiredOption` method which throws a new `MissingRequiredOptionException`

5.2.0
-----

 * [BC BREAK] Change the `TransportInterface::send()` and `AbstractTransport::doSend()` methods to return a `?SentMessage` instance instead of `void`
 * Introduce the `EmailRecipientInterface` and `RecipientInterface`
 * Add `email` and `phone` properties to `Recipient`
 * [BC BREAK] Change the type-hint of the `$recipient` argument in the `as*Message()` method of `EmailNotificationInterface` and `SmsNotificationInterface` to `EmailRecipientInterface`and `SmsRecipientInterface`
 * [BC BREAK] Remove the `AdminRecipient`
 * The `EmailRecipientInterface` and `SmsRecipientInterface` now extend the `RecipientInterface`
 * Introduce the `EmailRecipient` and `SmsRecipient`
 * [BC BREAK] Change the type-hint of the `$recipient` argument in `NotifierInterface::send()`, `Notifier::getChannels()`, `ChannelInterface::notifiy()` and `ChannelInterface::supports()` to `RecipientInterface`
 * Change `EmailChannel` to only support recipients which implement the `EmailRecipientInterface`
 * Change `SmsChannel` to only support recipients which implement the `SmsRecipientInterface`

5.1.0
-----

 * [BC BREAK] Remove the `ChatMessage::fromNotification()` method's `$recipient` and `$transport` arguments
 * [BC BREAK] Remove the `EmailMessage::fromNotification()` and `SmsMessage::fromNotification()` methods' `$transport` argument

5.0.0
-----

 * Introduce the component as experimental
