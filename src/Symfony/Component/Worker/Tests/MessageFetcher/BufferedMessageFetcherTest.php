<?php

namespace Symfony\Component\Worker\Tests\MessageFetcher;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Worker\MessageCollection;
use Symfony\Component\Worker\MessageFetcher\InMemoryMessageFetcher;
use Symfony\Component\Worker\MessageFetcher\BufferedMessageFetcher;

/**
 * @group time-sensitive
 */
class BufferedMessageFetcherTest extends TestCase
{
    public function test()
    {
        $fetcher = new InMemoryMessageFetcher(range(1, 5));
        $buffer = new BufferedMessageFetcher($fetcher, array(
            'max_messages' => 2,
        ));

        $collection = $buffer->fetchMessages();
        $this->assertInstanceOf(MessageCollection::class, $collection);
        $this->assertSame(array(1, 2), iterator_to_array($collection));

        $collection = $buffer->fetchMessages();
        $this->assertInstanceOf(MessageCollection::class, $collection);
        $this->assertSame(array(3, 4), iterator_to_array($collection));

        // Wait for another message
        $collection = $buffer->fetchMessages();
        $this->assertFalse($collection);

        sleep(10);

        $collection = $buffer->fetchMessages();
        $this->assertInstanceOf(MessageCollection::class, $collection);
        $this->assertSame(array(5), iterator_to_array($collection));

        $collection = $buffer->fetchMessages();
        $this->assertFalse($collection);
    }
}
