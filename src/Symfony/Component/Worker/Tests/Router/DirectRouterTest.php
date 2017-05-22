<?php

namespace Symfony\Component\Worker\Tests\Router;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Worker\Consumer\ConsumerInterface;
use Symfony\Component\Worker\MessageCollection;
use Symfony\Component\Worker\MessageFetcher\MessageFetcherInterface;
use Symfony\Component\Worker\Router\DirectRouter;

class DirectRouterTest extends TestCase
{
    public function testFetchMessage()
    {
        $messageFetcher = $this->createMock(MessageFetcherInterface::class);
        $messageFetcher->expects($this->once())->method('fetchMessages');
        $consumer = $this->createMock(ConsumerInterface::class);

        $router = new DirectRouter($messageFetcher, $consumer);

        $router->fetchMessages();
    }

    public function testConsume()
    {
        $messageCollection = new MessageCollection();

        $messageFetcher = $this->createMock(MessageFetcherInterface::class);
        $consumer = $this->createMock(ConsumerInterface::class);
        $consumer->expects($this->once())->method('consume')->with($messageCollection);

        $router = new DirectRouter($messageFetcher, $consumer);

        $router->consume($messageCollection);
    }
}
