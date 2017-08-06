<?php

namespace Symfony\Component\Worker\Tests\MessageFetcher;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Worker\MessageCollection;
use Symfony\Component\Worker\MessageFetcher\InMemoryMessageFetcher;

class InMemoryMessageFetcherTest extends TestCase
{
    public function test()
    {
        $fetcher = new InMemoryMessageFetcher(array('A', false, 'C'));

        $collection = $fetcher->fetchMessages();
        $this->assertInstanceOf(MessageCollection::class, $collection);
        $this->assertSame(array('A'), iterator_to_array($collection));

        $collection = $fetcher->fetchMessages();
        $this->assertFalse($collection);

        $collection = $fetcher->fetchMessages();
        $this->assertInstanceOf(MessageCollection::class, $collection);
        $this->assertSame(array('C'), iterator_to_array($collection));

        $collection = $fetcher->fetchMessages();
        $this->assertFalse($collection);

        $fetcher->queueMessage('D');

        $collection = $fetcher->fetchMessages();
        $this->assertInstanceOf(MessageCollection::class, $collection);
        $this->assertSame(array('D'), iterator_to_array($collection));

        $collection = $fetcher->fetchMessages();
        $this->assertFalse($collection);
    }
}
