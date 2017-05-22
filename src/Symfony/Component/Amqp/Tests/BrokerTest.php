<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Amqp\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Amqp\Broker;
use Symfony\Component\Amqp\Exception\InvalidArgumentException;
use Symfony\Component\Amqp\Exception\NonRetryableException;
use Symfony\Component\Amqp\Exchange;
use Symfony\Component\Amqp\Queue;
use Symfony\Component\Amqp\RetryStrategy\ConstantRetryStrategy;
use Symfony\Component\Amqp\RetryStrategy\ExponentialRetryStrategy;
use Symfony\Component\Amqp\Test\AmqpTestTrait;

class BrokerTest extends TestCase
{
    use AmqpTestTrait;

    /**
     * @expectedException \Symfony\Component\Amqp\Exception\InvalidArgumentException
     * @expectedExceptionMessage The connection should be a DSN or an instance of AMQPConnection.
     */
    public function testConstructorWithInvalidConnection()
    {
        new Broker(new \stdClass());
    }

    public function testConstructorUri()
    {
        $broker = new Broker('amqp://foo:bar@rabbitmq-3.lxc:1234/symfony_amqp');

        $connection = $broker->getConnection();

        $expected = array('rabbitmq-3.lxc', 'foo', 'bar', 1234, 'symfony_amqp');

        $this->assertEquals($expected, array(
            $connection->getHost(),
            $connection->getLogin(),
            $connection->getPassword(),
            $connection->getPort(),
            $connection->getVhost(),
        ));
    }

    public function testConstructorWithConnectionInstance()
    {
        $conn = $this->createConnection();

        $broker = new Broker($conn);

        $this->assertSame($conn, $broker->getConnection());
        $this->assertTrue($broker->isConnected());

        $channel = $broker->getChannel();

        $this->assertInstanceOf(\AMQPChannel::class, $channel);
        $this->assertTrue($channel->isConnected());
    }

    /**
     * @dataProvider provideInvalidConfiguration
     */
    public function testConstructorWithInvalidConfiguration($expectedMessage, $queuesConfiguration, $exchangesConfiguration)
    {
        try {
            new Broker('amqp://guest:guest@localhost:5672/', $queuesConfiguration, $exchangesConfiguration);

            $this->fail('The configuration should not be valid');
        } catch (\Exception $e) {
            $this->assertInstanceOf(InvalidArgumentException::class, $e);
            $this->assertSame($expectedMessage, $e->getMessage());
        }
    }

    public function provideInvalidConfiguration()
    {
        yield 'missing queue name' => array(
            'The key "name" is required to configure a Queue.',
            array(
                array(
                    'arguments' => array(),
                ),
            ),
            array(),
        );

        yield '2 queues with the same name' => array(
            'A queue named "non unique name" already exists.',
            array(
                array(
                    'name' => 'non unique name',
                ),
                array(
                    'name' => 'non unique name',
                ),
            ),
            array(),
        );

        yield 'missing exchange name' => array(
            'The key "name" is required to configure an Exchange.',
            array(),
            array(
                array(
                    'arguments' => array(),
                ),
            ),
        );

        yield '2 exchanges with the same name' => array(
            'An exchange named "non unique name" already exists.',
            array(),
            array(
                array(
                    'name' => 'non unique name',
                ),
                array(
                    'name' => 'non unique name',
                ),
            ),
        );
    }

    public function testConnection()
    {
        $broker = $this->createBroker();

        $this->assertFalse($broker->isConnected());

        $broker->connect();

        $this->assertTrue($broker->isConnected());

        $broker->disconnect();

        $this->assertFalse($broker->isConnected());
    }

