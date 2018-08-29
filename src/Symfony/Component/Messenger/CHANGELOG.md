CHANGELOG
=========

4.2.0
-----

 * [BC BREAK] `MessageSubscriberInterface::getHandledMessages()` return value has changed. The value of an array item
   needs to be an associative array or the method name. 
 * `ValidationMiddleware::handle()` and `SendMessageMiddleware::handle()` now require an `Envelope` object
 * `EnvelopeItemInterface` doesn't extend `Serializable` anymore
 * [BC BREAK] The `ConsumeMessagesCommand` class now takes an instance of `Psr\Container\ContainerInterface` 
   as first constructor argument
