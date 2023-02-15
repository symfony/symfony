CHANGELOG
=========

5.4.21
------

 * [BC BREAK] The following data providers for `TransportTestCase` are now static: `toStringProvider()`, `supportedMessagesProvider()` and `unsupportedMessagesProvider()`
 * [BC BREAK] `TransportTestCase::createTransport()` is now static

5.4
---

 * Add `SentMessageEvent` and `FailedMessageEvent`
 * Add `push` channel

5.3
---

 * The component is not marked as `@experimental` anymore
 * [BC BREAK] Change signature of `Dsn::__construct()` method from:
   `public function __construct(string $scheme, string $host, ?string $user = null, ?string $password = null, ?int $port = null, array $options = [], ?string $path = null)`
   to:
   `public function __construct(string $dsn)`
 * [BC BREAK] Remove `Dsn::fromString()` method
 * [BC BREAK] Changed the return type of `AbstractTransportFactory::getEndpoint()` from `?string` to `string`
 * Added `DSN::getRequiredOption` method which throws a new `MissingRequiredOptionException`.

5.2.0
-----

 * [BC BREAK] The `TransportInterface::send()` and `AbstractTransport::doSend()` methods changed to return a `?SentMessage` instance instead of `void`.
 * The `EmailRecipientInterface` and `RecipientInterface` were introduced.
 * Added `email` and `phone` properties to `Recipient`.
 * [BC BREAK] Changed the type-hint of the `$recipient` argument in the `as*Message()` method
   of `EmailNotificationInterface` and `SmsNotificationInterface` to `EmailRecipientInterface`
   and `SmsRecipientInterface`.
 * [BC BREAK] Removed the `AdminRecipient`.
 * The `EmailRecipientInterface` and `SmsRecipientInterface` now extend the `RecipientInterface`.
 * The `EmailRecipient` and `SmsRecipient` were introduced.
 * [BC BREAK] Changed the type-hint of the `$recipient` argument in `NotifierInterface::send()`,
   `Notifier::getChannels()`, `ChannelInterface::notifiy()` and `ChannelInterface::supports()` to
   `RecipientInterface`.
 * Changed `EmailChannel` to only support recipients which implement the `EmailRecipientInterface`.
 * Changed `SmsChannel` to only support recipients which implement the `SmsRecipientInterface`.

5.1.0
-----

 * [BC BREAK] The `ChatMessage::fromNotification()` method's `$recipient` and `$transport`
   arguments were removed.
 * [BC BREAK] The `EmailMessage::fromNotification()` and `SmsMessage::fromNotification()`
   methods' `$transport` argument was removed.

5.0.0
-----

 * Introduced the component as experimental
