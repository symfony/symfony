<?php

namespace Symfony\Component\Worker\Tests\Router;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Worker\Consumer\ConsumerInterface;
use Symfony\Component\Worker\MessageCollection;
use Symfony\Component\Worker\MessageFetcher\InMemoryMessageFetcher;
use Symfony\Component\Worker\Router\DirectRouter;
use Symfony\Component\Worker\Router\RoundRobinRouter;

class RoundRobinRouterTest extends TestCase
{
    public function testConsumeEverythingInARow()
    {
        $fetcher1 = new InMemoryMessageFetcher(array('A', 'B'));
        $consumer1 = new ConsumerMock();
        $router1 = new DirectRouter($fetcher1, $consumer1);

        $fetcher2 = new InMemoryMessageFetcher(array('D', 'E'));
        $consumer2 = new ConsumerMock();
        $router2 = new DirectRouter($fetcher2, $consumer2);

        $router = new RoundRobinRouter(array($router1, $router2), true);

        $messageCollection = $router->fetchMessages();
        $this->assertSame(array('A'), iterator_to_array($messageCollection));
        $router->consume($messageCollection);
        $this->assertSame(array('A'), $consumer1->messages);

        $messageCollection = $router->fetchMessages();
        $this->assertSame(array('B'), iterator_to_array($messageCollection));
        $router->consume($messageCollection);
        $this->assertSame(array('A', 'B'), $consumer1->messages);

        $messageCollection = $router->fetchMessages();
        $this->assertSame(array('D'), iterator_to_array($messageCollection));
        $router->consume($messageCollection);
        $this->assertSame(array('D'), $consumer2->messages);

        $messageCollection = $router->fetchMessages();
        $this->assertSame(array('E'), iterator_to_array($messageCollection));
        $router->consume($messageCollection);
        $this->assertSame(array('D', 'E'), $consumer2->messages);

        $messageCollection = $router->fetchMessages();
        $this->assertSame(false, $messageCollection);
    }

    public function testConsumeEverythingInSequence()
    {
        $fetcher1 = new InMemoryMessageFetcher(array('A', false, 'B'));
        $consumer1 = new ConsumerMock();
        $router1 = new DirectRouter($fetcher1, $consumer1);

        $fetcher2 = new InMemoryMessageFetcher(array('D', false, 'E'));
        $consumer2 = new ConsumerMock();
        $router2 = new DirectRouter($fetcher2, $consumer2);

        $router = new RoundRobinRouter(array($router1, $router2), true);

        $messageCollection = $router->fetchMessages();
        $this->assertSame(array('A'), iterator_to_array($messageCollection));
        $router->consume($messageCollection);
        $this->assertSame(array('A'), $consumer1->messages);

        $messageCollection = $router->fetchMessages();
        $this->assertSame(array('D'), iterator_to_array($messageCollection));
        $router->consume($messageCollection);
        $this->assertSame(array('D'), $consumer2->messages);

        $messageCollection = $router->fetchMessages();
        $this->assertSame(array('B'), iterator_to_array($messageCollection));
        $router->consume($messageCollection);
        $this->assertSame(array('A', 'B'), $consumer1->messages);

        $messageCollection = $router->fetchMessages();
        $this->assertSame(array('E'), iterator_to_array($messageCollection));
        $router->consume($messageCollection);
        $this->assertSame(array('D', 'E'), $consumer2->messages);

        $messageCollection = $router->fetchMessages();
        $this->assertSame(false, $messageCollection);
    }

    public function testConsumeInSequence()
    {
        $fetcher1 = new InMemoryMessageFetcher(array('A', false, 'B'));
        $consumer1 = new ConsumerMock();
        $router1 = new DirectRouter($fetcher1, $consumer1);

        $fetcher2 = new InMemoryMessageFetcher(array('D', false, 'E'));
        $consumer2 = new ConsumerMock();
        $router2 = new DirectRouter($fetcher2, $consumer2);

        $router = new RoundRobinRouter(array($router1, $router2), false);

        $messageCollection = $router->fetchMessages();
        $this->assertSame(array('A'), iterator_to_array($messageCollection));
        $router->consume($messageCollection);
        $this->assertSame(array('A'), $consumer1->messages);

        $messageCollection = $router->fetchMessages();
        $this->assertSame(array('D'), iterator_to_array($messageCollection));
        $router->consume($messageCollection);
        $this->assertSame(array('D'), $consumer2->messages);

        // Both message fetch return false
        $messageCollection = $router->fetchMessages();
        $this->assertFalse($messageCollection);

        $messageCollection = $router->fetchMessages();
        $this->assertSame(array('B'), iterator_to_array($messageCollection));
        $router->consume($messageCollection);
        $this->assertSame(array('A', 'B'), $consumer1->messages);

        $messageCollection = $router->fetchMessages();
        $this->assertSame(array('E'), iterator_to_array($messageCollection));
        $router->consume($messageCollection);
        $this->assertSame(array('D', 'E'), $consumer2->messages);

        $messageCollection = $router->fetchMessages();
        $this->assertSame(false, $messageCollection);
    }
}

class ConsumerMock implements ConsumerInterface
{
    public $messages = array();

    public function consume(MessageCollection $messageCollection)
    {
        foreach ($messageCollection as $message) {
            $this->messages[] = $message;
        }
    }
}
