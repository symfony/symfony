<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Uid\Tests\Factory;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Exception\LogicException;
use Symfony\Component\Uid\Factory\MockUuidFactory;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV1;
use Symfony\Component\Uid\UuidV3;
use Symfony\Component\Uid\UuidV4;
use Symfony\Component\Uid\UuidV5;
use Symfony\Component\Uid\UuidV6;
use Symfony\Component\Uid\UuidV7;
use Symfony\Component\Uid\UuidV8;

final class MockUuidFactoryTest extends TestCase
{
    public function testCreateFromEmptySequence()
    {
        $factory = new MockUuidFactory();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No more UUIDs in sequence. You may need to add more UUIDs to the sequence or call reset() to start over.');

        $factory->v4();
    }

    public function testCreateFromStringSequence()
    {
        $factory = new MockUuidFactory([
            '550e8400-e29b-41d4-a716-446655440000',
            '6ba7b810-9dad-11d1-80b4-00c04fd430c8',
        ]);

        $uuid1 = $factory->v4();
        $uuid2 = $factory->v1();

        $this->assertInstanceOf(UuidV4::class, $uuid1);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', (string) $uuid1);

        $this->assertInstanceOf(UuidV1::class, $uuid2);
        $this->assertSame('6ba7b810-9dad-11d1-80b4-00c04fd430c8', (string) $uuid2);
    }

    public function testCreateFromUuidSequence()
    {
        $uuid1 = Uuid::fromString('550e8400-e29b-41d4-a716-446655440000');
        $uuid2 = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');

        $factory = new MockUuidFactory([$uuid1, $uuid2]);

        $result1 = $factory->v4();
        $result2 = $factory->v1();

        $this->assertSame($uuid1, $result1);
        $this->assertSame($uuid2, $result2);
    }

    public function testSetSequence()
    {
        $factory = new MockUuidFactory();

        $factory->setSequence([
            '550e8400-e29b-41d4-a716-446655440000',
            '6ba7b810-9dad-11d1-80b4-00c04fd430c8',
        ]);

        $uuid1 = $factory->v4();
        $uuid2 = $factory->v1();

        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', (string) $uuid1);
        $this->assertSame('6ba7b810-9dad-11d1-80b4-00c04fd430c8', (string) $uuid2);
    }

    public function testReset()
    {
        $factory = new MockUuidFactory([
            '550e8400-e29b-41d4-a716-446655440000',
            '6ba7b810-9dad-11d1-80b4-00c04fd430c8',
        ]);

        // Get first UUID
        $uuid1 = $factory->v4();
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', (string) $uuid1);

        // Reset and get first UUID again
        $factory->reset();
        $uuid2 = $factory->v4();
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', (string) $uuid2);
    }

    public function testRunsOutOfUuids()
    {
        $factory = new MockUuidFactory([
            '550e8400-e29b-41d4-a716-446655440000',
        ]);

        // First call should work
        $uuid = $factory->v4();
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', (string) $uuid);

        // Second call should fail
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No more UUIDs in sequence. You may need to add more UUIDs to the sequence or call reset() to start over.');

        $factory->v4();
    }

    public function testWrongUuidVersionThrowsException()
    {
        // UuidV1 UUID being requested as V4
        $factory = new MockUuidFactory([
            '6ba7b810-9dad-11d1-80b4-00c04fd430c8', // This is a V1 UUID
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected UUID of type "Symfony\Component\Uid\UuidV4", got "Symfony\Component\Uid\UuidV1".');

        $factory->v4();
    }

    public function testAllUuidVersions()
    {
        $factory = new MockUuidFactory([
            '6ba7b810-9dad-11d1-80b4-00c04fd430c8', // V1
            '6fa459ea-ee8a-3ca4-894e-db77e160355e', // V3
            '550e8400-e29b-41d4-a716-446655440000', // V4
            '886313e1-3b8a-5372-9b90-0c9aee199e5d', // V5
            '1ef21d2f-1207-6660-8000-0242ac120002', // V6
            '017f22e2-79b0-7cc3-98c4-dc0c0c07398f', // V7
            '12345678-1234-8234-1234-123456789abc', // V8
        ]);

        $v1 = $factory->v1();
        $v3 = $factory->v3();
        $v4 = $factory->v4();
        $v5 = $factory->v5();
        $v6 = $factory->v6();
        $v7 = $factory->v7();
        $v8 = $factory->v8();

        $this->assertInstanceOf(UuidV1::class, $v1);
        $this->assertInstanceOf(UuidV3::class, $v3);
        $this->assertInstanceOf(UuidV4::class, $v4);
        $this->assertInstanceOf(UuidV5::class, $v5);
        $this->assertInstanceOf(UuidV6::class, $v6);
        $this->assertInstanceOf(UuidV7::class, $v7);
        $this->assertInstanceOf(UuidV8::class, $v8);

        $this->assertSame('6ba7b810-9dad-11d1-80b4-00c04fd430c8', (string) $v1);
        $this->assertSame('6fa459ea-ee8a-3ca4-894e-db77e160355e', (string) $v3);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', (string) $v4);
        $this->assertSame('886313e1-3b8a-5372-9b90-0c9aee199e5d', (string) $v5);
        $this->assertSame('1ef21d2f-1207-6660-8000-0242ac120002', (string) $v6);
        $this->assertSame('017f22e2-79b0-7cc3-98c4-dc0c0c07398f', (string) $v7);
        $this->assertSame('12345678-1234-8234-1234-123456789abc', (string) $v8);
    }

    public function testInvalidSequenceItem()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Sequence must contain only strings or Uuid instances.');

        new MockUuidFactory([123]);
    }
}