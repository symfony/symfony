CHANGELOG
=========

5.2.0
-----

 * [BC BREAK] The `TransportInterface::send()` and `AbstractTransport::doSend()` methods changed to return a `SentMessage` instance instead of `void`.

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
