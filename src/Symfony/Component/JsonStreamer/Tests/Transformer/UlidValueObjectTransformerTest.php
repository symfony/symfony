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

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonStreamer\Exception\InvalidArgumentException;
use Symfony\Component\JsonStreamer\Transformer\UlidValueObjectTransformer;
use Symfony\Component\Uid\Ulid;

class UlidValueObjectTransformerTest extends TestCase
{
    private const ULID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    public function testTransform()
    {
        $transformer = new UlidValueObjectTransformer();
        $ulid = Ulid::fromString(self::ULID);

        $this->assertSame(self::ULID, $transformer->transform($ulid));
        $this->assertSame((string) $ulid, $transformer->transform($ulid, [UlidValueObjectTransformer::FORMAT_KEY => UlidValueObjectTransformer::FORMAT_CANONICAL]));
        $this->assertSame($ulid->toRfc4122(), $transformer->transform($ulid, [UlidValueObjectTransformer::FORMAT_KEY => UlidValueObjectTransformer::FORMAT_RFC4122]));
        $this->assertSame($ulid->toBase58(), $transformer->transform($ulid, [UlidValueObjectTransformer::FORMAT_KEY => UlidValueObjectTransformer::FORMAT_BASE58]));
        $this->assertSame($ulid->toBase32(), $transformer->transform($ulid, [UlidValueObjectTransformer::FORMAT_KEY => UlidValueObjectTransformer::FORMAT_BASE32]));
    }

    public function testTransformThrowWhenInvalidNativeValue()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The native value must be an instance of "Symfony\Component\Uid\Ulid".');

        (new UlidValueObjectTransformer())->transform(new \stdClass());
    }

    public function testTransformThrowWhenInvalidFormat()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "invalid" format is not valid.');

        (new UlidValueObjectTransformer())->transform(Ulid::fromString(self::ULID), [UlidValueObjectTransformer::FORMAT_KEY => 'invalid']);
    }

    public function testReverseTransform()
    {
        $transformer = new UlidValueObjectTransformer();
        $ulid = Ulid::fromString(self::ULID);

        $this->assertInstanceOf(Ulid::class, $transformer->reverseTransform(self::ULID));
        $this->assertEquals($ulid, $transformer->reverseTransform(self::ULID));
    }

    public function testReverseTransformThrowWhenInvalidJsonValue()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The JSON value must be a string, "int" given.');

        (new UlidValueObjectTransformer())->reverseTransform(42);
    }

    public function testReverseTransformThrowWhenInvalidUlidString()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The string "not a ulid" is not a valid ULID.');

        (new UlidValueObjectTransformer())->reverseTransform('not a ulid');
    }
}