    public function testConfigure()
    {
        $config = array(
            array(
                'arguments' => array(),
                'retry_strategy' => 'constant',
                'retry_strategy_options' => array('time' => 1, 'max' => 2),
                'thresholds' => array('warning' => 10, 'critical' => 20),
                'name' => 'test_broker.configure.constant',
            ),
            array(
                'arguments' => array(),
                'retry_strategy' => 'exponential',
                'retry_strategy_options' => array('max' => 1, 'offset' => 2),
                'thresholds' => array('warning' => 10, 'critical' => 20),
                'name' => 'test_broker.configure.exponential',
            ),
        );
        $broker = $this->createBroker($config);

        $this->assertSame($config, array_values($broker->getQueuesConfiguration()));

        $queueA = $broker->getQueue('test_broker.configure.constant');

        // Creating a queue lazy-instantiate connection
        $this->assertTrue($broker->isConnected());

        $this->assertInstanceOf(Queue::class, $queueA);
        $this->assertSame('test_broker.configure.constant', $queueA->getName());
        $this->assertInstanceOf(ConstantRetryStrategy::class, $queueA->getRetryStrategy());
        $this->assertTrue($broker->hasRetryStrategy('test_broker.configure.constant'));

        $queueB = $broker->getQueue('test_broker.configure.exponential');

        $this->assertInstanceOf(Queue::class, $queueB);
        $this->assertSame('test_broker.configure.exponential', $queueB->getName());
        $this->assertInstanceOf(ExponentialRetryStrategy::class, $queueB->getRetryStrategy());
        $this->assertTrue($broker->hasRetryStrategy('test_broker.configure.exponential'));
    }

    public function testCreateExchange()
    {
        $broker = $this->createBroker();
        $exchange = $broker->createExchange('test_broker.create_exchange');

        $this->assertInstanceOf(Exchange::class, $exchange);
        $this->assertSame('test_broker.create_exchange', $exchange->getName());
    }

    public function testGetExchangeFromConfiguration()
    {
        $broker = $this->createBroker(array(), array(
            array(
                'name' => 'test_broker.get_exchange_from_configuration.exchange_1',
                'arguments' => array(
                    'type' => 'fanout',
                ),
            ),
            array(
                'name' => 'test_broker.get_exchange_from_configuration.exchange_2',
                'arguments' => array(
                    'type' => 'fanout',
                ),
            ),
        ));

        $exchange = $broker->getExchange('test_broker.get_exchange_from_configuration.exchange_1');

        $this->assertInstanceOf(Exchange::class, $exchange);
        $this->assertSame('test_broker.get_exchange_from_configuration.exchange_1', $exchange->getName());
        $this->assertSame('fanout', $exchange->getType());

        $broker->createQueue('test_broker.get_exchange_from_configuration.queue', array(
            'exchange' => 'test_broker.get_exchange_from_configuration.exchange_2',
        ));

        $exchange = $broker->getExchange('test_broker.get_exchange_from_configuration.exchange_2');

        $this->assertInstanceOf(Exchange::class, $exchange);
        $this->assertSame('test_broker.get_exchange_from_configuration.exchange_2', $exchange->getName());
        $this->assertSame('fanout', $exchange->getType());
    }

    public function testGetSetExchange()
    {
        $name = 'test_broker.get_set_exchange';
        $exchange = $this->createExchange($name);
        $broker = $this->createBroker();

        $broker->addExchange($exchange);

        $newExchange = $broker->getExchange($name);

        $this->assertSame($newExchange, $exchange);
    }

    /**
     * @expectedException \Symfony\Component\Amqp\Exception\InvalidArgumentException
     * @expectedExceptionMessage Exchange "404" does not exist.
     */
    public function testGetExchangeWithUnknownExchange()
    {
        $this->createBroker()->getExchange('404');
    }

    public function testCreateQueue()
    {
        $broker = $this->createBroker();
        $queue = $broker->createQueue('test_broker.create_queue');

        $this->assertInstanceOf(Queue::class, $queue);
        $this->assertSame('test_broker.create_queue', $queue->getName());
    }

    public function testGetQueueFromConfiguration()
    {
        $broker = $this->createBroker(array(
            array(
                'name' => 'test_broker.get_queue_from_configuration',
                'arguments' => array(
                    'flags' => \AMQP_AUTODELETE,
                ),
            ),
            array(
                'name' => 'test_broker.get_queue_from_configuration_2',
                'arguments' => array(
                    'flags' => \AMQP_AUTODELETE,
                ),
            ),
        ));

        $queue = $broker->getQueue('test_broker.get_queue_from_configuration');

        $this->assertInstanceOf(Queue::class, $queue);
        $this->assertSame('test_broker.get_queue_from_configuration', $queue->getName());
        $this->assertSame(\AMQP_AUTODELETE, $queue->getFlags());

        $broker->get('test_broker.get_queue_from_configuration_2');
        $queue = $broker->getQueue('test_broker.get_queue_from_configuration_2');

        $this->assertInstanceOf(Queue::class, $queue);
        $this->assertSame('test_broker.get_queue_from_configuration_2', $queue->getName());
        $this->assertSame(\AMQP_AUTODELETE, $queue->getFlags());
    }

