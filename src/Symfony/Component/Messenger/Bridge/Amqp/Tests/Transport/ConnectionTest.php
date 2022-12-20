<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Amqp\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Messenger\Bridge\Amqp\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpFactory;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\Connection;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;

/**
 * @requires extension amqp
 *
 * @group time-sensitive
 */
class ConnectionTest extends TestCase
{
    use ExpectDeprecationTrait;

    private const DEFAULT_EXCHANGE_NAME = 'messages';

    public function testItCannotBeConstructedWithAWrongDsn()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('The given AMQP DSN "amqp://:" is invalid.');
        Connection::fromDsn('amqp://:');
    }

    public function testItCanBeConstructedWithDefaults()
    {
        self::assertEquals(new Connection([
            'host' => 'localhost',
            'port' => 5672,
            'vhost' => '/',
        ], [
            'name' => self::DEFAULT_EXCHANGE_NAME,
        ], [
            self::DEFAULT_EXCHANGE_NAME => [],
        ]), Connection::fromDsn('amqp://'));
    }

    public function testItCanBeConstructedWithAnAmqpsDsn()
    {
        self::assertEquals(new Connection([
            'host' => 'localhost',
            'port' => 5671,
            'vhost' => '/',
            'cacert' => '/etc/ssl/certs',
        ], [
            'name' => self::DEFAULT_EXCHANGE_NAME,
        ], [
            self::DEFAULT_EXCHANGE_NAME => [],
        ]), Connection::fromDsn('amqps://localhost?'.
            'cacert=/etc/ssl/certs'));
    }

    public function testItGetsParametersFromTheDsn()
    {
        self::assertEquals(new Connection([
            'host' => 'host',
            'port' => 5672,
            'vhost' => '/',
        ], [
            'name' => 'custom',
        ], [
            'custom' => [],
        ]), Connection::fromDsn('amqp://host/%2f/custom'));
    }

    public function testOverrideOptionsViaQueryParameters()
    {
        self::assertEquals(new Connection([
            'host' => 'localhost',
            'port' => 1234,
            'vhost' => 'vhost',
            'login' => 'guest',
            'password' => 'password',
        ], [
            'name' => 'exchangeName',
        ], [
            'queueName' => [],
        ]), Connection::fromDsn('amqp://guest:password@localhost:1234/vhost/queue?exchange[name]=exchangeName&queues[queueName]'));
    }

    public function testOptionsAreTakenIntoAccountAndOverwrittenByDsn()
    {
        self::assertEquals(new Connection([
            'host' => 'localhost',
            'port' => 5672,
            'vhost' => '/',
            'persistent' => 'true',
        ], [
            'name' => 'exchangeName',
        ], [
            'queueName' => [],
        ]), Connection::fromDsn('amqp://localhost/%2f/queue?exchange[name]=exchangeName&queues[queueName]', [
            'persistent' => 'true',
            'exchange' => ['name' => 'toBeOverwritten'],
        ]));
    }

    /**
     * @group legacy
     */
    public function testDeprecationIfInvalidOptionIsPassedWithDsn()
    {
        $this->expectDeprecation('Since symfony/messenger 5.1: Invalid option(s) "foo" passed to the AMQP Messenger transport. Passing invalid options is deprecated.');
        Connection::fromDsn('amqp://host?foo=bar');
    }

    /**
     * @group legacy
     */
    public function testDeprecationIfInvalidOptionIsPassedAsArgument()
    {
        $this->expectDeprecation('Since symfony/messenger 5.1: Invalid option(s) "foo" passed to the AMQP Messenger transport. Passing invalid options is deprecated.');
        Connection::fromDsn('amqp://host', ['foo' => 'bar']);
    }

    /**
     * @group legacy
     */
    public function testDeprecationIfInvalidQueueOptionIsPassed()
    {
        $this->expectDeprecation('Since symfony/messenger 5.1: Invalid queue option(s) "foo" passed to the AMQP Messenger transport. Passing invalid queue options is deprecated.');
        Connection::fromDsn('amqp://host', ['queues' => ['queueName' => ['foo' => 'bar']]]);
    }

    /**
     * @group legacy
     */
    public function testDeprecationIfInvalidExchangeOptionIsPassed()
    {
        $this->expectDeprecation('Since symfony/messenger 5.1: Invalid exchange option(s) "foo" passed to the AMQP Messenger transport. Passing invalid exchange options is deprecated.');
        Connection::fromDsn('amqp://host', ['exchange' => ['foo' => 'bar']]);
    }

    public function testSetsParametersOnTheQueueAndExchange()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = self::createMock(\AMQPConnection::class),
            $amqpChannel = self::createMock(\AMQPChannel::class),
            $amqpQueue = self::createMock(\AMQPQueue::class),
            $amqpExchange = self::createMock(\AMQPExchange::class)
        );

        $amqpQueue->expects(self::once())->method('setArguments')->with([
            'x-dead-letter-exchange' => 'dead-exchange',
            'x-delay' => 100,
            'x-expires' => 150,
            'x-max-length' => 200,
            'x-max-length-bytes' => 300,
            'x-max-priority' => 4,
            'x-message-ttl' => 100,
        ]);

        $amqpExchange->expects(self::once())->method('setArguments')->with([
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
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Integer expected for queue argument');

        Connection::fromDsn($dsn, $options);
    }

    public function testItUsesANormalConnectionByDefault()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = self::createMock(\AMQPConnection::class),
            $amqpChannel = self::createMock(\AMQPChannel::class),
            $amqpQueue = self::createMock(\AMQPQueue::class),
            $amqpExchange = self::createMock(\AMQPExchange::class)
        );

        // makes sure the channel looks connected, so it's not re-created
        $amqpChannel->expects(self::once())->method('isConnected')->willReturn(true);
        $amqpConnection->expects(self::once())->method('connect');

        $connection = Connection::fromDsn('amqp://localhost', [], $factory);
        $connection->publish('body');
    }

    public function testItAllowsToUseAPersistentConnection()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = self::createMock(\AMQPConnection::class),
            $amqpChannel = self::createMock(\AMQPChannel::class),
            $amqpQueue = self::createMock(\AMQPQueue::class),
            $amqpExchange = self::createMock(\AMQPExchange::class)
        );

        // makes sure the channel looks connected, so it's not re-created
        $amqpChannel->expects(self::once())->method('isConnected')->willReturn(true);
        $amqpConnection->expects(self::once())->method('pconnect');

        $connection = Connection::fromDsn('amqp://localhost?persistent=true', [], $factory);
        $connection->publish('body');
    }

    public function testItSetupsTheConnectionWithDefaults()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = self::createMock(\AMQPConnection::class),
            $amqpChannel = self::createMock(\AMQPChannel::class),
            $amqpQueue = self::createMock(\AMQPQueue::class),
            $amqpExchange = self::createMock(\AMQPExchange::class)
        );

        $amqpExchange->expects(self::once())->method('declareExchange');
        $amqpExchange->expects(self::once())->method('publish')->with('body', null, \AMQP_NOPARAM, ['headers' => [], 'delivery_mode' => 2, 'timestamp' => time()]);
        $amqpQueue->expects(self::once())->method('declareQueue');
        $amqpQueue->expects(self::once())->method('bind')->with(self::DEFAULT_EXCHANGE_NAME, null);

        $connection = Connection::fromDsn('amqp://localhost', [], $factory);
        $connection->publish('body');
    }

    public function testItSetupsTheConnection()
    {
        $amqpConnection = self::createMock(\AMQPConnection::class);
        $amqpChannel = self::createMock(\AMQPChannel::class);
        $amqpExchange = self::createMock(\AMQPExchange::class);
        $amqpQueue0 = self::createMock(\AMQPQueue::class);
        $amqpQueue1 = self::createMock(\AMQPQueue::class);

        $factory = self::createMock(AmqpFactory::class);
        $factory->method('createConnection')->willReturn($amqpConnection);
        $factory->method('createChannel')->willReturn($amqpChannel);
        $factory->method('createExchange')->willReturn($amqpExchange);
        $factory->method('createQueue')->will(self::onConsecutiveCalls($amqpQueue0, $amqpQueue1));

        $amqpExchange->expects(self::once())->method('declareExchange');
        $amqpExchange->expects(self::once())->method('publish')->with('body', 'routing_key', \AMQP_NOPARAM, ['headers' => [], 'delivery_mode' => 2, 'timestamp' => time()]);
        $amqpQueue0->expects(self::once())->method('declareQueue');
        $amqpQueue0->expects(self::exactly(2))->method('bind')->withConsecutive(
            [self::DEFAULT_EXCHANGE_NAME, 'binding_key0'],
            [self::DEFAULT_EXCHANGE_NAME, 'binding_key1']
        );
        $amqpQueue1->expects(self::once())->method('declareQueue');
        $amqpQueue1->expects(self::exactly(2))->method('bind')->withConsecutive(
            [self::DEFAULT_EXCHANGE_NAME, 'binding_key2'],
            [self::DEFAULT_EXCHANGE_NAME, 'binding_key3']
        );

        $dsn = 'amqp://localhost?'.
            'exchange[default_publish_routing_key]=routing_key&'.
            'queues[queue0][binding_keys][0]=binding_key0&'.
            'queues[queue0][binding_keys][1]=binding_key1&'.
            'queues[queue1][binding_keys][0]=binding_key2&'.
            'queues[queue1][binding_keys][1]=binding_key3';

        $connection = Connection::fromDsn($dsn, [], $factory);
        $connection->publish('body');
    }

    public function testItSetupsTheTTLConnection()
    {
        $amqpConnection = self::createMock(\AMQPConnection::class);
        $amqpChannel = self::createMock(\AMQPChannel::class);
        $amqpExchange = self::createMock(\AMQPExchange::class);
        $amqpQueue0 = self::createMock(\AMQPQueue::class);
        $amqpQueue1 = self::createMock(\AMQPQueue::class);

        $factory = self::createMock(AmqpFactory::class);
        $factory->method('createConnection')->willReturn($amqpConnection);
        $factory->method('createChannel')->willReturn($amqpChannel);
        $factory->method('createExchange')->willReturn($amqpExchange);
        $factory->method('createQueue')->will(self::onConsecutiveCalls($amqpQueue0, $amqpQueue1));

        $amqpExchange->expects(self::once())->method('declareExchange');
        $amqpExchange->expects(self::once())->method('publish')->with('body', 'routing_key', \AMQP_NOPARAM, ['headers' => [], 'delivery_mode' => 2, 'timestamp' => time()]);
        $amqpQueue0->expects(self::once())->method('declareQueue');
        $amqpQueue0->expects(self::exactly(2))->method('bind')->withConsecutive(
            [self::DEFAULT_EXCHANGE_NAME, 'binding_key0'],
            [self::DEFAULT_EXCHANGE_NAME, 'binding_key1']
        );
        $amqpQueue1->expects(self::once())->method('declareQueue');
        $amqpQueue1->expects(self::exactly(2))->method('bind')->withConsecutive(
            [self::DEFAULT_EXCHANGE_NAME, 'binding_key2'],
            [self::DEFAULT_EXCHANGE_NAME, 'binding_key3']
        );

        $dsn = 'amqps://localhost?'.
            'cacert=/etc/ssl/certs&'.
            'exchange[default_publish_routing_key]=routing_key&'.
            'queues[queue0][binding_keys][0]=binding_key0&'.
            'queues[queue0][binding_keys][1]=binding_key1&'.
            'queues[queue1][binding_keys][0]=binding_key2&'.
            'queues[queue1][binding_keys][1]=binding_key3';

        $connection = Connection::fromDsn($dsn, [], $factory);
        $connection->publish('body');
    }

    public function testBindingArguments()
    {
        $amqpConnection = self::createMock(\AMQPConnection::class);
        $amqpChannel = self::createMock(\AMQPChannel::class);
        $amqpExchange = self::createMock(\AMQPExchange::class);
        $amqpQueue = self::createMock(\AMQPQueue::class);

        $factory = self::createMock(AmqpFactory::class);
        $factory->method('createConnection')->willReturn($amqpConnection);
        $factory->method('createChannel')->willReturn($amqpChannel);
        $factory->method('createExchange')->willReturn($amqpExchange);
        $factory->method('createQueue')->willReturn($amqpQueue);

        $amqpExchange->expects(self::once())->method('declareExchange');
        $amqpExchange->expects(self::once())->method('publish')->with('body', null, \AMQP_NOPARAM, ['headers' => [], 'delivery_mode' => 2, 'timestamp' => time()]);
        $amqpQueue->expects(self::once())->method('declareQueue');
        $amqpQueue->expects(self::exactly(1))->method('bind')->withConsecutive(
            [self::DEFAULT_EXCHANGE_NAME, null, ['x-match' => 'all']]
        );

        $dsn = 'amqp://localhost?exchange[type]=headers'.
            '&queues[queue0][binding_arguments][x-match]=all';

        $connection = Connection::fromDsn($dsn, [], $factory);
        $connection->publish('body');
    }

    public function testItCanDisableTheSetup()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = self::createMock(\AMQPConnection::class),
            $amqpChannel = self::createMock(\AMQPChannel::class),
            $amqpQueue = self::createMock(\AMQPQueue::class),
            $amqpExchange = self::createMock(\AMQPExchange::class)
        );

        $amqpExchange->expects(self::never())->method('declareExchange');
        $amqpQueue->expects(self::never())->method('declareQueue');
        $amqpQueue->expects(self::never())->method('bind');

        $connection = Connection::fromDsn('amqp://localhost', ['auto_setup' => 'false'], $factory);
        $connection->publish('body');

        $connection = Connection::fromDsn('amqp://localhost', ['auto_setup' => false], $factory);
        $connection->publish('body');

        $connection = Connection::fromDsn('amqp://localhost?auto_setup=false', [], $factory);
        $connection->publish('body');
    }

    public function testItSetupQueuesOnce()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = self::createMock(\AMQPConnection::class),
            $amqpChannel = self::createMock(\AMQPChannel::class),
            $amqpQueue = self::createMock(\AMQPQueue::class),
            $amqpExchange = self::createMock(\AMQPExchange::class)
        );

        $amqpExchange->expects(self::once())->method('declareExchange');
        $amqpQueue->expects(self::once())->method('declareQueue');
        $amqpQueue->expects(self::once())->method('bind');

        $connection = Connection::fromDsn('amqp://localhost', ['auto_setup' => true], $factory);
        $connection->publish('body');
        $connection->publish('body');
    }

    /**
     * @group legacy
     */
    public function testSetChannelPrefetchWhenSetup()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = self::createMock(\AMQPConnection::class),
            $amqpChannel = self::createMock(\AMQPChannel::class),
            $amqpQueue = self::createMock(\AMQPQueue::class),
            $amqpExchange = self::createMock(\AMQPExchange::class)
        );

        // makes sure the channel looks connected, so it's not re-created
        $amqpChannel->expects(self::any())->method('isConnected')->willReturn(true);

        $amqpChannel->expects(self::never())->method('setPrefetchCount');

        $this->expectDeprecation('Since symfony/messenger 5.3: The "prefetch_count" option passed to the AMQP Messenger transport has no effect and should not be used.');
        $connection = Connection::fromDsn('amqp://localhost?prefetch_count=2', [], $factory);
        $connection->setup();
    }

    public function testAutoSetupWithDelayDeclaresExchangeQueuesAndDelay()
    {
        $amqpConnection = self::createMock(\AMQPConnection::class);
        $amqpChannel = self::createMock(\AMQPChannel::class);

        $factory = self::createMock(AmqpFactory::class);
        $factory->method('createConnection')->willReturn($amqpConnection);
        $factory->method('createChannel')->willReturn($amqpChannel);
        $factory->method('createQueue')->will(self::onConsecutiveCalls($amqpQueue = self::createMock(\AMQPQueue::class), $delayQueue = self::createMock(\AMQPQueue::class)));
        $factory->method('createExchange')->will(self::onConsecutiveCalls($amqpExchange = self::createMock(\AMQPExchange::class), $delayExchange = self::createMock(\AMQPExchange::class)));

        $amqpExchange->expects(self::once())->method('setName')->with(self::DEFAULT_EXCHANGE_NAME);
        $amqpExchange->expects(self::once())->method('declareExchange');
        $amqpQueue->expects(self::once())->method('setName')->with(self::DEFAULT_EXCHANGE_NAME);
        $amqpQueue->expects(self::once())->method('declareQueue');

        $delayExchange->expects(self::once())->method('setName')->with('delays');
        $delayExchange->expects(self::once())->method('declareExchange');
        $delayExchange->expects(self::once())->method('publish');

        $connection = Connection::fromDsn('amqp://localhost', [], $factory);
        $connection->publish('{}', ['x-some-headers' => 'foo'], 5000);
    }

    public function testItDelaysTheMessage()
    {
        $delayExchange = self::createMock(\AMQPExchange::class);
        $delayExchange->expects(self::once())
            ->method('publish')
            ->with('{}', 'delay_messages__5000_delay', \AMQP_NOPARAM, [
                'headers' => ['x-some-headers' => 'foo'],
                'delivery_mode' => 2,
                'timestamp' => time(),
            ]);
        $connection = $this->createDelayOrRetryConnection($delayExchange, self::DEFAULT_EXCHANGE_NAME, 'delay_messages__5000_delay');

        $connection->publish('{}', ['x-some-headers' => 'foo'], 5000);
    }

    public function testItRetriesTheMessage()
    {
        $delayExchange = self::createMock(\AMQPExchange::class);
        $delayExchange->expects(self::once())
            ->method('publish')
            ->with('{}', 'delay_messages__5000_retry', \AMQP_NOPARAM);
        $connection = $this->createDelayOrRetryConnection($delayExchange, '', 'delay_messages__5000_retry');

        $amqpEnvelope = self::createMock(\AMQPEnvelope::class);
        $amqpStamp = AmqpStamp::createFromAmqpEnvelope($amqpEnvelope, null, '');
        $connection->publish('{}', [], 5000, $amqpStamp);
    }

    public function testItDelaysTheMessageWithADifferentRoutingKeyAndTTLs()
    {
        $amqpConnection = self::createMock(\AMQPConnection::class);
        $amqpChannel = self::createMock(\AMQPChannel::class);

        $factory = self::createMock(AmqpFactory::class);
        $factory->method('createConnection')->willReturn($amqpConnection);
        $factory->method('createChannel')->willReturn($amqpChannel);
        $factory->method('createQueue')->will(self::onConsecutiveCalls(self::createMock(\AMQPQueue::class), $delayQueue = self::createMock(\AMQPQueue::class)));
        $factory->method('createExchange')->will(self::onConsecutiveCalls(self::createMock(\AMQPExchange::class), $delayExchange = self::createMock(\AMQPExchange::class)));

        $connectionOptions = [
            'retry' => [
                'dead_routing_key' => 'my_dead_routing_key',
            ],
        ];

        $connection = Connection::fromDsn('amqp://localhost', $connectionOptions, $factory);

        $delayQueue->expects(self::once())->method('setName')->with('delay_messages__120000_delay');
        $delayQueue->expects(self::once())->method('setArguments')->with([
            'x-message-ttl' => 120000,
            'x-expires' => 120000 + 10000,
            'x-dead-letter-exchange' => self::DEFAULT_EXCHANGE_NAME,
            'x-dead-letter-routing-key' => '',
        ]);

        $delayQueue->expects(self::once())->method('declareQueue');
        $delayQueue->expects(self::once())->method('bind')->with('delays', 'delay_messages__120000_delay');

        $delayExchange->expects(self::once())->method('publish')->with('{}', 'delay_messages__120000_delay', \AMQP_NOPARAM, ['headers' => [], 'delivery_mode' => 2, 'timestamp' => time()]);
        $connection->publish('{}', [], 120000);
    }

    public function testNoCredentialLeakageWhenConnectionFails()
    {
        self::expectException(\AMQPException::class);
        self::expectExceptionMessage('Could not connect to the AMQP server. Please verify the provided DSN.');
        $factory = new TestAmqpFactory(
            $amqpConnection = self::createMock(\AMQPConnection::class),
            $amqpChannel = self::createMock(\AMQPChannel::class),
            $amqpQueue = self::createMock(\AMQPQueue::class),
            $amqpExchange = self::createMock(\AMQPExchange::class)
        );

        $amqpConnection->method('connect')->willThrowException(
            new \AMQPConnectionException('Oups.')
        );

        $connection = Connection::fromDsn('amqp://user:secretpassword@localhost', [], $factory);
        $connection->channel();
    }

    public function testNoCaCertOnSslConnectionFromDsn()
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('No CA certificate has been provided. Set "amqp.cacert" in your php.ini or pass the "cacert" parameter in the DSN to use SSL. Alternatively, you can use amqp:// to use without SSL.');

        $factory = new TestAmqpFactory(
            $amqpConnection = self::createMock(\AMQPConnection::class),
            $amqpChannel = self::createMock(\AMQPChannel::class),
            $amqpQueue = self::createMock(\AMQPQueue::class),
            $amqpExchange = self::createMock(\AMQPExchange::class)
        );

        $oldCaCertValue = ini_set('amqp.cacert', '');

        try {
            Connection::fromDsn('amqps://', [], $factory);
        } finally {
            ini_set('amqp.cacert', $oldCaCertValue);
        }
    }

    public function testAmqpStampHeadersAreUsed()
    {
        $factory = new TestAmqpFactory(
            self::createMock(\AMQPConnection::class),
            self::createMock(\AMQPChannel::class),
            self::createMock(\AMQPQueue::class),
            $amqpExchange = self::createMock(\AMQPExchange::class)
        );

        $amqpExchange->expects(self::once())->method('publish')->with('body', null, \AMQP_NOPARAM, ['headers' => ['Foo' => 'X', 'Bar' => 'Y'], 'delivery_mode' => 2, 'timestamp' => time()]);

        $connection = Connection::fromDsn('amqp://localhost', [], $factory);
        $connection->publish('body', ['Foo' => 'X'], 0, new AmqpStamp(null, \AMQP_NOPARAM, ['headers' => ['Bar' => 'Y']]));
    }

    public function testAmqpStampDeliveryModeIsUsed()
    {
        $factory = new TestAmqpFactory(
            self::createMock(\AMQPConnection::class),
            self::createMock(\AMQPChannel::class),
            self::createMock(\AMQPQueue::class),
            $amqpExchange = self::createMock(\AMQPExchange::class)
        );

        $amqpExchange->expects(self::once())->method('publish')->with('body', null, \AMQP_NOPARAM, ['headers' => [], 'delivery_mode' => 1, 'timestamp' => time()]);

        $connection = Connection::fromDsn('amqp://localhost', [], $factory);
        $connection->publish('body', [], 0, new AmqpStamp(null, \AMQP_NOPARAM, ['delivery_mode' => 1]));
    }

    public function testItCanPublishWithTheDefaultRoutingKey()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = self::createMock(\AMQPConnection::class),
            $amqpChannel = self::createMock(\AMQPChannel::class),
            $amqpQueue = self::createMock(\AMQPQueue::class),
            $amqpExchange = self::createMock(\AMQPExchange::class)
        );

        $amqpExchange->expects(self::once())->method('publish')->with('body', 'routing_key');

        $connection = Connection::fromDsn('amqp://localhost?exchange[default_publish_routing_key]=routing_key', [], $factory);
        $connection->publish('body');
    }

    public function testItCanPublishWithASuppliedRoutingKey()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = self::createMock(\AMQPConnection::class),
            $amqpChannel = self::createMock(\AMQPChannel::class),
            $amqpQueue = self::createMock(\AMQPQueue::class),
            $amqpExchange = self::createMock(\AMQPExchange::class)
        );

        $amqpExchange->expects(self::once())->method('publish')->with('body', 'routing_key');

        $connection = Connection::fromDsn('amqp://localhost?exchange[default_publish_routing_key]=default_routing_key', [], $factory);
        $connection->publish('body', [], 0, new AmqpStamp('routing_key'));
    }

    public function testItDelaysTheMessageWithTheInitialSuppliedRoutingKeyAsArgument()
    {
        $amqpConnection = self::createMock(\AMQPConnection::class);
        $amqpChannel = self::createMock(\AMQPChannel::class);

        $factory = self::createMock(AmqpFactory::class);
        $factory->method('createConnection')->willReturn($amqpConnection);
        $factory->method('createChannel')->willReturn($amqpChannel);
        $factory->method('createQueue')->will(self::onConsecutiveCalls(self::createMock(\AMQPQueue::class), $delayQueue = self::createMock(\AMQPQueue::class)));
        $factory->method('createExchange')->will(self::onConsecutiveCalls(self::createMock(\AMQPExchange::class), $delayExchange = self::createMock(\AMQPExchange::class)));

        $connectionOptions = [
            'retry' => [
                'dead_routing_key' => 'my_dead_routing_key',
            ],
        ];

        $connection = Connection::fromDsn('amqp://localhost', $connectionOptions, $factory);

        $delayQueue->expects(self::once())->method('setName')->with('delay_messages_routing_key_120000_delay');
        $delayQueue->expects(self::once())->method('setArguments')->with([
            'x-message-ttl' => 120000,
            'x-expires' => 120000 + 10000,
            'x-dead-letter-exchange' => self::DEFAULT_EXCHANGE_NAME,
            'x-dead-letter-routing-key' => 'routing_key',
        ]);

        $delayQueue->expects(self::once())->method('declareQueue');
        $delayQueue->expects(self::once())->method('bind')->with('delays', 'delay_messages_routing_key_120000_delay');

        $delayExchange->expects(self::once())->method('publish')->with('{}', 'delay_messages_routing_key_120000_delay', \AMQP_NOPARAM, ['headers' => [], 'delivery_mode' => 2, 'timestamp' => time()]);
        $connection->publish('{}', [], 120000, new AmqpStamp('routing_key'));
    }

    public function testItCanPublishWithCustomFlagsAndAttributes()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = self::createMock(\AMQPConnection::class),
            $amqpChannel = self::createMock(\AMQPChannel::class),
            $amqpQueue = self::createMock(\AMQPQueue::class),
            $amqpExchange = self::createMock(\AMQPExchange::class)
        );

        $amqpExchange->expects(self::once())->method('publish')->with(
            'body',
            'routing_key',
            \AMQP_IMMEDIATE,
            ['delivery_mode' => 2, 'headers' => ['type' => DummyMessage::class], 'timestamp' => time()]
        );

        $connection = Connection::fromDsn('amqp://localhost', [], $factory);
        $connection->publish('body', ['type' => DummyMessage::class], 0, new AmqpStamp('routing_key', \AMQP_IMMEDIATE, ['delivery_mode' => 2]));
    }

    public function testItPublishMessagesWithoutWaitingForConfirmation()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = self::createMock(\AMQPConnection::class),
            $amqpChannel = self::createMock(\AMQPChannel::class),
            $amqpQueue = self::createMock(\AMQPQueue::class),
            $amqpExchange = self::createMock(\AMQPExchange::class)
        );

        $amqpChannel->expects(self::never())->method('waitForConfirm')->with(0.5);

        $connection = Connection::fromDsn('amqp://localhost', [], $factory);
        $connection->publish('body');
    }

    public function testSetChannelToConfirmMessage()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = self::createMock(\AMQPConnection::class),
            $amqpChannel = self::createMock(\AMQPChannel::class),
            $amqpQueue = self::createMock(\AMQPQueue::class),
            $amqpExchange = self::createMock(\AMQPExchange::class)
        );

        $amqpChannel->expects(self::once())->method('confirmSelect');
        $amqpChannel->expects(self::once())->method('setConfirmCallback');
        $connection = Connection::fromDsn('amqp://localhost?confirm_timeout=0.5', [], $factory);
        $connection->setup();
    }

    public function testItCanPublishAndWaitForConfirmation()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = self::createMock(\AMQPConnection::class),
            $amqpChannel = self::createMock(\AMQPChannel::class),
            $amqpQueue = self::createMock(\AMQPQueue::class),
            $amqpExchange = self::createMock(\AMQPExchange::class)
        );

        $amqpChannel->expects(self::once())->method('waitForConfirm')->with(0.5);

        $connection = Connection::fromDsn('amqp://localhost?confirm_timeout=0.5', [], $factory);
        $connection->publish('body');
    }

    public function testItCanBeConstructedWithTLSOptionsAndNonTLSDsn()
    {
        self::assertEquals(new Connection([
            'host' => 'localhost',
            'port' => 5672,
            'vhost' => '/',
        ], [
            'name' => self::DEFAULT_EXCHANGE_NAME,
        ], [
            self::DEFAULT_EXCHANGE_NAME => [],
        ]), Connection::fromDsn('amqp://', [
            'cacert' => 'foobar',
            'cert' => 'foobar',
            'key' => 'foobar',
            'verify' => false,
        ]));
    }

    private function createDelayOrRetryConnection(\AMQPExchange $delayExchange, string $deadLetterExchangeName, string $delayQueueName): Connection
    {
        $amqpConnection = self::createMock(\AMQPConnection::class);
        $amqpChannel = self::createMock(\AMQPChannel::class);

        $factory = self::createMock(AmqpFactory::class);
        $factory->method('createConnection')->willReturn($amqpConnection);
        $factory->method('createChannel')->willReturn($amqpChannel);
        $factory->method('createQueue')->will(self::onConsecutiveCalls(self::createMock(\AMQPQueue::class), $delayQueue = self::createMock(\AMQPQueue::class)));
        $factory->method('createExchange')->will(self::onConsecutiveCalls(self::createMock(\AMQPExchange::class), $delayExchange));

        $delayQueue->expects(self::once())->method('setName')->with($delayQueueName);
        $delayQueue->expects(self::once())->method('setArguments')->with([
            'x-message-ttl' => 5000,
            'x-expires' => 5000 + 10000,
            'x-dead-letter-exchange' => $deadLetterExchangeName,
            'x-dead-letter-routing-key' => '',
        ]);

        $delayQueue->expects(self::once())->method('declareQueue');
        $delayQueue->expects(self::once())->method('bind')->with('delays', $delayQueueName);

        return Connection::fromDsn('amqp://localhost', [], $factory);
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
