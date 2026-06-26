<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests\Transformer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonStreamer\Exception\InvalidArgumentException;
use Symfony\Component\JsonStreamer\Transformer\UuidValueObjectTransformer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV4;
use Symfony\Component\Uid\UuidV7;

class UuidValueObjectTransformerTest extends TestCase
{
    private const UUID = 'a7613e0a-5986-4f29-b3f8-3b6f8e5e6b40';

    public function testTransform()
    {
        $transformer = new UuidValueObjectTransformer();
        $uuid = Uuid::fromString(self::UUID);

        $this->assertSame(self::UUID, $transformer->transform($uuid));
        $this->assertSame((string) $uuid, $transformer->transform($uuid, [UuidValueObjectTransformer::FORMAT_KEY => UuidValueObjectTransformer::FORMAT_CANONICAL]));
        $this->assertSame($uuid->toRfc4122(), $transformer->transform($uuid, [UuidValueObjectTransformer::FORMAT_KEY => UuidValueObjectTransformer::FORMAT_RFC4122]));
        $this->assertSame($uuid->toBase58(), $transformer->transform($uuid, [UuidValueObjectTransformer::FORMAT_KEY => UuidValueObjectTransformer::FORMAT_BASE58]));
        $this->assertSame($uuid->toBase32(), $transformer->transform($uuid, [UuidValueObjectTransformer::FORMAT_KEY => UuidValueObjectTransformer::FORMAT_BASE32]));
    }

    public function testTransformThrowWhenInvalidNativeValue()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The native value must be an instance of "Symfony\Component\Uid\Uuid".');

        (new UuidValueObjectTransformer())->transform(new \stdClass());
    }

    public function testTransformThrowWhenInvalidFormat()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "invalid" format is not valid.');

        (new UuidValueObjectTransformer())->transform(Uuid::fromString(self::UUID), [UuidValueObjectTransformer::FORMAT_KEY => 'invalid']);
    }

    /**
     * @param class-string<Uuid> $expectedClass
     */
    #[DataProvider('reverseTransformDataProvider')]
    public function testReverseTransform(string $expectedClass, string $string)
    {
        $transformer = new UuidValueObjectTransformer();

        $this->assertInstanceOf($expectedClass, $transformer->reverseTransform($string));
        $this->assertEquals(Uuid::fromString($string), $transformer->reverseTransform($string));
    }

    public static function reverseTransformDataProvider(): iterable
    {
        yield [UuidV4::class, 'a7613e0a-5986-4f29-b3f8-3b6f8e5e6b40'];
        yield [UuidV7::class, '018f7a5e-3c1e-7a4e-8b1a-2c3d4e5f6a7b'];
    }

    public function testReverseTransformThrowWhenInvalidJsonValue()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The JSON value must be a string, "int" given.');

        (new UuidValueObjectTransformer())->reverseTransform(42);
    }

    public function testReverseTransformThrowWhenInvalidUuidString()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The string "not a uuid" is not a valid UUID.');

        (new UuidValueObjectTransformer())->reverseTransform('not a uuid');
    }
}