    public function testGetSetQueue()
    {
        $name = 'test_broker.get_set_queue';
        $queue = new Queue($this->createChannel(), $name);
        $broker = $this->createBroker();

        $broker->addQueue($queue);

        $newQueue = $broker->getQueue($name);

        $this->assertSame($newQueue, $queue);
    }

    /**
     * @expectedException \Symfony\Component\Amqp\Exception\InvalidArgumentException
     * @expectedExceptionMessage Queue "404" does not exist.
     */
    public function testGetQueueWithUnknownQueue()
    {
        $this->createBroker()->getQueue('404');
    }

    public function testHasRetryStrategy()
    {
        $broker = $this->createBroker();

        $broker->createQueue('test_broker.has_retry_strategy_no');

        $this->assertFalse($broker->hasRetryStrategy('test_broker.has_retry_strategy_no'));

        $broker->createQueue('test_broker.has_retry_strategy_yes', array('retry_strategy' => new ConstantRetryStrategy(2)));

        $this->assertTrue($broker->hasRetryStrategy('test_broker.has_retry_strategy_yes'));
    }

    public function testPublishCreateEverything()
    {
        $this->emptyQueue('test_broker.publish_default');

        $broker = $this->createBroker();
        $broker->publish('test_broker.publish_default', 'payload-1');

        $exchange = $broker->getExchange('symfony.default');

        $this->assertInstanceOf(Exchange::class, $exchange);
        $this->assertSame('symfony.default', $exchange->getName());

        $queue = $broker->getQueue('test_broker.publish_default');

        $this->assertInstanceOf(Queue::class, $queue);
        $this->assertSame('test_broker.publish_default', $queue->getName());

        $this->assertNextMessageBody('payload-1', 'test_broker.publish_default');
    }

    public function testPublishInCustomExchange()
    {
        $this->emptyQueue('test_broker.publish_custom_exchange');

        $broker = $this->createBroker();
        $broker->publish('test_broker.publish_custom_exchange', 'payload-2', array(
            'exchange' => 'test_broker.custom_exchange',
        ));

        $exchange = $broker->getExchange('test_broker.custom_exchange');
        $this->assertInstanceOf(Exchange::class, $exchange);
        $this->assertSame('test_broker.custom_exchange', $exchange->getName());

        $queue = $broker->getQueue('test_broker.publish_custom_exchange');
        $this->assertInstanceOf(Queue::class, $queue);
        $this->assertSame('test_broker.publish_custom_exchange', $queue->getName());

        $this->assertNextMessageBody('payload-2', 'test_broker.publish_custom_exchange');
    }

    public function testPublishWithSpecialExchangeAndFlags()
    {
        $broker = $this->createBroker();
        $channel = $broker->getChannel();

        $exchange = $this->getMockBuilder(Exchange::class)
            ->setConstructorArgs(array($channel, 'test_broker.publish_flags'))
            ->enableProxyingToOriginalMethods()
            ->getMock()
        ;
        $exchange
            ->expects($this->exactly(3))
            ->method('getName')
            ->will($this->returnValue('test_broker.publish_flags'))
        ;
        $exchange
            ->expects($this->once())
            ->method('publish')
            ->with(
                'payload',
                'test_broker.publish_flags',
                \AMQP_MANDATORY,
                array('delivery_mode' => 1, 'message_id' => 1234)
            )
        ;

        $broker->addExchange($exchange);

        $broker->publish('test_broker.publish_flags', 'payload', array(
            'delivery_mode' => 1,
            'flags' => \AMQP_MANDATORY,
            'exchange' => 'test_broker.publish_flags',
            'message_id' => 1234,
        ));
    }

