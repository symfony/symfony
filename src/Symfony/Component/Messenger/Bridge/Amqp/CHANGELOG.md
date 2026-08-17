CHANGELOG
=========

8.2
---

 * Round delays up to two significant digits and add the `delay[granularity]` option to control that rounding
 * Implement the `KeepaliveReceiverInterface` to enable asynchronously notifying AMQP that the job is still being processed, in order to avoid timeouts
 * Pass the routing key to the serializer as `extra[routing_key]` when decoding

8.1
---

 * Allow setting `queues` to `false` to skip binding the default `messages` queue
 * Add option `delay[daily_delay_queues]` in the transport definition

7.3
---

 * Add default exchange support

7.1
---

 * Implement the `CloseableTransportInterface` to allow closing the AMQP connection
 * Add option `delay[arguments]` in the transport definition

6.4
---

 * Add option `delay[daily_delay_queues]` in the transport definition

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
