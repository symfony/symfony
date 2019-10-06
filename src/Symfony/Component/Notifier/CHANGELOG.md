CHANGELOG
=========

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