    public function testPublishWithCustomBinding()
    {
        $broker = $this->createBroker();
        $broker->createQueue('test_broker.extra.queue_1', array(
            'routing_keys' => 'test_broker.extra.queue',
        ));
        $broker->createQueue('test_broker.extra.queue_2', array(
            'routing_keys' => 'test_broker.extra.queue',
        ));
        $this->emptyQueue('test_broker.extra.queue_1');
        $this->emptyQueue('test_broker.extra.queue_2');

        $this->assertQueueSize(0, 'test_broker.extra.queue_1');
        $this->assertQueueSize(0, 'test_broker.extra.queue_2');

        $broker->publish('test_broker.extra.queue', 'payload-42');

        // Ensure we don't create extra queue
        try {
            $broker->getQueue('test_broker.extra.queue');

            $this->fail('The queues exists!');
        } catch (\Exception $e) {
            $this->assertSame('Queue "test_broker.extra.queue" does not exist.', $e->getMessage());
        }

        $this->assertQueueSize(1, 'test_broker.extra.queue_1');
        $this->assertNextMessageBody('payload-42', 'test_broker.extra.queue_1');

        $this->assertQueueSize(1, 'test_broker.extra.queue_2');
        $this->assertNextMessageBody('payload-42', 'test_broker.extra.queue_2');
    }

    public function testPublishWithCustomBindingInConfig()
    {
        $config = array(
            array(
                'name' => 'test_broker.extra2.queue_1',
                'retry_strategy' => array(),
                'arguments' => array(
                    'routing_keys' => 'test_broker.extra2.queue',
                ),
            ),
            array(
                'name' => 'test_broker.extra2.queue_2',
                'retry_strategy' => array(),
                'arguments' => array(
                    'routing_keys' => 'test_broker.extra2.queue',
                ),
            ),
        );

        $broker = $this->createBroker($config);

        $this->emptyQueue('test_broker.extra2.queue_1');
        $this->emptyQueue('test_broker.extra2.queue_2');

        $this->assertQueueSize(0, 'test_broker.extra2.queue_1');
        $this->assertQueueSize(0, 'test_broker.extra2.queue_2');

        $broker->publish('test_broker.extra2.queue', 'payload-42');

        // Ensure we don't create extra queue
        try {
            $broker->getQueue('test_broker.extra2.queue');

            $this->fail('The queues exists!');
        } catch (\Exception $e) {
            $this->assertSame('Queue "test_broker.extra2.queue" does not exist.', $e->getMessage());
        }

        $this->assertQueueSize(1, 'test_broker.extra2.queue_1');
        $this->assertNextMessageBody('payload-42', 'test_broker.extra2.queue_1');

        $this->assertQueueSize(1, 'test_broker.extra2.queue_2');
        $this->assertNextMessageBody('payload-42', 'test_broker.extra2.queue_2');
    }

    public function provideExchangeTests()
    {
        yield 'with default exchange' => array(Broker::DEFAULT_EXCHANGE);
        yield 'with a custom exchange' => array('foobar');
    }

    /**
     * @dataProvider provideExchangeTests
     */
    public function testDelay($exchange)
    {
        $broker = $this->createBroker();

        $broker->createQueue('test_broker.delay.step_1', array(
            'routing_keys' => 'test_broker.delay',
            'exchange' => $exchange,
        ));
        $broker->createQueue('test_broker.delay.step_2', array(
            'retry_strategy' => new ConstantRetryStrategy(1, 2),
            'routing_keys' => 'test_broker.delay',
            'exchange' => $exchange,
        ));

        $this->emptyQueue('test_broker.delay.step_1');
        $this->emptyQueue('test_broker.delay.step_2');

        $broker->delay('test_broker.delay', 'my_message', 1, array('exchange' => $exchange));

        sleep(1);

        $this->assertQueueSize(1, 'test_broker.delay.step_1');
        $this->assertQueueSize(1, 'test_broker.delay.step_2');
    }

    public function testGet()
    {
        $broker = $this->createBroker();
        $broker->createQueue('test_broker.get');

        $this->emptyQueue('test_broker.get');

        $broker->publish('test_broker.get', 'payload-42');
        usleep(100);

        $msg = $broker->get('test_broker.get');
        $this->assertSame('payload-42', $msg->getBody());
    }

    public function testConsume()
    {
        $broker = $this->createBroker();
        $broker->createQueue('test_broker.consume');

        $this->emptyQueue('test_broker.consume');

        $broker->publish('test_broker.consume', 'payload-42');
        usleep(100);

        $consumed = false;
        $broker->consume('test_broker.consume', function (\AMQPEnvelope $msg) use (&$consumed) {
            $consumed = true;
            $this->assertInstanceOf(\AMQPEnvelope::class, $msg);
            $this->assertSame('payload-42', $msg->getBody());

            return false;
        }, \AMQP_AUTOACK);
        $this->assertTrue($consumed);
    }

