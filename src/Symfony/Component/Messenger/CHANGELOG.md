CHANGELOG
=========

4.2.0
-----

 * The component is not experimental anymore
 * [BC BREAK] The signature of `Amqp*` classes changed to take a `Connection` as a first argument and an optional
   `Serializer` as a second argument.
 * [BC BREAK] `SenderLocator` has been renamed to `ContainerSenderLocator`
   Be careful as there is still a `SenderLocator` class, but it does not rely on a `ContainerInterface` to find senders.
   Instead, it accepts the sender instance itself instead of its identifier in the container.
 * [BC BREAK] `MessageSubscriberInterface::getHandledMessages()` return value has changed. The value of an array item
   needs to be an associative array or the method name.
 * `ValidationMiddleware::handle()` and `SendMessageMiddleware::handle()` now require an `Envelope` object
 * `EnvelopeItemInterface` doesn't extend `Serializable` anymore
 * [BC BREAK] The `ConsumeMessagesCommand` class now takes an instance of `Psr\Container\ContainerInterface` 
   as first constructor argument
 * [BC BREAK] The `EncoderInterface` and `DecoderInterface` have been replaced by a unified `Symfony\Component\Messenger\Transport\Serialization\SerializerInterface`.
 * [BC BREAK] The locator passed to `ContainerHandlerLocator` should not prefix its keys by "handler." anymore
 * [BC BREAK] The `AbstractHandlerLocator::getHandler()` method uses `?callable` as return type
 * Added `AllowSingleHandlerMiddleware` for message buses which behave like command buses.

4.1.0
-----

 * Introduced the component as experimental
