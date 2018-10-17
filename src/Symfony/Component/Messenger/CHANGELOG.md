CHANGELOG
=========

4.2.0
-----

 * The component is not experimental anymore
 * All the changes below are BC BREAKS
 * `MessageBusInterface::dispatch()` and `MiddlewareInterface::handle()` now return `void`
 * `MiddlewareInterface::handle()` now require an `Envelope` as first argument
 * `EnvelopeAwareInterface` has been removed
 * The signature of `Amqp*` classes changed to take a `Connection` as a first argument and an optional
   `Serializer` as a second argument.
 * `SenderLocator` has been renamed to `ContainerSenderLocator`
   Be careful as there is still a `SenderLocator` class, but it does not rely on a `ContainerInterface` to find senders.
   Instead, it accepts the sender instance itself instead of its identifier in the container.
 * `MessageSubscriberInterface::getHandledMessages()` return value has changed. The value of an array item
   needs to be an associative array or the method name.
 * `ValidationMiddleware::handle()` and `SendMessageMiddleware::handle()` now require an `Envelope` object
 * `StampInterface` replaces `EnvelopeItemInterface` and doesn't extend `Serializable` anymore
 * The `ConsumeMessagesCommand` class now takes an instance of `Psr\Container\ContainerInterface`
   as first constructor argument
 * The `EncoderInterface` and `DecoderInterface` have been replaced by a unified `Symfony\Component\Messenger\Transport\Serialization\SerializerInterface`.
 * The locator passed to `ContainerHandlerLocator` should not prefix its keys by "handler." anymore
 * The `AbstractHandlerLocator::getHandler()` method uses `?callable` as return type
 * Renamed `EnvelopeItemInterface` to `StampInterface`
 * `Envelope`'s constructor and `with()` method now accept `StampInterface` objects as variadic parameters
 * Renamed and moved `ReceivedMessage`, `ValidationConfiguration` and `SerializerConfiguration` in the `Stamp` namespace
 * Removed the `WrapIntoReceivedMessage`
 * `SenderLocatorInterface::getSenderForMessage()` has been replaced by `getSender(Envelope $envelope)`
 * `MessengerDataCollector::getMessages()` returns an iterable, not just an array anymore
 * `AbstractHandlerLocator` is now internal
 * `HandlerLocatorInterface::resolve()` has been replaced by `getHandler(Envelope $envelope)`
 * `SenderLocatorInterface::getSenderForMessage()` has been replaced by `getSender(Envelope $envelope)`
 * `SenderInterface::send()` returns `void`

4.1.0
-----

 * Introduced the component as experimental
