CHANGELOG
=========

8.2
---

 * Send messages through the Firebase Cloud Messaging v1 API
 * Deprecate `AndroidNotification`, `IOSNotification` and `WebNotification`, use `FirebaseOptions` instead
 * Deprecate the `firebase://USERNAME:PASSWORD@default` DSN, use `firebase://PROJECT_ID?client_email=...&private_key_id=...&private_key=...` instead
 * Deprecate the `$token` argument of `FirebaseTransport::__construct()`
 * Add the `ssl` DSN option to send requests over plain HTTP

5.3
---

 * The bridge is not marked as `@experimental` anymore
 * Add `data` field to options

5.1.0
-----

 * Added the bridge
