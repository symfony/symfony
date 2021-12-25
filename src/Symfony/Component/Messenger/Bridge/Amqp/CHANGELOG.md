CHANGELOG
=========

6.0
---

 * Remove option `prefetch_count`
 * Using invalid options will throw a `LogicException`

5.3
---

 * Deprecated the `prefetch_count` parameter, it has no effect and will be removed in Symfony 6.0.
 * `AmqpReceiver` implements `QueueReceiverInterface` to fetch messages from a specific set of queues.
 * Add ability to distinguish retry and delay actions

5.2.0
-----

 * Add option to confirm message delivery
 * DSN now support AMQPS out-of-the-box.

5.1.0
-----

 * Introduced the AMQP bridge.
 * Deprecated use of invalid options
