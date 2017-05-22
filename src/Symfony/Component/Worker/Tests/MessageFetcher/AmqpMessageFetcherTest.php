<?php

namespace Symfony\Component\Worker\Tests\MessageFetcher;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Amqp\Broker;
use Symfony\Component\Worker\MessageCollection;
use Symfony\Component\Worker\MessageFetcher\AmqpMessageFetcher;

class AmqpMessageFetcherTest extends TestCase
{
    public function testWithAutoAckAndNoMessage()
    {
        $broker = $this->getMockBuilder(Broker::class)->disableOriginalConstructor()->getMock();
        $broker
            ->expects($this->once())
            ->method('get')
            ->with('queue', \AMQP_AUTOACK)
            ->willReturn(false)
        ;

        $messageFetcher = new AmqpMessageFetcher($broker, 'queue', true);

        $collection = $messageFetcher->fetchMessages();
        $this->assertFalse($collection);
    }

    public function testWithAutoAckAndOneMessage()
    {
        $broker = $this->getMockBuilder(Broker::class)->disableOriginalConstructor()->getMock();
        $broker
            ->expects($this->once())
            ->method('get')
            ->with('queue', \AMQP_AUTOACK)
            ->willReturn('A')
        ;

        $messageFetcher = new AmqpMessageFetcher($broker, 'queue', true);

        $collection = $messageFetcher->fetchMessages();
        $this->assertInstanceOf(MessageCollection::class, $collection);
        $this->assertSame(array('A'), iterator_to_array($collection));
    }

    public function testWithoutAutoAckAndNoMessage()
    {
        $broker = $this->getMockBuilder(Broker::class)->disableOriginalConstructor()->getMock();
        $broker
            ->expects($this->once())
            ->method('get')
            ->with('queue', \AMQP_NOPARAM)
            ->willReturn(false)
        ;

        $messageFetcher = new AmqpMessageFetcher($broker, 'queue', false);

        $collection = $messageFetcher->fetchMessages();
        $this->assertFalse($collection);
    }

    public function testWithoutAutoAckAndOneMessage()
    {
        $broker = $this->getMockBuilder(Broker::class)->disableOriginalConstructor()->getMock();
        $broker
            ->expects($this->once())
            ->method('get')
            ->with('queue', \AMQP_NOPARAM)
            ->willReturn('A')
        ;

        $messageFetcher = new AmqpMessageFetcher($broker, 'queue', false);

        $collection = $messageFetcher->fetchMessages();
        $this->assertInstanceOf(MessageCollection::class, $collection);
        $this->assertSame(array('A'), iterator_to_array($collection));
    }
}
