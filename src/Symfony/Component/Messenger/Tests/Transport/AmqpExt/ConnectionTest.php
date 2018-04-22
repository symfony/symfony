<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\AmqpExt;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Transport\AmqpExt\AmqpFactory;
use Symfony\Component\Messenger\Transport\AmqpExt\Connection;

/**
 * @requires extension amqp
 */
class ConnectionTest extends TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The given AMQP DSN "amqp://" is invalid.
     */
    public function testItCannotBeConstructedWithAWrongDsn()
    {
        Connection::fromDsn('amqp://');
    }

    public function testItGetsParametersFromTheDsn()
    {
        $this->assertEquals(
            new Connection(array(
                'host' => 'localhost',
                'port' => 5672,
                'vhost' => '/',
            ), array(
                'name' => 'messages',
            ), array(
                'name' => 'messages',
            )),
            Connection::fromDsn('amqp://localhost/%2f/messages')
        );
    }

    public function testOverrideOptionsViaQueryParameters()
    {
        $this->assertEquals(
            new Connection(array(
                'host' => 'redis',
                'port' => 1234,
                'vhost' => '/',
                'login' => 'guest',
                'password' => 'password',
            ), array(
                'name' => 'exchangeName',
            ), array(
                'name' => 'queue',
            )),
            Connection::fromDsn('amqp://guest:password@redis:1234/%2f/queue?exchange[name]=exchangeName')
        );
    }

    public function testOptionsAreTakenIntoAccountAndOverwrittenByDsn()
    {
        $this->assertEquals(
            new Connection(array(
                'host' => 'redis',
                'port' => 1234,
                'vhost' => '/',
                'login' => 'guest',
                'password' => 'password',
                'persistent' => 'true',
            ), array(
                'name' => 'exchangeName',
            ), array(
                'name' => 'queueName',
            )),
            Connection::fromDsn('amqp://guest:password@redis:1234/%2f/queue?exchange[name]=exchangeName&queue[name]=queueName', array(
                'persistent' => 'true',
                'exchange' => array('name' => 'toBeOverwritten'),
            ))
        );
    }

    public function testSetsParametersOnTheQueueAndExchange()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = $this->getMockBuilder(\AMQPConnection::class)->disableOriginalConstructor()->getMock(),
            $amqpChannel = $this->getMockBuilder(\AMQPChannel::class)->disableOriginalConstructor()->getMock(),
            $amqpQueue = $this->getMockBuilder(\AMQPQueue::class)->disableOriginalConstructor()->getMock(),
            $amqpExchange = $this->getMockBuilder(\AMQPExchange::class)->disableOriginalConstructor()->getMock()
        );

        $amqpQueue->expects($this->once())->method('setArguments')->with(array(
            'x-dead-letter-exchange' => 'dead-exchange',
            'x-message-ttl' => '1200',
        ));

        $amqpExchange->expects($this->once())->method('setArguments')->with(array(
            'alternate-exchange' => 'alternate',
        ));

        $connection = Connection::fromDsn('amqp://localhost/%2f/messages?queue[arguments][x-dead-letter-exchange]=dead-exchange', array(
            'queue' => array(
                'arguments' => array(
                    'x-message-ttl' => '1200',
                ),
            ),
            'exchange' => array(
                'arguments' => array(
                    'alternate-exchange' => 'alternate',
                ),
            ),
        ), true, $factory);
        $connection->publish('body');
    }

    public function testItUsesANormalConnectionByDefault()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = $this->getMockBuilder(\AMQPConnection::class)->disableOriginalConstructor()->getMock(),
            $amqpChannel = $this->getMockBuilder(\AMQPChannel::class)->disableOriginalConstructor()->getMock(),
            $amqpQueue = $this->getMockBuilder(\AMQPQueue::class)->disableOriginalConstructor()->getMock(),
            $amqpExchange = $this->getMockBuilder(\AMQPExchange::class)->disableOriginalConstructor()->getMock()
        );

        $amqpConnection->expects($this->once())->method('connect');

        $connection = Connection::fromDsn('amqp://localhost/%2f/messages', array(), false, $factory);
        $connection->publish('body');
    }

    public function testItAllowsToUseAPersistentConnection()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = $this->getMockBuilder(\AMQPConnection::class)->disableOriginalConstructor()->getMock(),
            $amqpChannel = $this->getMockBuilder(\AMQPChannel::class)->disableOriginalConstructor()->getMock(),
            $amqpQueue = $this->getMockBuilder(\AMQPQueue::class)->disableOriginalConstructor()->getMock(),
            $amqpExchange = $this->getMockBuilder(\AMQPExchange::class)->disableOriginalConstructor()->getMock()
        );

        $amqpConnection->expects($this->once())->method('pconnect');

        $connection = Connection::fromDsn('amqp://localhost/%2f/messages?persistent=true', array(), false, $factory);
        $connection->publish('body');
    }

    public function testItSetupsTheConnectionWhenDebug()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = $this->getMockBuilder(\AMQPConnection::class)->disableOriginalConstructor()->getMock(),
            $amqpChannel = $this->getMockBuilder(\AMQPChannel::class)->disableOriginalConstructor()->getMock(),
            $amqpQueue = $this->getMockBuilder(\AMQPQueue::class)->disableOriginalConstructor()->getMock(),
            $amqpExchange = $this->getMockBuilder(\AMQPExchange::class)->disableOriginalConstructor()->getMock()
        );

        $amqpExchange->method('getName')->willReturn('exchange_name');
        $amqpExchange->expects($this->once())->method('declareExchange');
        $amqpQueue->expects($this->once())->method('declareQueue');
        $amqpQueue->expects($this->once())->method('bind')->with('exchange_name', 'my_key');

        $connection = Connection::fromDsn('amqp://localhost/%2f/messages?queue[routing_key]=my_key', array(), true, $factory);
        $connection->publish('body');
    }

    public function testItCanDisableTheSetup()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = $this->getMockBuilder(\AMQPConnection::class)->disableOriginalConstructor()->getMock(),
            $amqpChannel = $this->getMockBuilder(\AMQPChannel::class)->disableOriginalConstructor()->getMock(),
            $amqpQueue = $this->getMockBuilder(\AMQPQueue::class)->disableOriginalConstructor()->getMock(),
            $amqpExchange = $this->getMockBuilder(\AMQPExchange::class)->disableOriginalConstructor()->getMock()
        );

        $amqpExchange->method('getName')->willReturn('exchange_name');
        $amqpExchange->expects($this->never())->method('declareExchange');
        $amqpQueue->expects($this->never())->method('declareQueue');
        $amqpQueue->expects($this->never())->method('bind');

        $connection = Connection::fromDsn('amqp://localhost/%2f/messages?queue[routing_key]=my_key', array('auto-setup' => 'false'), true, $factory);
        $connection->publish('body');

        $connection = Connection::fromDsn('amqp://localhost/%2f/messages?queue[routing_key]=my_key', array('auto-setup' => false), true, $factory);
        $connection->publish('body');

        $connection = Connection::fromDsn('amqp://localhost/%2f/messages?queue[routing_key]=my_key&auto-setup=false', array(), true, $factory);
        $connection->publish('body');
    }

    public function testItRetriesTheMessage()
    {
        $amqpConnection = $this->getMockBuilder(\AMQPConnection::class)->disableOriginalConstructor()->getMock();
        $amqpChannel = $this->getMockBuilder(\AMQPChannel::class)->disableOriginalConstructor()->getMock();
        $retryQueue = $this->getMockBuilder(\AMQPQueue::class)->disableOriginalConstructor()->getMock();

        $factory = $this->getMockBuilder(AmqpFactory::class)->getMock();
        $factory->method('createConnection')->willReturn($amqpConnection);
        $factory->method('createChannel')->willReturn($amqpChannel);
        $factory->method('createQueue')->willReturn($retryQueue);
        $factory->method('createExchange')->will($this->onConsecutiveCalls(
            $retryExchange = $this->getMockBuilder(\AMQPExchange::class)->disableOriginalConstructor()->getMock(),
            $amqpExchange = $this->getMockBuilder(\AMQPExchange::class)->disableOriginalConstructor()->getMock()
        ));

        $amqpExchange->expects($this->once())->method('setName')->with('messages');
        $amqpExchange->method('getName')->willReturn('messages');

        $retryExchange->expects($this->once())->method('setName')->with('retry');
        $retryExchange->expects($this->once())->method('declareExchange');
        $retryExchange->method('getName')->willReturn('retry');

        $retryQueue->expects($this->once())->method('setName')->with('retry_queue_1');
        $retryQueue->expects($this->once())->method('setArguments')->with(array(
            'x-message-ttl' => 10000,
            'x-dead-letter-exchange' => 'messages',
        ));

        $retryQueue->expects($this->once())->method('declareQueue');
        $retryQueue->expects($this->once())->method('bind')->with('retry', 'attempt_1');

        $envelope = $this->getMockBuilder(\AMQPEnvelope::class)->getMock();
        $envelope->method('getHeader')->with('symfony-messenger-attempts')->willReturn(false);
        $envelope->method('getHeaders')->willReturn(array('x-some-headers' => 'foo'));
        $envelope->method('getBody')->willReturn('{}');

        $retryExchange->expects($this->once())->method('publish')->with('{}', 'attempt_1', AMQP_NOPARAM, array('headers' => array('x-some-headers' => 'foo', 'symfony-messenger-attempts' => 1)));

        $connection = Connection::fromDsn('amqp://localhost/%2f/messages', array('retry' => array('attempts' => 3)), false, $factory);
        $connection->publishForRetry($envelope);
    }

    public function testItRetriesTheMessageWithADifferentRoutingKeyAndTTLs()
    {
        $amqpConnection = $this->getMockBuilder(\AMQPConnection::class)->disableOriginalConstructor()->getMock();
        $amqpChannel = $this->getMockBuilder(\AMQPChannel::class)->disableOriginalConstructor()->getMock();
        $retryQueue = $this->getMockBuilder(\AMQPQueue::class)->disableOriginalConstructor()->getMock();

        $factory = $this->getMockBuilder(AmqpFactory::class)->getMock();
        $factory->method('createConnection')->willReturn($amqpConnection);
        $factory->method('createChannel')->willReturn($amqpChannel);
        $factory->method('createQueue')->willReturn($retryQueue);
        $factory->method('createExchange')->will($this->onConsecutiveCalls(
            $retryExchange = $this->getMockBuilder(\AMQPExchange::class)->disableOriginalConstructor()->getMock(),
            $amqpExchange = $this->getMockBuilder(\AMQPExchange::class)->disableOriginalConstructor()->getMock()
        ));

        $amqpExchange->expects($this->once())->method('setName')->with('messages');
        $amqpExchange->method('getName')->willReturn('messages');

        $retryExchange->expects($this->once())->method('setName')->with('retry');
        $retryExchange->expects($this->once())->method('declareExchange');
        $retryExchange->method('getName')->willReturn('retry');

        $connectionOptions = array(
            'retry' => array(
                'attempts' => 3,
                'dead_routing_key' => 'my_dead_routing_key',
                'ttl' => array(30000, 60000, 120000),
            ),
        );

        $connection = Connection::fromDsn('amqp://localhost/%2f/messages', $connectionOptions, false, $factory);

        $messageRetriedTwice = $this->getMockBuilder(\AMQPEnvelope::class)->getMock();
        $messageRetriedTwice->method('getHeader')->with('symfony-messenger-attempts')->willReturn('2');
        $messageRetriedTwice->method('getHeaders')->willReturn(array('symfony-messenger-attempts' => '2'));
        $messageRetriedTwice->method('getBody')->willReturn('{}');

        $retryQueue->expects($this->once())->method('setName')->with('retry_queue_3');
        $retryQueue->expects($this->once())->method('setArguments')->with(array(
            'x-message-ttl' => 120000,
            'x-dead-letter-exchange' => 'messages',
        ));

        $retryQueue->expects($this->once())->method('declareQueue');
        $retryQueue->expects($this->once())->method('bind')->with('retry', 'attempt_3');

        $retryExchange->expects($this->once())->method('publish')->with('{}', 'attempt_3', AMQP_NOPARAM, array('headers' => array('symfony-messenger-attempts' => 3)));
        $connection->publishForRetry($messageRetriedTwice);
    }
}

class TestAmqpFactory extends AmqpFactory
{
    private $connection;
    private $channel;
    private $queue;
    private $exchange;

    public function __construct(\AMQPConnection $connection, \AMQPChannel $channel, \AMQPQueue $queue, \AMQPExchange $exchange)
    {
        $this->connection = $connection;
        $this->channel = $channel;
        $this->queue = $queue;
        $this->exchange = $exchange;
    }

    public function createConnection(array $credentials): \AMQPConnection
    {
        return $this->connection;
    }

    public function createChannel(\AMQPConnection $connection): \AMQPChannel
    {
        return $this->channel;
    }

    public function createQueue(\AMQPChannel $channel): \AMQPQueue
    {
        return $this->queue;
    }

    public function createExchange(\AMQPChannel $channel): \AMQPExchange
    {
        return $this->exchange;
    }
}
