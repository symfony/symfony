<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\Serialization;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\ClaimCheckNotFoundException;
use Symfony\Component\Messenger\Exception\ClaimCheckStorageException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Serialization\ClaimCheckSerializer;
use Symfony\Component\Messenger\Transport\Serialization\MessageTypeAwareSerializerInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\Serialization\SigningSerializer;

class ClaimCheckSerializerTest extends TestCase
{
    public function testSmallEncodedEnvelopeIsReturnedUnchanged()
    {
        $inner = new PhpSerializer();
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects($this->never())->method('getItem');
        $serializer = new ClaimCheckSerializer($inner, $pool, 10000);
        $envelope = new Envelope(new DummyMessage('hello'));

        $this->assertSame($inner->encode($envelope), $serializer->encode($envelope));
    }

    public function testLargeEncodedEnvelopeIsStoredAndRestored()
    {
        $values = [];
        $serializer = new ClaimCheckSerializer(new PhpSerializer(), $this->createCachePool($values), 512);
        $envelope = new Envelope(new DummyMessage(str_repeat('a', 1000)));

        $encoded = $serializer->encode($envelope);

        $this->assertSame('1', $encoded['headers']['X-Symfony-Messenger-Claim-Check'] ?? null);
        $claim = json_decode($encoded['body'], true, flags: \JSON_THROW_ON_ERROR);
        $this->assertNotEmpty($values[$claim['id']]);
        $this->assertEquals($envelope, $serializer->decode($encoded));
    }

    public function testTransportMetadataIsForwardedToTheInnerSerializer()
    {
        $values = [];
        $phpSerializer = new PhpSerializer();
        $envelope = new Envelope(new DummyMessage(str_repeat('a', 1000)));
        $inner = $this->createMock(SerializerInterface::class);
        $inner->method('encode')->willReturn($phpSerializer->encode($envelope));
        $inner->expects($this->once())->method('decode')
            ->with($this->callback(static fn (array $encodedEnvelope): bool => ['routing_key' => 'urgent'] === $encodedEnvelope['extra']))
            ->willReturn($envelope);
        $serializer = new ClaimCheckSerializer($inner, $this->createCachePool($values), 512);
        $encoded = $serializer->encode($envelope);
        $encoded['extra'] = ['routing_key' => 'urgent'];

        $serializer->decode($encoded);
    }

    public function testMissingClaimProducesDecodingFailure()
    {
        $values = [];
        $serializer = new ClaimCheckSerializer(new PhpSerializer(), $this->createCachePool($values), 512);
        $encoded = $serializer->encode(new Envelope(new DummyMessage(str_repeat('a', 1000))));
        $claim = json_decode($encoded['body'], true, flags: \JSON_THROW_ON_ERROR);
        unset($values[$claim['id']]);

        $decoded = $serializer->decode($encoded);

        $this->assertInstanceOf(MessageDecodingFailedException::class, $decoded->getMessage());
        $this->assertSame($encoded, $decoded->getMessage()->encodedEnvelope);
        $this->assertInstanceOf(ClaimCheckNotFoundException::class, $decoded->getMessage()->getPrevious());
    }

    public function testChangedClaimProducesDecodingFailure()
    {
        $values = [];
        $serializer = new ClaimCheckSerializer(new PhpSerializer(), $this->createCachePool($values), 512);
        $encoded = $serializer->encode(new Envelope(new DummyMessage(str_repeat('a', 1000))));
        $claim = json_decode($encoded['body'], true, flags: \JSON_THROW_ON_ERROR);
        $values[$claim['id']] = 'changed';

        $decoded = $serializer->decode($encoded);

        $this->assertInstanceOf(MessageDecodingFailedException::class, $decoded->getMessage());
        $this->assertStringContainsString('integrity', $decoded->getMessage()->getMessage());
    }

    public function testMessageTypeDoesNotRetrieveClaim()
    {
        $values = [];
        $serializer = new ClaimCheckSerializer(new PhpSerializer(), $this->createCachePool($values), 512);
        $encoded = $serializer->encode(new Envelope(new DummyMessage(str_repeat('a', 1000))));
        $claim = json_decode($encoded['body'], true, flags: \JSON_THROW_ON_ERROR);
        unset($values[$claim['id']]);

        $this->assertInstanceOf(MessageTypeAwareSerializerInterface::class, $serializer);
        $this->assertSame(DummyMessage::class, $serializer->getMessageType($encoded));
    }

