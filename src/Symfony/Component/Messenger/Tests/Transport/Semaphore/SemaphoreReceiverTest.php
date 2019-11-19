<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\Semaphore;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Semaphore\Connection;
use Symfony\Component\Messenger\Transport\Semaphore\SemaphoreEnvelope;
use Symfony\Component\Messenger\Transport\Semaphore\SemaphoreReceiver;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Serializer as SerializerComponent;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class SemaphoreReceiverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (false === \extension_loaded('sysvmsg')) {
            $this->markTestSkipped('Semaphore extension (sysvmsg) is required.');
        }
    }

    public function testItReturnsTheDecodedMessageToTheHandler()
    {
        $serializer = new Serializer(
                new SerializerComponent\Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()])
        );

        $semaphoreEnvelope = $this->createSemaphoreEnvelope();
        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
        $connection->method('get')->willReturn($semaphoreEnvelope);

        $receiver = new SemaphoreReceiver($connection, $serializer);
        $actualEnvelopes = iterator_to_array($receiver->get());

        $this->assertCount(1, $actualEnvelopes);
        $this->assertEquals(new DummyMessage('Hi'), $actualEnvelopes[0]->getMessage());
    }

    private function createSemaphoreEnvelope(): SemaphoreEnvelope
    {
        $envelope = $this->getMockBuilder(SemaphoreEnvelope::class)->disableOriginalConstructor()->getMock();
        $envelope->method('getBody')->willReturn('{"message": "Hi"}');
        $envelope->method('getHeaders')->willReturn([
                'type' => DummyMessage::class,
        ]);

        return $envelope;
    }
}
