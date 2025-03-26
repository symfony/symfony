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
use Symfony\Component\JsonStreamer\ValueTransformer\DateTimeToStringValueTransformer;

class DateTimeToStringValueTransformerTest extends TestCase
{
    public function testTransform()
    {
        $valueTransformer = new DateTimeToStringValueTransformer();

        $this->assertSame(
            '2023-07-26T00:00:00+00:00',
            $valueTransformer->transform(new \DateTimeImmutable('2023-07-26', new \DateTimeZone('UTC')), []),
        );

        $this->assertSame(
            '26/07/2023 00:00:00',
            $valueTransformer->transform((new \DateTimeImmutable('2023-07-26', new \DateTimeZone('UTC')))->setTime(0, 0), [DateTimeToStringValueTransformer::FORMAT_KEY => 'd/m/Y H:i:s']),
        );
    }

    public function testTransformThrowWhenInvalidNativeValue()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The native value must implement the "\DateTimeInterface".');

        (new DateTimeToStringValueTransformer())->transform(true, []);
    }
}
