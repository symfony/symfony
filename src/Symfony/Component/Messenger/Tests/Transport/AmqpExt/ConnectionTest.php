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
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\AmqpExt\AmqpFactory;
use Symfony\Component\Messenger\Transport\AmqpExt\AmqpStamp;
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
            new Connection([
                'host' => 'localhost',
                'port' => 5672,
                'vhost' => '/',
            ], [
                'name' => 'messages',
            ], [
                'messages' => [],
            ]),
            Connection::fromDsn('amqp://localhost/%2f/messages')
        );
    }

    public function testOverrideOptionsViaQueryParameters()
    {
        $this->assertEquals(
            new Connection([
                'host' => 'redis',
                'port' => 1234,
                'vhost' => '/',
                'login' => 'guest',
                'password' => 'password',
            ], [
                'name' => 'exchangeName',
            ], [
                'queueName' => [],
            ]),
            Connection::fromDsn('amqp://guest:password@redis:1234/%2f/queue?exchange[name]=exchangeName&queues[queueName]')
        );
    }

    public function testOptionsAreTakenIntoAccountAndOverwrittenByDsn()
    {
        $this->assertEquals(
            new Connection([
                'host' => 'redis',
                'port' => 1234,
                'vhost' => '/',
                'login' => 'guest',
                'password' => 'password',
                'persistent' => 'true',
            ], [
                'name' => 'exchangeName',
            ], [
                'queueName' => [],
            ]),
            Connection::fromDsn('amqp://guest:password@redis:1234/%2f/queue?exchange[name]=exchangeName&queues[queueName]', [
                'persistent' => 'true',
                'exchange' => ['name' => 'toBeOverwritten'],
            ])
        );
    }

    public function testSetsParametersOnTheQueueAndExchange()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = $this->createMock(\AMQPConnection::class),
            $amqpChannel = $this->createMock(\AMQPChannel::class),
            $amqpQueue = $this->createMock(\AMQPQueue::class),
            $amqpExchange = $this->createMock(\AMQPExchange::class)
        );

        $amqpQueue->expects($this->once())->method('setArguments')->with([
            'x-dead-letter-exchange' => 'dead-exchange',
            'x-delay' => 100,
            'x-expires' => 150,
            'x-max-length' => 200,
            'x-max-length-bytes' => 300,
            'x-max-priority' => 4,
            'x-message-ttl' => 100,
        ]);

        $amqpExchange->expects($this->once())->method('setArguments')->with([
            'alternate-exchange' => 'alternate',
        ]);

        $dsn = 'amqp://localhost/%2f/messages?'.
            'queues[messages][arguments][x-dead-letter-exchange]=dead-exchange&'.
            'queues[messages][arguments][x-message-ttl]=100&'.
            'queues[messages][arguments][x-delay]=100&'.
            'queues[messages][arguments][x-expires]=150&'
        ;
        $connection = Connection::fromDsn($dsn, [
            'queues' => [
                'messages' => [
                    'arguments' => [
                        'x-max-length' => '200',
                        'x-max-length-bytes' => '300',
                        'x-max-priority' => '4',
                    ],
                ],
            ],
            'exchange' => [
                'arguments' => [
                    'alternate-exchange' => 'alternate',
                ],
            ],
        ], $factory);
        $connection->publish('body');
    }

    public function invalidQueueArgumentsDataProvider(): iterable
    {
        $baseDsn = 'amqp://localhost/%2f/messages';

        return [
            [$baseDsn.'?queues[messages][arguments][x-delay]=not-a-number', []],
            [$baseDsn.'?queues[messages][arguments][x-expires]=not-a-number', []],
            [$baseDsn.'?queues[messages][arguments][x-max-length]=not-a-number', []],
            [$baseDsn.'?queues[messages][arguments][x-max-length-bytes]=not-a-number', []],
            [$baseDsn.'?queues[messages][arguments][x-max-priority]=not-a-number', []],
            [$baseDsn.'?queues[messages][arguments][x-message-ttl]=not-a-number', []],

            // Ensure the exception is thrown when the arguments are passed via the array options
            [$baseDsn, ['queues' => ['messages' => ['arguments' => ['x-delay' => 'not-a-number']]]]],
            [$baseDsn, ['queues' => ['messages' => ['arguments' => ['x-expires' => 'not-a-number']]]]],
            [$baseDsn, ['queues' => ['messages' => ['arguments' => ['x-max-length' => 'not-a-number']]]]],
            [$baseDsn, ['queues' => ['messages' => ['arguments' => ['x-max-length-bytes' => 'not-a-number']]]]],
            [$baseDsn, ['queues' => ['messages' => ['arguments' => ['x-max-priority' => 'not-a-number']]]]],
            [$baseDsn, ['queues' => ['messages' => ['arguments' => ['x-message-ttl' => 'not-a-number']]]]],
        ];
    }

    /**
     * @dataProvider invalidQueueArgumentsDataProvider
     */
    public function testFromDsnWithInvalidValueOnQueueArguments(string $dsn, array $options)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Integer expected for queue argument');

        Connection::fromDsn($dsn, $options);
    }

    public function testItUsesANormalConnectionByDefault()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = $this->createMock(\AMQPConnection::class),
            $amqpChannel = $this->createMock(\AMQPChannel::class),
            $amqpQueue = $this->createMock(\AMQPQueue::class),
            $amqpExchange = $this->createMock(\AMQPExchange::class)
        );

        // makes sure the channel looks connected, so it's not re-created
        $amqpChannel->expects($this->once())->method('isConnected')->willReturn(true);
        $amqpConnection->expects($this->once())->method('connect');

        $connection = Connection::fromDsn('amqp://localhost/%2f/messages', [], $factory);
        $connection->publish('body');
    }

    public function testItAllowsToUseAPersistentConnection()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = $this->createMock(\AMQPConnection::class),
            $amqpChannel = $this->createMock(\AMQPChannel::class),
            $amqpQueue = $this->createMock(\AMQPQueue::class),
            $amqpExchange = $this->createMock(\AMQPExchange::class)
        );

        // makes sure the channel looks connected, so it's not re-created
        $amqpChannel->expects($this->once())->method('isConnected')->willReturn(true);
        $amqpConnection->expects($this->once())->method('pconnect');

        $connection = Connection::fromDsn('amqp://localhost/%2f/messages?persistent=true', [], $factory);
        $connection->publish('body');
    }

    public function testItSetupsTheConnectionWithDefaults()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = $this->createMock(\AMQPConnection::class),
            $amqpChannel = $this->createMock(\AMQPChannel::class),
            $amqpQueue = $this->createMock(\AMQPQueue::class),
            $amqpExchange = $this->createMock(\AMQPExchange::class)
        );

        $amqpExchange->method('getName')->willReturn('exchange_name');
        $amqpExchange->expects($this->once())->method('declareExchange');
        $amqpExchange->expects($this->once())->method('publish')->with('body', null, AMQP_NOPARAM, ['headers' => []]);
        $amqpQueue->expects($this->once())->method('declareQueue');
        $amqpQueue->expects($this->once())->method('bind')->with('exchange_name', null);

        $connection = Connection::fromDsn('amqp://localhost/%2f/messages', [], $factory);
        $connection->publish('body');
    }

    public function testItSetupsTheConnection()
    {
        $amqpConnection = $this->createMock(\AMQPConnection::class);
        $amqpChannel = $this->createMock(\AMQPChannel::class);
        $amqpExchange = $this->createMock(\AMQPExchange::class);
        $amqpQueue0 = $this->createMock(\AMQPQueue::class);
        $amqpQueue1 = $this->createMock(\AMQPQueue::class);

        $factory = $this->createMock(AmqpFactory::class);
        $factory->method('createConnection')->willReturn($amqpConnection);
        $factory->method('createChannel')->willReturn($amqpChannel);
        $factory->method('createExchange')->willReturn($amqpExchange);
        $factory->method('createQueue')->will($this->onConsecutiveCalls($amqpQueue0, $amqpQueue1));

        $amqpExchange->method('getName')->willReturn('exchange_name');
        $amqpExchange->expects($this->once())->method('declareExchange');
        $amqpExchange->expects($this->once())->method('publish')->with('body', 'routing_key', AMQP_NOPARAM, ['headers' => []]);
        $amqpQueue0->expects($this->once())->method('declareQueue');
        $amqpQueue0->expects($this->exactly(2))->method('bind')->withConsecutive(
            ['exchange_name', 'binding_key0'],
            ['exchange_name', 'binding_key1']
        );
        $amqpQueue1->expects($this->once())->method('declareQueue');
        $amqpQueue1->expects($this->exactly(2))->method('bind')->withConsecutive(
            ['exchange_name', 'binding_key2'],
            ['exchange_name', 'binding_key3']
        );

        $dsn = 'amqp://localhost/%2f/messages?'.
            'exchange[default_publish_routing_key]=routing_key&'.
            'queues[queue0][binding_keys][0]=binding_key0&'.
            'queues[queue0][binding_keys][1]=binding_key1&'.
            'queues[queue1][binding_keys][0]=binding_key2&'.
            'queues[queue1][binding_keys][1]=binding_key3';

        $connection = Connection::fromDsn($dsn, [], $factory);
        $connection->publish('body');
    }

    public function testItCanDisableTheSetup()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = $this->createMock(\AMQPConnection::class),
            $amqpChannel = $this->createMock(\AMQPChannel::class),
            $amqpQueue = $this->createMock(\AMQPQueue::class),
            $amqpExchange = $this->createMock(\AMQPExchange::class)
        );

        $amqpExchange->method('getName')->willReturn('exchange_name');
        $amqpExchange->expects($this->never())->method('declareExchange');
        $amqpQueue->expects($this->never())->method('declareQueue');
        $amqpQueue->expects($this->never())->method('bind');

        $connection = Connection::fromDsn('amqp://localhost/%2f/messages', ['auto_setup' => 'false'], $factory);
        $connection->publish('body');

        $connection = Connection::fromDsn('amqp://localhost/%2f/messages', ['auto_setup' => false], $factory);
        $connection->publish('body');

        $connection = Connection::fromDsn('amqp://localhost/%2f/messages?auto_setup=false', [], $factory);
        $connection->publish('body');
    }

    public function testSetChannelPrefetchWhenSetup()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = $this->createMock(\AMQPConnection::class),
            $amqpChannel = $this->createMock(\AMQPChannel::class),
            $amqpQueue = $this->createMock(\AMQPQueue::class),
            $amqpExchange = $this->createMock(\AMQPExchange::class)
        );

        // makes sure the channel looks connected, so it's not re-created
        $amqpChannel->expects($this->exactly(2))->method('isConnected')->willReturn(true);

        $amqpChannel->expects($this->exactly(2))->method('setPrefetchCount')->with(2);
        $connection = Connection::fromDsn('amqp://localhost/%2f/messages?prefetch_count=2', [], $factory);
        $connection->setup();
        $connection = Connection::fromDsn('amqp://localhost/%2f/messages', ['prefetch_count' => 2], $factory);
        $connection->setup();
    }

    public function testItDelaysTheMessage()
    {
        $amqpConnection = $this->createMock(\AMQPConnection::class);
        $amqpChannel = $this->createMock(\AMQPChannel::class);
        $delayQueue = $this->createMock(\AMQPQueue::class);

        $factory = $this->createMock(AmqpFactory::class);
        $factory->method('createConnection')->willReturn($amqpConnection);
        $factory->method('createChannel')->willReturn($amqpChannel);
        $factory->method('createQueue')->willReturn($delayQueue);
        $factory->method('createExchange')->will($this->onConsecutiveCalls(
            $delayExchange = $this->getMockBuilder(\AMQPExchange::class)->disableOriginalConstructor()->getMock(),
            $amqpExchange = $this->getMockBuilder(\AMQPExchange::class)->disableOriginalConstructor()->getMock()
        ));

        $amqpExchange->expects($this->once())->method('setName')->with('messages');
        $amqpExchange->method('getName')->willReturn('messages');

        $delayExchange->expects($this->once())->method('setName')->with('delay');
        $delayExchange->expects($this->once())->method('declareExchange');
        $delayExchange->method('getName')->willReturn('delay');

        $delayQueue->expects($this->once())->method('setName')->with('delay_queue_5000');
        $delayQueue->expects($this->once())->method('setArguments')->with([
            'x-message-ttl' => 5000,
            'x-dead-letter-exchange' => 'messages',
        ]);

        $delayQueue->expects($this->once())->method('declareQueue');
        $delayQueue->expects($this->once())->method('bind')->with('delay', 'delay_5000');

        $delayExchange->expects($this->once())->method('publish')->with('{}', 'delay_5000', AMQP_NOPARAM, ['headers' => ['x-some-headers' => 'foo']]);

        $connection = Connection::fromDsn('amqp://localhost/%2f/messages', [], $factory);
        $connection->publish('{}', ['x-some-headers' => 'foo'], 5000);
    }

    public function testItDelaysTheMessageWithADifferentRoutingKeyAndTTLs()
    {
        $amqpConnection = $this->createMock(\AMQPConnection::class);
        $amqpChannel = $this->createMock(\AMQPChannel::class);
        $delayQueue = $this->createMock(\AMQPQueue::class);

        $factory = $this->createMock(AmqpFactory::class);
        $factory->method('createConnection')->willReturn($amqpConnection);
        $factory->method('createChannel')->willReturn($amqpChannel);
        $factory->method('createQueue')->willReturn($delayQueue);
        $factory->method('createExchange')->will($this->onConsecutiveCalls(
            $delayExchange = $this->getMockBuilder(\AMQPExchange::class)->disableOriginalConstructor()->getMock(),
            $amqpExchange = $this->getMockBuilder(\AMQPExchange::class)->disableOriginalConstructor()->getMock()
        ));

        $amqpExchange->expects($this->once())->method('setName')->with('messages');
        $amqpExchange->method('getName')->willReturn('messages');

        $delayExchange->expects($this->once())->method('setName')->with('delay');
        $delayExchange->expects($this->once())->method('declareExchange');
        $delayExchange->method('getName')->willReturn('delay');

        $connectionOptions = [
            'retry' => [
                'dead_routing_key' => 'my_dead_routing_key',
            ],
        ];

        $connection = Connection::fromDsn('amqp://localhost/%2f/messages', $connectionOptions, $factory);

        $delayQueue->expects($this->once())->method('setName')->with('delay_queue_120000');
        $delayQueue->expects($this->once())->method('setArguments')->with([
            'x-message-ttl' => 120000,
            'x-dead-letter-exchange' => 'messages',
        ]);

        $delayQueue->expects($this->once())->method('declareQueue');
        $delayQueue->expects($this->once())->method('bind')->with('delay', 'delay_120000');

        $delayExchange->expects($this->once())->method('publish')->with('{}', 'delay_120000', AMQP_NOPARAM, ['headers' => []]);
        $connection->publish('{}', [], 120000);
    }

    /**
     * @expectedException \AMQPException
     * @expectedExceptionMessage Could not connect to the AMQP server. Please verify the provided DSN. ({"delay":{"routing_key_pattern":"delay_%delay%","exchange_name":"delay","queue_name_pattern":"delay_queue_%delay%"},"host":"localhost","port":5672,"vhost":"\/","login":"user","password":"********"})
     */
    public function testObfuscatePasswordInDsn()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = $this->createMock(\AMQPConnection::class),
            $amqpChannel = $this->createMock(\AMQPChannel::class),
            $amqpQueue = $this->createMock(\AMQPQueue::class),
            $amqpExchange = $this->createMock(\AMQPExchange::class)
        );

        $amqpConnection->method('connect')->willThrowException(
            new \AMQPConnectionException('Oups.')
        );

        $connection = Connection::fromDsn('amqp://user:secretpassword@localhost/%2f/messages', [], $factory);
        $connection->channel();
    }

    public function testItCanPublishWithTheDefaultRoutingKey()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = $this->createMock(\AMQPConnection::class),
            $amqpChannel = $this->createMock(\AMQPChannel::class),
            $amqpQueue = $this->createMock(\AMQPQueue::class),
            $amqpExchange = $this->createMock(\AMQPExchange::class)
        );

        $amqpExchange->expects($this->once())->method('publish')->with('body', 'routing_key');

        $connection = Connection::fromDsn('amqp://localhost/%2f/messages?exchange[default_publish_routing_key]=routing_key', [], $factory);
        $connection->publish('body');
    }

    public function testItCanPublishWithASuppliedRoutingKey()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = $this->createMock(\AMQPConnection::class),
            $amqpChannel = $this->createMock(\AMQPChannel::class),
            $amqpQueue = $this->createMock(\AMQPQueue::class),
            $amqpExchange = $this->createMock(\AMQPExchange::class)
        );

        $amqpExchange->expects($this->once())->method('publish')->with('body', 'routing_key');

        $connection = Connection::fromDsn('amqp://localhost/%2f/messages?exchange[default_publish_routing_key]=default_routing_key', [], $factory);
        $connection->publish('body', [], 0, new AmqpStamp('routing_key'));
    }

    public function testItDelaysTheMessageWithTheInitialSuppliedRoutingKeyAsArgument()
    {
        $amqpConnection = $this->createMock(\AMQPConnection::class);
        $amqpChannel = $this->createMock(\AMQPChannel::class);
        $delayQueue = $this->createMock(\AMQPQueue::class);

        $factory = $this->createMock(AmqpFactory::class);
        $factory->method('createConnection')->willReturn($amqpConnection);
        $factory->method('createChannel')->willReturn($amqpChannel);
        $factory->method('createQueue')->willReturn($delayQueue);
        $factory->method('createExchange')->will($this->onConsecutiveCalls(
            $delayExchange = $this->createMock(\AMQPExchange::class),
            $amqpExchange = $this->createMock(\AMQPExchange::class)
        ));

        $amqpExchange->expects($this->once())->method('setName')->with('messages');
        $amqpExchange->method('getName')->willReturn('messages');

        $delayExchange->expects($this->once())->method('setName')->with('delay');
        $delayExchange->expects($this->once())->method('declareExchange');
        $delayExchange->method('getName')->willReturn('delay');

        $connectionOptions = [
            'retry' => [
                'dead_routing_key' => 'my_dead_routing_key',
            ],
        ];

        $connection = Connection::fromDsn('amqp://localhost/%2f/messages', $connectionOptions, $factory);

        $delayQueue->expects($this->once())->method('setName')->with('delay_queue_120000');
        $delayQueue->expects($this->once())->method('setArguments')->with([
            'x-message-ttl' => 120000,
            'x-dead-letter-exchange' => 'messages',
        ]);
        $delayQueue->expects($this->once())->method('setArgument')->with(
            'x-dead-letter-routing-key',
            'routing_key'
        );

        $delayQueue->expects($this->once())->method('declareQueue');
        $delayQueue->expects($this->once())->method('bind')->with('delay', 'delay_120000');

        $delayExchange->expects($this->once())->method('publish')->with('{}', 'delay_120000', AMQP_NOPARAM, ['headers' => []]);
        $connection->publish('{}', [], 120000, new AmqpStamp('routing_key'));
    }

    public function testItCanPublishWithCustomFlagsAndAttributes()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = $this->createMock(\AMQPConnection::class),
            $amqpChannel = $this->createMock(\AMQPChannel::class),
            $amqpQueue = $this->createMock(\AMQPQueue::class),
            $amqpExchange = $this->createMock(\AMQPExchange::class)
        );

        $amqpExchange->expects($this->once())->method('publish')->with(
            'body',
            'routing_key',
            AMQP_IMMEDIATE,
            ['delivery_mode' => 2, 'headers' => ['type' => DummyMessage::class]]
        );

        $connection = Connection::fromDsn('amqp://localhost/%2f/messages', [], $factory);
        $connection->publish('body', ['type' => DummyMessage::class], 0, new AmqpStamp('routing_key', AMQP_IMMEDIATE, ['delivery_mode' => 2]));
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
