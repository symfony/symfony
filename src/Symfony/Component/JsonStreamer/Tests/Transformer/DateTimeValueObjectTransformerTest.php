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
use Symfony\Component\JsonStreamer\Transformer\DateTimeValueObjectTransformer;

class DateTimeValueObjectTransformerTest extends TestCase
{
    public function testTransform()
    {
        $transformer = new DateTimeValueObjectTransformer();

        $this->assertSame(
            '2023-07-26T00:00:00+00:00',
            $transformer->transform(new \DateTimeImmutable('2023-07-26', new \DateTimeZone('UTC')), []),
        );

        $this->assertSame(
            '26/07/2023 00:00:00',
            $transformer->transform((new \DateTimeImmutable('2023-07-26', new \DateTimeZone('UTC')))->setTime(0, 0), [DateTimeValueObjectTransformer::FORMAT_KEY => 'd/m/Y H:i:s']),
        );
    }

    public function testTransformWithTimezone()
    {
        $transformer = new DateTimeValueObjectTransformer();

        $this->assertSame(
            '2016-12-01T09:00:00+09:00',
            $transformer->transform(new \DateTimeImmutable('2016/12/01', new \DateTimeZone('UTC')), [
                DateTimeValueObjectTransformer::TIMEZONE_KEY => new \DateTimeZone('Asia/Tokyo'),
            ]),
        );

        $this->assertSame(
            '2016-12-01T00:00:00+09:00',
            $transformer->transform(new \DateTimeImmutable('2016/12/01', new \DateTimeZone('Asia/Tokyo')), [
                DateTimeValueObjectTransformer::TIMEZONE_KEY => new \DateTimeZone('Asia/Tokyo'),
            ]),
        );

        $this->assertSame(
            '2016-12-01T09:00:00+09:00',
            $transformer->transform(new \DateTimeImmutable('2016/12/01', new \DateTimeZone('UTC')), [
                DateTimeValueObjectTransformer::TIMEZONE_KEY => 'Asia/Tokyo',
            ]),
        );
    }

    public function testTransformThrowWhenInvalidNativeValue()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The native value must implement the "\DateTimeInterface".');

        (new DateTimeValueObjectTransformer())->transform(new \stdClass(), []);
    }

    public function testReverseTransform()
    {
        $transformer = new DateTimeValueObjectTransformer();

        $this->assertEquals(
            new \DateTimeImmutable('2023-07-26'),
            $transformer->reverseTransform('2023-07-26', []),
        );

        $this->assertEquals(
            (new \DateTimeImmutable('2023-07-26'))->setTime(0, 0),
            $transformer->reverseTransform('26/07/2023 00:00:00', [DateTimeValueObjectTransformer::FORMAT_KEY => 'd/m/Y H:i:s']),
        );
    }

    public function testReverseTransformWithTimezone()
    {
        $transformer = new DateTimeValueObjectTransformer();

        $this->assertEquals(
            new \DateTimeImmutable('2016/12/01 17:35:00', new \DateTimeZone('Asia/Tokyo')),
            $transformer->reverseTransform('2016/12/01 17:35:00', [
                DateTimeValueObjectTransformer::FORMAT_KEY => 'Y/m/d H:i:s',
                DateTimeValueObjectTransformer::TIMEZONE_KEY => new \DateTimeZone('Asia/Tokyo'),
            ]),
        );

        $this->assertEquals(
            new \DateTimeImmutable('2016/12/01 17:35:00', new \DateTimeZone('Asia/Tokyo')),
            $transformer->reverseTransform('2016/12/01 17:35:00', [
                DateTimeValueObjectTransformer::FORMAT_KEY => 'Y/m/d H:i:s',
                DateTimeValueObjectTransformer::TIMEZONE_KEY => 'Asia/Tokyo',
            ]),
        );

        $this->assertEquals(
            new \DateTimeImmutable('2016/12/01 17:35:00', new \DateTimeZone('Asia/Tokyo')),
            $transformer->reverseTransform('2016/12/01 17:35:00', [
                DateTimeValueObjectTransformer::TIMEZONE_KEY => new \DateTimeZone('Asia/Tokyo'),
            ]),
        );
    }

    public function testReverseTransformThrowWhenInvalidJsonValue()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The JSON value is either not an string, or an empty string; you should pass a string that can be parsed with the passed format or a valid DateTime string.');

        (new DateTimeValueObjectTransformer())->reverseTransform('', []);
    }

    public function testReverseTransformThrowWhenInvalidDateTimeString()
    {
        $valueTransformer = new DateTimeValueObjectTransformer();

        try {
            $valueTransformer->reverseTransform('0', []);
            $this->fail(\sprintf('A "%s" exception must have been thrown.', InvalidArgumentException::class));
        } catch (InvalidArgumentException $e) {
            $this->assertEquals("Parsing datetime string \"0\" resulted in 1 errors: \nat position 0: Unexpected character", $e->getMessage());
        }

        try {
            $valueTransformer->reverseTransform('0', [DateTimeValueObjectTransformer::FORMAT_KEY => 'Y-m-d']);
            $this->fail(\sprintf('A "%s" exception must have been thrown.', InvalidArgumentException::class));
        } catch (InvalidArgumentException $e) {
            $this->assertEquals("Parsing datetime string \"0\" using format \"Y-m-d\" resulted in 1 errors: \nat position 1: Not enough data available to satisfy format", $e->getMessage());
        }
    }
}