    public function testAck()
    {
        $broker = $this->createBroker();

        $this->emptyQueue('test_broker.ack');

        $broker->publish('test_broker.ack', 'payload-42');
        usleep(100);

        $msg = $broker->get('test_broker.ack');

        $broker->ack($msg);
        $broker->disconnect();

        $this->assertQueueSize(0, 'test_broker.ack');
    }

    public function testNack()
    {
        $broker = $this->createBroker();

        $this->emptyQueue('test_broker.nack');

        $broker->publish('test_broker.nack', 'payload-42');
        usleep(100);

        $msg = $broker->get('test_broker.nack');

        $broker->nack($msg);
        $broker->disconnect();

        $this->assertQueueSize(0, 'test_broker.nack');
    }

    public function testNackAndRequeue()
    {
        $broker = $this->createBroker();

        $this->emptyQueue('test_broker.nack_and_requeue');

        $broker->publish('test_broker.nack_and_requeue', 'payload-42');
        usleep(100);

        $msg = $broker->get('test_broker.nack_and_requeue');

        $broker->nack($msg, \AMQP_REQUEUE);
        $broker->disconnect();

        $this->assertQueueSize(1, 'test_broker.nack_and_requeue');
        $this->assertNextMessageBody('payload-42', 'test_broker.nack_and_requeue');
    }

    /**
     * @expectedException \Symfony\Component\Amqp\Exception\LogicException
     * @expectedExceptionMessage The queue "test_broker.no_retry" has no retry strategy
     */
    public function testRetryWhenRSIsNotDefined()
    {
        $broker = $this->createBroker();

        $this->emptyQueue('test_broker.no_retry');

        $broker->publish('test_broker.no_retry', 'payload-42');
        usleep(100);

        $msg = $broker->get('test_broker.no_retry');
        $broker->ack($msg);
        $broker->retry($msg);
    }

    /**
     * @dataProvider provideExchangeTests
     */
    public function testRetry($exchange)
    {
        $broker = $this->createBroker();
        $broker->createQueue('test_broker.retry', array(
            'retry_strategy' => $rs = new ConstantRetryStrategy(1, 2),
            'exchange' => $exchange,
        ));

        $this->emptyQueue('test_broker.retry');

        $broker->publish('test_broker.retry', 'payload-42', array(
            'exchange' => $exchange,
        ));
        usleep(100);

        $msg = $this->assertNextMessageBody('payload-42', 'test_broker.retry');
        $this->assertFalse($msg->getHeader('retries'));

        $broker->retry($msg, null, 'a message');

        $this->assertQueueSize(0, 'test_broker.retry');
        usleep(1000100);

        $this->assertQueueSize(1, 'test_broker.retry');
        $msg = $this->assertNextMessageBody('payload-42', 'test_broker.retry');
        $this->assertSame('a message', $msg->getHeader('retry-message'));
        $this->assertSame(1, $msg->getHeader('retries'));

        $broker->retry($msg);

        $this->assertQueueSize(0, 'test_broker.retry');
        usleep(1000100);

        $this->assertQueueSize(1, 'test_broker.retry');
        $msg = $this->assertNextMessageBody('payload-42', 'test_broker.retry');
        $this->assertSame(2, $msg->getHeader('retries'));
        $this->assertSame('a message', $msg->getHeader('retry-message'));

        try {
            $broker->retry($msg);

            $this->fail('We should reach NonRetryable limit.');
        } catch (\Exception $e) {
            $this->assertInstanceOf(NonRetryableException::class, $e);
            $this->assertSame('The message has been retried too many times (2).', $e->getMessage());
            $this->assertSame($rs, $e->getRetryStrategy());
            $this->assertSame($msg, $e->getEnvelope());
        }
    }

