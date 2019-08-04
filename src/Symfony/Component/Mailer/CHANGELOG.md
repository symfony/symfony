CHANGELOG
=========

4.4.0
-----

 * Added PHPUnit constraints
 * Added `MessageDataCollector`
 * Added `MessageEvents` and `MessageLoggerListener` to allow collecting sent emails
 * [BC BREAK] `TransportInterface` has a new `getName()` method
 * [BC BREAK] Classes `AbstractApiTransport` and `AbstractHttpTransport` moved under `Transport` sub-namespace.
 * [BC BREAK] Transports depend on `Symfony\Contracts\EventDispatcher\EventDispatcherInterface`
   instead of `Symfony\Component\EventDispatcher\EventDispatcherInterface`.
 * Added possibility to register custom transport for dsn by implementing
   `Symfony\Component\Mailer\Transport\TransportFactoryInterface` and tagging with `mailer.transport_factory` tag in DI.
 * Added `Symfony\Component\Mailer\Test\TransportFactoryTestCase` to ease testing custom transport factories.
 * Added `SentMessage::getDebug()` and `TransportExceptionInterface::getDebug` to help debugging

4.3.0
-----

 * Added the component.
