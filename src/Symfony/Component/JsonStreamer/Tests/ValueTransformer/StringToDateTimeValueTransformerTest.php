<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests\ValueTransformer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonStreamer\Exception\InvalidArgumentException;
use Symfony\Component\JsonStreamer\ValueTransformer\StringToDateTimeValueTransformer;

class StringToDateTimeValueTransformerTest extends TestCase
{
    public function testTransform()
    {
        $valueTransformer = new StringToDateTimeValueTransformer();

        $this->assertEquals(
            new \DateTimeImmutable('2023-07-26'),
            $valueTransformer->transform('2023-07-26', []),
        );

        $this->assertEquals(
            (new \DateTimeImmutable('2023-07-26'))->setTime(0, 0),
            $valueTransformer->transform('26/07/2023 00:00:00', [StringToDateTimeValueTransformer::FORMAT_KEY => 'd/m/Y H:i:s']),
        );
    }

    public function testTransformThrowWhenInvalidJsonValue()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The JSON value is either not an string, or an empty string, or null; you should pass a string that can be parsed with the passed format or a valid DateTime string.');

        (new StringToDateTimeValueTransformer())->transform(true, []);
    }

    public function testTransformThrowWhenInvalidDateTimeString()
    {
        $valueTransformer = new StringToDateTimeValueTransformer();

        try {
            $valueTransformer->transform('0', []);
            $this->fail(\sprintf('A "%s" exception must have been thrown.', InvalidArgumentException::class));
        } catch (InvalidArgumentException $e) {
            $this->assertEquals("Parsing datetime string \"0\" resulted in 1 errors: \nat position 0: Unexpected character", $e->getMessage());
        }

        try {
            $valueTransformer->transform('0', [StringToDateTimeValueTransformer::FORMAT_KEY => 'Y-m-d']);
            $this->fail(\sprintf('A "%s" exception must have been thrown.', InvalidArgumentException::class));
        } catch (InvalidArgumentException $e) {
            $this->assertEquals("Parsing datetime string \"0\" using format \"Y-m-d\" resulted in 1 errors: \nat position 1: Not enough data available to satisfy format", $e->getMessage());
        }
    }
}
