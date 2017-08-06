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
use Symfony\Component\Amqp\Queue;
use Symfony\Component\Amqp\RetryStrategy\ConstantRetryStrategy;
use Symfony\Component\Amqp\Test\AmqpTestTrait;

class QueueTest extends TestCase
{
    use AmqpTestTrait;

    private $channel;

    protected function setUp()
    {
        // Default queue is auto bounded to the default exchange.
        $this->createExchange(Broker::DEFAULT_EXCHANGE);
        // And all queues are bounded to the retry exchange.
        $this->createExchange(Broker::RETRY_EXCHANGE);

        $this->channel = $this->createChannel();
    }

    public function testDefaultConstructor()
    {
        $queue = new Queue($this->channel, 'test_queue.default');

        $this->assertSame('test_queue.default', $queue->getName());
        $this->assertSame(\AMQP_DURABLE, $queue->getFlags());
    }

    public function testCustomConstructor()
    {
        $this->createExchange('test_queue.custom_exchange');

        $queue = new Queue($this->channel, 'test_queue.routing_key', array(
            'flags' => \AMQP_NOPARAM,
            'exchange' => 'test_queue.custom_exchange',
            'retry_strategy' => $r = new ConstantRetryStrategy(5),
            'retry_strategy_queue_pattern' => '10',
        ));

        $this->assertSame('test_queue.routing_key', $queue->getName());
        $this->assertSame(\AMQP_NOPARAM, $queue->getFlags());
        $this->assertSame($r, $queue->getRetryStrategy());
        $this->assertSame('10', $queue->getRetryStrategyQueuePattern());
    }

    public function provideCustomBinding()
    {
        $defaultBindings = array(
            Broker::RETRY_EXCHANGE => array(
                array(
                    'routing_key' => 'test_queue.binding',
                    'bind_arguments' => array(),
                ),
            ),
        );

        yield array($defaultBindings, false);

        $bindings = $defaultBindings + array(
            Broker::DEFAULT_EXCHANGE => array(
                array(
                    'routing_key' => null,
                    'bind_arguments' => array(),
                ),
            ),
        );
        yield array($bindings, null);

        $bindings = $defaultBindings + array(
            Broker::DEFAULT_EXCHANGE => array(
                array(
                    'routing_key' => 'foobar',
                    'bind_arguments' => array(),
                ),
            ),
        );
        yield array($bindings, 'foobar');

        $bindings = $defaultBindings + array(
            Broker::DEFAULT_EXCHANGE => array(
                array(
                    'routing_key' => 'foobar',
                    'bind_arguments' => array(),
                ),
                array(
                    'routing_key' => 'baz',
                    'bind_arguments' => array(),
                ),
            ),
        );
        yield array($bindings, array('foobar', 'baz'));
    }

    /**
     * @dataProvider provideCustomBinding
     */
    public function testCustomBinding($expected, $routingKeys)
    {
        $queue = new Queue($this->channel, 'test_queue.binding', array(
            'routing_keys' => $routingKeys,
        ));

        $this->assertEquals($expected, $queue->getBindings());
    }

    /**
     * @expectedException \Symfony\Component\Amqp\Exception\InvalidArgumentException
     * @expectedExceptionMessage "routing_keys" option should be a string, false, null or an array of string, "object" given.
     */
    public function testInvalidRoutingKeys()
    {
        new Queue($this->channel, 'test_queue.binding', array(
            'routing_keys' => new \stdClass(),
        ));
    }
}
