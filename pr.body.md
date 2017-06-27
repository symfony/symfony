Hello.

I'm happy and excited to share with you 2 new components.

note: The PR description (what you are currently reading) is also committed (as
`pr.body.md`). I will remove it just before the merge. Like that you could also
ask question about the "documentation". But please, don't over-comment the
"language / English". This part of the job will be done in the doc repository.

### AMQP

It is a library created at @SensioLabs few years ago (Mon Mar 18 17:26:01 2013 +0100).
Its goal is to ease the communication with a service that implement [AMQP](https://fr.wikipedia.org/wiki/Advanced_Message_Queuing_Protocol)
For example, [RabbitMQ](http://www.rabbitmq.com/) implements AMQP.

At that time, [Swarrot](https://github.com/swarrot/swarrot) did not exist yet
and only [php-amqplib](https://github.com/php-amqplib/php-amqplib) existed.

We started by using ``php-amqplib`` but we faced many issues: memory leak, bad
handling of signal, poor documentation...

So we decided to stop using it and to build our own library. Over the years, we
added very nice features, we fixed very weird edge cases and we gain real
expertise on AMQP.

Nowadays, it's very common to use AMQP in a web / CLI project.

So four years later, we decided to open-source it and to add it to Symfony to
leverage the Symfony ecosystem (code quality, release process, documentation,
visibility, community, etc.)

So basically it's an abstraction of the [AMQP pecl](https://github.com/pdezwart/php-amqp/).

Here is the README.rst we had for this lib. I have updated it to match the
version that will land in Symfony.

<details>
<summary>The old README (but updated)</summary>

Symfony AMQP
============

Fed up of writing the same boiler-plate code over and over again whenever you
need to use your favorite AMQP broker? Have you a hard time remembering how to
publish a message or how to wire exchanges and queues? I had the exact same
feeling. There are many AMQP libraries providing a very good low-level access to
the AMQP protocol, but what about providing a simple API for abstracting the
most common use cases? This library gives you an opinionated way of using any
AMQP brokers and it also provides a nice and consistent API for low-level
interaction with any AMQP brokers.

Dependencies
------------

This library depends on the ``amqp`` PECL extensions (version 1.4.0-beta2 or
later)::

    sudo apt-get install php-amqp

Using the Conventions
---------------------

The simplest usage of an AMQP broker is sending a message that is consumed by
another script::

    use Symfony\Component\Amqp\Broker;

    // connects to a local AMQP broker by default
    $broker = new Broker();

    // publish a message on the 'log' queue
    $broker->publish('log', 'some message');

    // in another script (non-blocking)
    // $message is false if no messages are in the queue
    $message = $broker->get('log');

    // blocking (waits for a message to be available in the queue)
    $message = $broker->consume('log');

The example above is based on some "conventions" and as such makes the
following assumptions:

* A default exchange is used to publish the message (named
  ``symfony.default``);

* The routing is done via the routing key (``log`` in this example);

* Queues and exchanges are created implicitly when first accessed;

* The connection to the broker is done lazily whenever a message must be sent
  or received.

Retrying a Message
------------------

Retrying processing a message when an error occurs is as easy as defining a
retry strategy on a queue::

    use Symfony\Component\Amqp\RetryStrategy\ConstantRetryStrategy;

    // configure the queue explicitly
    $broker->createQueue('log', array(
        // retry every 5 seconds
        'retry_strategy' => new ConstantRetryStrategy(5),
    ));

Whenever you ``$broker->retry()`` a message, it is going to be automatically re-
enqueued after a ``5`` seconds wait for a retry.

You can also drop the message after a limited number of retries (``2`` in the
following example)::

    $broker->createQueue('log', array(
        // retry 2 times
        'retry_strategy' => new ConstantRetryStrategy(5, 2),
    ));

Instead of trying every ``n`` seconds, you can also use a retry mechanism based
on a truncated exponential backoff algorithm::

    use Symfony\Component\Amqp\RetryStrategy\ExponentialRetryStrategy;

    $broker->createQueue('log', array(
        // retry 5 times
        'retry_strategy' => new ExponentialRetryStrategy(5),
    ));

The message will be re-enqueued after 1 second the first time you call
``retry()``, ``2^1`` seconds the second time, ``2^2`` seconds the third time,
and ``2^n`` seconds the nth time. If you want to wait more than 1 second the
first time, you can pass an offset::

    $broker->createQueue('log', array(
        // starts at 2^3
        'retry_strategy' => new ExponentialRetryStrategy(5, 3),
    ));

.. note::

    The retry strategies are implemented by using the dead-lettering feature of
    AMQP. Behind the scene, a special exchange is bound to queues configured
    based on the retry strategy you set.

.. note::

    Don't forget to ``ack`` or ``nack`` your message if you retry it. And
    obviously you should not use the AMQP_Requeue flag.

Configuring a Broker
--------------------

By default, a broker tries to connect to a local AMQP broker with the default
port, username, and password. If you have a different setting, pass a URI to
the ``Broker`` constructor::

    $broker = new Broker('amqp://user:pass@10.1.2.3:345/some-vhost');

Configuring an Exchange
-----------------------

The default exchange used by the library is of type ``direct``. You can also
create your own exchange::

    // define a new fanout exchange
    $broker->createExchange('sensiolabs.fanout', array('type' => \AMQP_EX_TYPE_FANOUT));

You can then binding a queue to this named exchange easily::

    $broker->createQueue('logs', array('exchange' => 'sensiolabs.fanout', 'routing_keys' => null));
    $broker->createQueue('logs.again', array('exchange' => 'sensiolabs.fanout', 'routing_keys' => null));

The second argument of ``createExchange()`` takes an array of arguments passed
to the exchange. The following keys are used to further configure the exchange:

* ``flags``: sets the exchange flags;

* ``type``: sets the type of the queue (see ``\AMQP_EX_TYPE_*`` constants).

.. note::

    Note that ``createExchange()`` automatically declares the exchange.

Configuring a Queue
-------------------

As demonstrated in some examples, you can create your own queue. As for the
exchange, the second argument of the ``createQueue()`` method is a list of
queue arguments; the following keys are used to further configure the queue:

* ``exchange``: The exchange name to bind the queue to (the default exchange is
  used if not set);

* ``flags``: Sets the exchange flags;

* ``bind_arguments``: An array of arguments to pass when binding the queue with
  an exchange;

* ``retry_strategy``: The retry strategy to use (an instance of
  :class:``Symfony\\Amqp\\RetryStrategy\\RetryStrategyInterface``).

.. note::

    Note that ``createQueue()`` automatically declares and binds the queue.

Implementation details
----------------------

The retry strategy
..................

The retry strategy is implemented with two custom and private exchanges:
``symfony.dead_letter`` and ``symfony.retry``.

Calling ``Broker::retry`` will publish the same message in the
``symfony.dead_letter`` exchange.

This exchange will route the message to a queue named like
``%exchange%.%time%.wait``, for example ``sensiolabs.default.000005.wait``. This
queue has a TTL of 5 seconds. It means that if nothing consumes this message, it
will be dropped after 5 seconds. But this queue has also a Dead Letter (DL). It
means that instead of dropping the message, the AMQP server will re-publish
automatically the message to the Exchange configured as DL.

After 5 seconds the message will be re-published to ``symfony.retry`` Exchange.
This exchange is bound with every single queue. Finally, the message will land
in the original queue.

</details>

### Worker

The second component was extracted from our internal SensioLabsAmqp component.
We extracted it as is decoupled from the AMQP component. Thus it could be used,
for example, to write redis, kafka daemon.

<details>
<summary>Documentation</summary>

Symfony Worker
==============

The worker component help you to write simple but flexible daemon.

Introduction
------------

First you need something that ``fetch`` some messages. If the message are sent
to AMQP, you should use the ``AmqpMessageFetcher``::

    use Symfony\Component\Amqp\Broker;
    use Symfony\Component\Worker\MessageFetcher\AmqpMessageFetcher;

    $broker = new Broker();
    $fetcher = new AmqpMessageFetcher($broker, 'queue_name');

Then you need a Consumer that will ``consumer`` each AMQP message::

    namespace AppBundle\Consumer;

    use Symfony\Component\Amqp\Broker;
    use Symfony\Component\Worker\Consumer\ConsumerInterface;
    use Symfony\Component\Worker\MessageCollection;

    class DumpConsumer implements ConsumerInterface
    {
        private $broker;

        public function __construct(Broker $broker)
        {
            $this->broker = $broker;
        }

        public function consume(MessageCollection $messageCollection)
        {
            foreach ($messageCollection as $message) {
                dump($message);

                $this->broker->ack($message);
            }
        }
    }

Finally plug everything together::

    use AppBundle\Consumer\DumpConsumer;
    use Symfony\Component\Amqp\Broker;
    use Symfony\Component\Worker\Loop\Loop;
    use Symfony\Component\Worker\MessageFetcher\AmqpMessageFetcher;

    $broker = new Broker();
    $fetcher = new AmqpMessageFetcher($broker, 'queue_name');
    $consumer = new DumpConsumer($broker);

    $loop = new Loop(new DirectRouter($fetcher, $consumer));

    $loop->run();

Message Fetcher
---------------

* ``AmqpMessageFetcher``: Proxy to interact with an AMQP server
* ``BufferedMessageFetcher``: Wrapper to buffer some message. Useful if you want to call an API in a "bulk" way.
* ``InMemoryMessageFetcher``: Useful in test env

Router
------

The router has the responsibility to fetch a message, then to dispatch it to a
consumer.

* ``DirectRouter``: Use a ``MessageFetcherInterface`` and a ``ConsumerInterface``. Each message fetched is passed to the consumer.
* ``RoundRobinRouter``: Wrapper to be able to fetch message from various sources.

</details>

---

In Symfony full stack, everything is simpler.

I have forked [the standard edition](https://github.com/lyrixx/symfony-standard/tree/amqp)
to show how it works.
