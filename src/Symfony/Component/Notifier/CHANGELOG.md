CHANGELOG
=========

5.2.0
-----

 * [BC BREAK] The `TransportInterface::send()` and `AbstractTransport::doSend()` methods changed to return a `?SentMessage` instance instead of `void`.
 * Added the Zulip notifier bridge
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

 * Added the Mattermost notifier bridge
 * [BC BREAK] The `ChatMessage::fromNotification()` method's `$recipient` and `$transport`
   arguments were removed.
 * [BC BREAK] The `EmailMessage::fromNotification()` and `SmsMessage::fromNotification()`
   methods' `$transport` argument was removed.

5.0.0
-----

 * Introduced the component as experimental
