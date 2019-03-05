CHANGELOG
=========

4.3.0
-----

 * Added `PhpSerializer` which uses PHP's native `serialize()` and
   `unserialize()` to serialize messages to a transport

 * [BC BREAK] If no serializer were passed, the default serializer
   changed from `Serializer` to `PhpSerializer` inside `AmqpReceiver`,
   `AmqpSender`, `AmqpTransport` and `AmqpTransportFactory`.

 * Added `TransportException` to mark an exception transport-related

 * [BC BREAK] If listening to exceptions while using `AmqpSender` or `AmqpReceiver`, `\AMQPException` is
   no longer thrown in favor of `TransportException`.
   
 * Added `prefetch_count` AMQP option which set the channel prefetch count
 
 * Added `consume_fatal` (default `true`) and `consume_requeue` (default `false`) AMQP options which allow consumer to
   continue processing messages or nack it with requeue.

 * Added `UnrecoverableMessageExceptionInterface` and `RecoverableMessageExceptionInterface` into AMQP transport
   exception for nack with or without requeue, and then continue to consume the other messages.
 

4.2.0
-----

 * Added `HandleTrait` leveraging a message bus instance to return a single 
   synchronous message handling result
 * Added `HandledStamp` & `SentStamp` stamps
 * All the changes below are BC BREAKS
 * Senders and handlers subscribing to parent interfaces now receive *all* matching messages, wildcard included
 * `MessageBusInterface::dispatch()`, `MiddlewareInterface::handle()` and `SenderInterface::send()` return `Envelope`
 * `MiddlewareInterface::handle()` now require an `Envelope` as first argument and a `StackInterface` as second
 * `EnvelopeAwareInterface` has been removed
 * The signature of `Amqp*` classes changed to take a `Connection` as a first argument and an optional
   `Serializer` as a second argument.
 * `MessageSubscriberInterface::getHandledMessages()` return value has changed. The value of an array item
   needs to be an associative array or the method name.
 * `StampInterface` replaces `EnvelopeItemInterface` and doesn't extend `Serializable` anymore
 * The `ConsumeMessagesCommand` class now takes an instance of `Psr\Container\ContainerInterface`
   as first constructor argument
 * The `EncoderInterface` and `DecoderInterface` have been replaced by a unified `Symfony\Component\Messenger\Transport\Serialization\SerializerInterface`.
 * Renamed `EnvelopeItemInterface` to `StampInterface`
 * `Envelope`'s constructor and `with()` method now accept `StampInterface` objects as variadic parameters
 * Renamed and moved `ReceivedMessage`, `ValidationConfiguration` and `SerializerConfiguration` in the `Stamp` namespace
 * Removed the `WrapIntoReceivedMessage` class
 * `MessengerDataCollector::getMessages()` returns an iterable, not just an array anymore
 * `HandlerLocatorInterface::resolve()` has been removed, use `HandlersLocator::getHandlers()` instead
 * `SenderLocatorInterface::getSenderForMessage()` has been removed, use `SendersLocator::getSenders()` instead
 * Classes in the `Middleware\Enhancers` sub-namespace have been moved to the `Middleware` one
 * Classes in the `Asynchronous\Routing` sub-namespace have been moved to the `Transport\Sender\Locator` sub-namespace
 * The `Asynchronous/Middleware/SendMessageMiddleware` class has been moved to the `Middleware` namespace
 * `SenderInterface` has been moved to the `Transport\Sender` sub-namespace
 * The `ChainHandler` and `ChainSender` classes have been removed
 * `ReceiverInterface` and its implementations have been moved to the `Transport\Receiver` sub-namespace
 * `ActivationMiddlewareDecorator` has been renamed `ActivationMiddleware`
 * `AllowNoHandlerMiddleware` has been removed in favor of a new constructor argument on `HandleMessageMiddleware`
 * The `ContainerHandlerLocator`, `AbstractHandlerLocator`, `SenderLocator` and `AbstractSenderLocator` classes have been removed
 * `Envelope::all()` takes a new optional `$stampFqcn` argument and returns the stamps for the specified FQCN, or all stamps by their class name
 * `Envelope::get()` has been renamed `Envelope::last()`

4.1.0
-----

 * Introduced the component as experimental
