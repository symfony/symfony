CHANGELOG
=========

5.2.0
-----

 * added `NativeTransportFactory` to configure a transport based on php.ini settings
 * added `local_domain`, `restart_threshold`, `restart_threshold_sleep` and `ping_threshold` options for `smtp`
 * added `command` option for `sendmail`

4.4.0
-----

 * [BC BREAK] changed the `NullTransport` DSN from `smtp://null` to `null://null`
 * [BC BREAK] renamed `SmtpEnvelope` to `Envelope`, renamed `DelayedSmtpEnvelope` to
   `DelayedEnvelope`
 * [BC BREAK] changed the syntax for failover and roundrobin DSNs

   Before:

   dummy://a || dummy://b (for failover)
   dummy://a && dummy://b (for roundrobin)

   After:

   failover(dummy://a dummy://b)
   roundrobin(dummy://a dummy://b)

 * added support for multiple transports on a `Mailer` instance
 * [BC BREAK] removed the `auth_mode` DSN option (it is now always determined automatically)
 * STARTTLS cannot be enabled anymore (it is used automatically if TLS is disabled and the server supports STARTTLS)
 * [BC BREAK] Removed the `encryption` DSN option (use `smtps` instead)
 * Added support for the `smtps` protocol (does the same as using `smtp` and port `465`)
 * Added PHPUnit constraints
 * Added `MessageDataCollector`
 * Added `MessageEvents` and `MessageLoggerListener` to allow collecting sent emails
 * [BC BREAK] `TransportInterface` has a new `__toString()` method
 * [BC BREAK] Classes `AbstractApiTransport` and `AbstractHttpTransport` moved under `Transport` sub-namespace.
 * [BC BREAK] Transports depend on `Symfony\Contracts\EventDispatcher\EventDispatcherInterface`
   instead of `Symfony\Component\EventDispatcher\EventDispatcherInterface`.
 * Added possibility to register custom transport for dsn by implementing
   `Symfony\Component\Mailer\Transport\TransportFactoryInterface` and tagging with `mailer.transport_factory` tag in DI.
 * Added `Symfony\Component\Mailer\Test\TransportFactoryTestCase` to ease testing custom transport factories.
 * Added `SentMessage::getDebug()` and `TransportExceptionInterface::getDebug` to help debugging
 * Made `MessageEvent` final
 * add DSN parameter `verify_peer` to disable TLS peer verification for SMTP transport

4.3.0
-----

 * Added the component.
