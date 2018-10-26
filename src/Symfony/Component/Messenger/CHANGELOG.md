CHANGELOG
=========

4.2.0
-----

 * The component is not experimental anymore
 * All the changes below are BC BREAKS
 * subscribing to messages based on their interfaces or parent classes has been removed in favor of topic-based subscription
 * `MessageBusInterface::dispatch()`, `MiddlewareInterface::handle()` and `SenderInterface::send()` return `Envelope`
 * `MessageBusInterface::dispatch()` now takes a second `string $topic = null` argument
 * `MiddlewareInterface::handle()` now require an `Envelope` as first argument and a `StackInterface` as second
 * `EnvelopeAwareInterface` has been removed
 * The signature of `Amqp*` classes changed to take a `Connection` as a first argument and an optional
   `Serializer` as a second argument.
 * `SenderLocator` has been renamed to `ContainerSenderLocator`
   Be careful as there is still a `SenderLocator` class, but it does not rely on a `ContainerInterface` to find senders.
   Instead, it accepts the sender instance itself instead of its identifier in the container.
 * `MessageSubscriberInterface::getHandledMessages()` return value has changed. The value of an array item
   needs to be an associative array or the method name.
 * `StampInterface` replaces `EnvelopeItemInterface` and doesn't extend `Serializable` anymore
 * The `ConsumeMessagesCommand` class now takes an instance of `Psr\Container\ContainerInterface`
   as first constructor argument
 * The `EncoderInterface` and `DecoderInterface` have been replaced by a unified `Symfony\Component\Messenger\Transport\Serialization\SerializerInterface`.
 * The locator passed to `ContainerHandlerLocator` should not prefix its keys by "handler." anymore
 * Renamed `EnvelopeItemInterface` to `StampInterface`
 * `Envelope`'s constructor and `with()` method now accept `StampInterface` objects as variadic parameters
 * Renamed and moved `ReceivedMessage`, `ValidationConfiguration` and `SerializerConfiguration` in the `Stamp` namespace
 * Removed the `WrapIntoReceivedMessage`
 * `MessengerDataCollector::getMessages()` returns an iterable, not just an array anymore
 * The `AbstractHandlerLocator` and `AbstractSenderLocator` classes have been removed
 * `HandlerLocatorInterface::resolve()` has been replaced by `getHandler(string $topic): ?callable` and shouldn't throw when no handlers are found
 * `SenderLocatorInterface::getSenderForMessage()` has been replaced by `getSender(string $topic): ?SenderInterface`
 * Classes in the `Middleware\Enhancers` sub-namespace have been moved to the `Middleware` one
 * Classes in the `Asynchronous\Routing` sub-namespace have been moved to the `Transport\Sender\Locator` sub-namespace
 * The `Asynchronous/Middleware/SendMessageMiddleware` class has been moved to the `Middleware` namespace
 * `SenderInterface` and `ChainSender` classes have been moved to the `Transport\Sender` sub-namespace
 * `ReceiverInterface` and its implementations have been moved to the `Transport\Receiver` sub-namespace
 * `ActivationMiddlewareDecorator` has been renamed `ActivationMiddleware`
 * `AllowNoHandlerMiddleware` has been removed in favor of a new constructor argument on `HandleMessageMiddleware`

4.1.0
-----

 * Introduced the component as experimental
