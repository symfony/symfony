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

use Symfony\Component\Amqp\Exchange;
use Symfony\Component\Amqp\Queue;
use Symfony\Component\Amqp\SimpleBroker;

class SimpleBrokerTest extends TestCase
{
    use AmqpTestTrait;

    public function testConstructor()
    {
        $conn = $this->createConnection();

        $broker = new SimpleBroker($conn, array(), array());

        $this->assertSame($conn, $broker->getConnection());
        $this->assertTrue($broker->isConnected());

        $channel = $broker->getChannel();

        $this->assertInstanceOf(\AMQPChannel::class, $channel);
        $this->assertTrue($channel->isConnected());
    }

    public function testCreateWithDsn()
    {
        $broker = SimpleBroker::createWithDsn('amqp://foo:bar@rabbitmq-3.lxc:1234/symfony_amqp', array(), array());

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

    public function testConnection()
    {
        $broker = $this->createBroker();

        $this->assertFalse($broker->isConnected());

        $broker->connect();

        $this->assertTrue($broker->isConnected());

        $broker->disconnect();

        $this->assertFalse($broker->isConnected());
    }

    public function testPublish()
    {
        $exchange = $this->createExchange('test_broker_simple.publish');

        $queue = $this->createQueue('test_broker_simple.publish');
        $queue->purge();
        $queue->bind($exchange->getName(), 'test_broker_simple.publish');

        $broker = $this->createBroker(array($queue), array($exchange));

        $broker->publish('test_broker_simple.publish', 'payload-1', array(
            'exchange' => $exchange->getName(),
        ));

        $this->assertNextMessageBody('payload-1', 'test_broker_simple.publish');
    }

    /**
     * @expectedException \Symfony\Component\Amqp\Exception\UndefinedExchangeException
     * @expectedExceptionMessage The exchange "test_broker_simple.publish_unknown_exchange" does not exist.
     */
    public function testPublishInUnknownExchange()
    {
        $broker = $this->createBroker();

        $broker->publish('test_broker_simple.publish_unknown_exchange', 'payload-1', array(
            'exchange' => 'test_broker_simple.publish_unknown_exchange',
        ));
    }

    public function testMove()
    {
        $exchange = $this->createExchange('test_broker_simple.move');

        $queueFrom = $this->createQueue('test_broker_simple.move.from');
        $queueFrom->purge();
        $queueFrom->bind($exchange->getName(), 'test_broker_simple.move.from');

        $queueTo = $this->createQueue('test_broker_simple.move.to');
        $queueTo->purge();
        $queueTo->bind($exchange->getName(), 'test_broker_simple.move.to');

        $broker = $this->createBroker(array($queueFrom, $queueTo), array($exchange));

        $broker->publish('test_broker_simple.move.from', 'payload-42', array(
            'exchange' => $exchange->getName(),
            'app_id' => 'app',
            'headers' => array(
                'foo' => 'bar',
            ),
        ));

        usleep(500);

        $message = $broker->get('test_broker_simple.move.from', \AMQP_AUTOACK);
        $broker->move($message, 'test_broker_simple.move.to', array(
            'exchange' => $exchange->getName(),
        ));

        usleep(500);

        $this->assertQueueSize(1, 'test_broker_simple.move.to');

        $message = $broker->get('test_broker_simple.move.to', \AMQP_AUTOACK);

        $this->assertSame('payload-42', $message->getBody());
        $this->assertSame('bar', $message->getHeader('foo'));
        $this->assertSame('app', $message->getAppId());
    }

    public function testMoveToDeadLetter()
    {
        $exchange = $this->createExchange('test_broker_simple.move_to_dl');

        $queueFrom = $this->createQueue('test_broker_simple.move_to_dl');
        $queueFrom->purge();
        $queueFrom->bind($exchange->getName(), 'test_broker_simple.move_to_dl');

        $queueDl = $this->createQueue('test_broker_simple.move_to_dl.dead');
        $queueDl->purge();
        $queueDl->bind($exchange->getName(), 'test_broker_simple.move_to_dl.dead');

        $broker = $this->createBroker(array($queueFrom, $queueDl), array($exchange));

        $broker->publish('test_broker_simple.move_to_dl', 'payload-42', array(
            'exchange' => $exchange->getName(),
            'app_id' => 'app',
            'headers' => array(
                'foo' => 'bar',
            ),
        ));

        usleep(500);

        $message = $broker->get('test_broker_simple.move_to_dl', \AMQP_AUTOACK);
        $broker->moveToDeadLetter($message, array(
            'exchange' => $exchange->getName(),
        ));

        usleep(500);

        $this->assertQueueSize(1, 'test_broker_simple.move_to_dl.dead');

        $message = $broker->get('test_broker_simple.move_to_dl.dead', \AMQP_AUTOACK);

        $this->assertSame('payload-42', $message->getBody());
        $this->assertSame('bar', $message->getHeader('foo'));
        $this->assertSame('app', $message->getAppId());
    }

    public function testGet()
    {
        $exchange = $this->createExchange('test_broker_simple.get');

        $queue = $this->createQueue('test_broker_simple.get');
        $queue->purge();
        $queue->bind($exchange->getName(), 'test_broker_simple.get');

        $broker = $this->createBroker(array($queue), array($exchange));

        $this->emptyQueue('test_broker_simple.get');

        $broker->publish('test_broker_simple.get', 'payload-42', array(
            'exchange' => $exchange->getName(),
        ));
        usleep(500);

        $msg = $broker->get('test_broker_simple.get');

        $this->assertInstanceOf(\AMQPEnvelope::class, $msg);
        $this->assertSame('payload-42', $msg->getBody());
    }

    /**
     * @expectedException \Symfony\Component\Amqp\Exception\UndefinedQueueException
     * @expectedExceptionMessage The queue "test_broker_simple.get_unknown_exchange" does not exist.
     */
    public function testGetUnknownQueue()
    {
        $broker = $this->createBroker();

        $broker->get('test_broker_simple.get_unknown_exchange');
    }

    public function testConsume()
    {
        $exchange = $this->createExchange('test_broker_simple.consume');

        $queue = $this->createQueue('test_broker_simple.consume');
        $queue->purge();
        $queue->bind($exchange->getName(), 'test_broker_simple.consume');

        $broker = $this->createBroker(array($queue), array($exchange));

        $this->emptyQueue('test_broker_simple.consume');

        $broker->publish('test_broker_simple.consume', 'payload-42', array(
            'exchange' => $exchange->getName(),
        ));
        usleep(500);

        $consumed = false;
        $broker->consume('test_broker_simple.consume', function (\AMQPEnvelope $msg) use (&$consumed) {
            $consumed = true;
            $this->assertInstanceOf(\AMQPEnvelope::class, $msg);
            $this->assertSame('payload-42', $msg->getBody());

            return false;
        }, \AMQP_AUTOACK);
        $this->assertTrue($consumed);
    }

    public function testAck()
    {
        $exchange = $this->createExchange('test_broker_simple.ack');

        $queue = $this->createQueue('test_broker_simple.ack');
        $queue->purge();
        $queue->bind($exchange->getName(), 'test_broker_simple.ack');

        $broker = $this->createBroker(array($queue), array($exchange));

        $this->emptyQueue('test_broker_simple.ack');

        $broker->publish('test_broker_simple.ack', 'payload-42', array(
            'exchange' => $exchange->getName(),
        ));
        usleep(500);

        $msg = $broker->get('test_broker_simple.ack');

        $broker->ack($msg);
        $broker->disconnect();

        $this->assertQueueSize(0, 'test_broker_simple.ack');
    }

    public function testNack()
    {
        $exchange = $this->createExchange('test_broker_simple.nack');

        $queue = $this->createQueue('test_broker_simple.nack');
        $queue->purge();
        $queue->bind($exchange->getName(), 'test_broker_simple.nack');

        $broker = $this->createBroker(array($queue), array($exchange));

        $this->emptyQueue('test_broker_simple.nack');

        $broker->publish('test_broker_simple.nack', 'payload-42', array(
            'exchange' => $exchange->getName(),
        ));
        usleep(500);

        $msg = $broker->get('test_broker_simple.nack');

        $broker->nack($msg);
        $broker->disconnect();

        $this->assertQueueSize(0, 'test_broker_simple.nack');
    }

    public function testNackAndRequeue()
    {
        $exchange = $this->createExchange('test_broker_simple.nack_and_requeue');

        $queue = $this->createQueue('test_broker_simple.nack_and_requeue');
        $queue->purge();
        $queue->bind($exchange->getName(), 'test_broker_simple.nack_and_requeue');

        $broker = $this->createBroker(array($queue), array($exchange));

        $this->emptyQueue('test_broker_simple.nack_and_requeue');

        $broker->publish('test_broker_simple.nack_and_requeue', 'payload-42', array(
            'exchange' => $exchange->getName(),
        ));
        usleep(500);

        $msg = $broker->get('test_broker_simple.nack_and_requeue');

        $broker->nack($msg, \AMQP_REQUEUE);
        $broker->disconnect();

        $this->assertQueueSize(1, 'test_broker_simple.nack_and_requeue');
        $this->assertNextMessageBody('payload-42', 'test_broker_simple.nack_and_requeue');
    }

    private function createBroker(array $queues = array(), $exchanges = array())
    {
        return SimpleBroker::createWithDsn(getenv('AMQP_DSN'), $queues, $exchanges);
    }
}