    /**
     * @dataProvider provideExchangeTests
     */
    public function testRetryWithSpecialBinding($exchange)
    {
        $broker = $this->createBroker();
        $broker->createQueue('test_broker.retry_finished.step_1', array(
            'retry_strategy' => new ConstantRetryStrategy(1, 2),
            'routing_keys' => 'test_broker.retry_finished',
            'exchange' => $exchange,
        ));
        $broker->createQueue('test_broker.retry_finished.step_2', array(
            'retry_strategy' => new ConstantRetryStrategy(1, 2),
            'routing_keys' => 'test_broker.retry_finished',
            'exchange' => $exchange,
        ));

        $this->emptyQueue('test_broker.retry_finished.step_1');
        $this->emptyQueue('test_broker.retry_finished.step_2');

        $broker->publish('test_broker.retry_finished', 'payload-42', array(
            'exchange' => $exchange,
        ));
        usleep(100);

        $this->assertQueueSize(1, 'test_broker.retry_finished.step_1');
        $this->assertQueueSize(1, 'test_broker.retry_finished.step_2');

        $msg = $this->assertNextMessageBody('payload-42', 'test_broker.retry_finished.step_1');
        $this->assertFalse($msg->getHeader('retries'));

        $broker->retry($msg, 'test_broker.retry_finished.step_1');

        $this->assertQueueSize(0, 'test_broker.retry_finished.step_1');
        $this->assertQueueSize(1, 'test_broker.retry_finished.step_2');
        usleep(1000100);

        $this->assertQueueSize(1, 'test_broker.retry_finished.step_1');
        $msg = $this->assertNextMessageBody('payload-42', 'test_broker.retry_finished.step_1');

        $this->assertSame(1, $msg->getHeader('retries'));

        $broker->retry($msg, 'test_broker.retry_finished.step_1');

        $this->assertQueueSize(0, 'test_broker.retry_finished.step_1');
        $this->assertQueueSize(1, 'test_broker.retry_finished.step_2');
        usleep(1000100);

        $this->assertQueueSize(1, 'test_broker.retry_finished.step_1');
        $msg = $this->assertNextMessageBody('payload-42', 'test_broker.retry_finished.step_1');
        $this->assertSame(2, $msg->getHeader('retries'));

        try {
            $broker->retry($msg, 'test_broker.retry_finished.step_1');

            $this->fail('We should reach NonRetryable limit.');
        } catch (\Exception $e) {
            $this->assertInstanceOf('Symfony\Component\Amqp\Exception\NonRetryableException', $e);
            $this->assertSame('The message has been retried too many times (2).', $e->getMessage());
            $this->assertSame($msg, $e->getEnvelope());
        }

        $this->assertQueueSize(0, 'test_broker.retry_finished.step_1');
        $this->assertQueueSize(1, 'test_broker.retry_finished.step_2');
    }

    public function testMove()
    {
        $broker = $this->createBroker();
        $this->emptyQueue('test_broker.move.from');

        $broker->publish('test_broker.move.from', 'payload-42', array(
            'app_id' => 'app',
            'headers' => array(
                'foo' => 'bar',
            ),
        ));
        $message = $broker->get('test_broker.move.from', \AMQP_AUTOACK);

        $broker->move($message, 'test_broker.move.to');

        $this->assertQueueSize(1, 'test_broker.move.to');

        $message = $broker->get('test_broker.move.to', \AMQP_AUTOACK);

        $this->assertSame('payload-42', $message->getBody());
        $this->assertSame('bar', $message->getHeader('foo'));
        $this->assertSame('app', $message->getAppId());
    }

    public function testMoveToDeadLetter()
    {
        $broker = $this->createBroker();
        $this->emptyQueue('test_broker.move_to_dl');

        $broker->publish('test_broker.move_to_dl', 'payload-42', array(
            'app_id' => 'app',
            'headers' => array(
                'foo' => 'bar',
            ),
        ));
        $message = $broker->get('test_broker.move_to_dl', \AMQP_AUTOACK);

        $broker->moveToDeadLetter($message);

        $this->assertQueueSize(1, 'test_broker.move_to_dl.dead');

        $message = $broker->get('test_broker.move_to_dl.dead', \AMQP_AUTOACK);

        $this->assertSame('payload-42', $message->getBody());
        $this->assertSame('bar', $message->getHeader('foo'));
        $this->assertSame('app', $message->getAppId());
    }

    private function createBroker(array $queuesConfiguration = array(), $exchangesConfiguration = array())
    {
        return new Broker(getenv('RABBITMQ_URL'), $queuesConfiguration, $exchangesConfiguration);
    }
}
