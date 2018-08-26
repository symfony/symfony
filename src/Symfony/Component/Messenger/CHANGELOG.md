CHANGELOG
=========

4.2.0
-----

 * `ValidationMiddleware::handle()` and `SendMessageMiddleware::handle()` now require an `Envelope` object
 * `EnvelopeItemInterface` doesn't extend `Serializable` anymore
 * [BC BREAK] The `ConsumeMessagesCommand` class now takes an instance of `Psr\Container\ContainerInterface` 
   as first constructor argument
