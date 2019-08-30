<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Util;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Util\DateIntervalComparisonHelper;

final class DateIntervalComparisonHelperTest extends TestCase
{
    /**
     * @dataProvider supportsProvider
     */
    public function testSupports(bool $expected, $value, $comparedValue)
    {
        $this->assertSame($expected, DateIntervalComparisonHelper::supports($value, $comparedValue));
    }

    public function supportsProvider()
    {
        return [
            [false, 'foo', 'bar'],
            [false, $dateInterval = new \DateInterval('PT30S'), new \stdClass()],
            [false, $dateInterval, $dateInterval],
            [false, $dateInterval, 2],
            [true, $dateInterval, 'foo'],
            [true, $dateInterval, new \DateInterval('PT2S')],
            [true, $dateInterval, 0],
        ];
    }

    public function testConvertValue()
    {
        $this->assertEquals(new \DateTimeImmutable('@0'), DateIntervalComparisonHelper::convertValue(new \DateTimeImmutable('@0-30 seconds'), new \DateInterval('PT30S')));
    }

    public function testConvertComparedValueWhenTheStringComparedValueIsInvalid()
    {
        $this->expectException(\InvalidArgumentException::class);

        DateIntervalComparisonHelper::convertComparedValue(new \DateTimeImmutable(), 'foo');
    }

    /**
     * @dataProvider convertComparedValueProvider
     */
    public function testConvertComparedValue($expected, \DateTimeImmutable $reference, $comparedValue, bool $strict = false)
    {
        $convertedComparedValue = DateIntervalComparisonHelper::convertComparedValue($reference, $comparedValue);

        if (!$strict) {
            $this->assertEquals($expected, $convertedComparedValue);
        } else {
            $this->assertSame($expected, $convertedComparedValue);
        }
    }

    public function convertComparedValueProvider()
    {
        return [
            [new \DateTimeImmutable('@0-45 minutes'), new \DateTimeImmutable('@0'), '-45 minutes'],
            [new \DateTimeImmutable('@0'), new \DateTimeImmutable('@0-1 year'), new \DateInterval('P1Y')],
            [$reference = new \DateTimeImmutable(), $reference, 0],
        ];
    }
}
