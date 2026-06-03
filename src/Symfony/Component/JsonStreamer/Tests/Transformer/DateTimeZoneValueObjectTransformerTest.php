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
use Symfony\Component\JsonStreamer\Transformer\DateTimeZoneValueObjectTransformer;

class DateTimeZoneValueObjectTransformerTest extends TestCase
{
    public function testTransform()
    {
        $transformer = new DateTimeZoneValueObjectTransformer();

        $this->assertSame('UTC', $transformer->transform(new \DateTimeZone('UTC')));
        $this->assertSame('Asia/Tokyo', $transformer->transform(new \DateTimeZone('Asia/Tokyo')));
        $this->assertSame('Europe/Paris', $transformer->transform(new \DateTimeZone('Europe/Paris')));
    }

    public function testTransformThrowWhenInvalidNativeValue()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The native value must be an instance of "\DateTimeZone".');

        (new DateTimeZoneValueObjectTransformer())->transform(new \stdClass());
    }

    public function testReverseTransform()
    {
        $transformer = new DateTimeZoneValueObjectTransformer();

        $this->assertEquals(new \DateTimeZone('UTC'), $transformer->reverseTransform('UTC'));
        $this->assertEquals(new \DateTimeZone('Asia/Tokyo'), $transformer->reverseTransform('Asia/Tokyo'));
        $this->assertEquals(new \DateTimeZone('Europe/Paris'), $transformer->reverseTransform('Europe/Paris'));
    }

    public function testReverseTransformThrowWhenInvalidJsonValue()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The JSON value must be a string, "int" given.');

        (new DateTimeZoneValueObjectTransformer())->reverseTransform(42);
    }

    public function testReverseTransformThrowWhenInvalidTimezoneString()
    {
        $this->expectException(InvalidArgumentException::class);

        (new DateTimeZoneValueObjectTransformer())->reverseTransform('Jupiter/Europa');
    }
}