    public function testMessageTypeIsDelegatedForRegularEnvelope()
    {
        $serializer = new ClaimCheckSerializer(new PhpSerializer(), $this->createStub(CacheItemPoolInterface::class), 10000);
        $encoded = $serializer->encode(new Envelope(new DummyMessage('hello')));

        $this->assertSame(DummyMessage::class, $serializer->getMessageType($encoded));
    }

    public function testComposesWithSigningSerializer()
    {
        $values = [];
        $serializer = new ClaimCheckSerializer(
            new SigningSerializer(new PhpSerializer(), 'secret', [DummyMessage::class]),
            $this->createCachePool($values),
            512,
        );
        $envelope = new Envelope(new DummyMessage(str_repeat('a', 1000)));

        $encoded = $serializer->encode($envelope);
        $claim = json_decode($encoded['body'], true, flags: \JSON_THROW_ON_ERROR);

        $this->assertArrayNotHasKey('Body-Sign', $encoded['headers']);
        $this->assertStringContainsString('Body-Sign', $values[$claim['id']]);
        $this->assertEquals($envelope, $serializer->decode($encoded));
    }

    public function testReferenceThatExceedsMaximumSizeIsRemoved()
    {
        $values = [];
        $serializer = new ClaimCheckSerializer(new PhpSerializer(), $this->createCachePool($values), 1);

        try {
            $serializer->encode(new Envelope(new DummyMessage(str_repeat('a', 1000))));
            $this->fail('An oversized claim check reference was returned.');
        } catch (ClaimCheckStorageException $e) {
            $this->assertSame([], $values);
            $this->assertStringContainsString('reference is larger', $e->getMessage());
        }
    }

    public function testCacheSaveFailureThrowsStorageException()
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->never())->method('isHit');
        $item->method('set')->willReturnSelf();
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturn($item);
        $pool->method('save')->willReturn(false);
        $serializer = new ClaimCheckSerializer(new PhpSerializer(), $pool, 512);

        $this->expectException(ClaimCheckStorageException::class);
        $this->expectExceptionMessage('Unable to store the claim check');

        $serializer->encode(new Envelope(new DummyMessage(str_repeat('a', 1000))));
    }

    public function testReferenceSizeErrorIsReportedWhenCleanupFails()
    {
        $item = $this->createStub(CacheItemInterface::class);
        $item->method('set')->willReturnSelf();
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturn($item);
        $pool->method('save')->willReturn(true);
        $pool->method('deleteItem')->willThrowException(new \RuntimeException('Cleanup failed.'));
        $serializer = new ClaimCheckSerializer(new PhpSerializer(), $pool, 1);

        $this->expectException(ClaimCheckStorageException::class);
        $this->expectExceptionMessage('reference is larger');

        $serializer->encode(new Envelope(new DummyMessage(str_repeat('a', 1000))));
    }

    public function testReferenceSizeErrorIsReportedWhenCleanupReturnsFalse()
    {
        $item = $this->createStub(CacheItemInterface::class);
        $item->method('set')->willReturnSelf();
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturn($item);
        $pool->method('save')->willReturn(true);
        $pool->method('deleteItem')->willReturn(false);
        $serializer = new ClaimCheckSerializer(new PhpSerializer(), $pool, 1);

        $this->expectException(ClaimCheckStorageException::class);
        $this->expectExceptionMessage('reference is larger');

        $serializer->encode(new Envelope(new DummyMessage(str_repeat('a', 1000))));
    }

    public function testRejectsInvalidMaximumSize()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('maximum size must be greater than zero');

        new ClaimCheckSerializer(new PhpSerializer(), $this->createStub(CacheItemPoolInterface::class), 0);
    }

    /** @param array<string, mixed> $values */
    private function createCachePool(array &$values): CacheItemPoolInterface
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturnCallback(function (string $id) use (&$values): CacheItemInterface {
            $item = $this->createStub(CacheItemInterface::class);
            $value = $values[$id] ?? null;
            $item->method('getKey')->willReturn($id);
            $item->method('get')->willReturnCallback(static function () use (&$value): mixed {
                return $value;
            });
            $item->method('isHit')->willReturnCallback(static fn (): bool => null !== $value);
            $item->method('set')->willReturnCallback(static function (mixed $newValue) use (&$value, $item): CacheItemInterface {
                $value = $newValue;

                return $item;
            });

            return $item;
        });
        $pool->method('save')->willReturnCallback(static function (CacheItemInterface $item) use (&$values): bool {
            $values[$item->getKey()] = $item->get();

            return true;
        });
        $pool->method('deleteItem')->willReturnCallback(static function (string $id) use (&$values): bool {
            unset($values[$id]);

            return true;
        });

        return $pool;
    }
}
