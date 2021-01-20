<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\DateIntervalFormatter;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Intl\DateIntervalFormatter\DateIntervalFormatter;

class DateIntervalFormatterTest extends TestCase
{
    /**
     * @dataProvider provideIntervals
     */
    public function testFormatInterval($interval, string $expected, int $precision = 0)
    {
        $formatter = new DateIntervalFormatter();
        $this->assertSame($expected, $formatter->formatInterval($interval, $precision));
    }

    public function provideIntervals(): \Generator
    {
        yield [new \DateInterval('PT0S'), 'now'];
        yield [new \DateInterval('PT1S'), '1 second'];
        yield [new \DateInterval('PT10S'), '10 seconds'];
        yield [new \DateInterval('PT1M10S'), '1 minute and 10 seconds'];
        yield [new \DateInterval('PT10M10S'), '10 minutes and 10 seconds'];
        yield [new \DateInterval('PT1H10M10S'), '1 hour, 10 minutes and 10 seconds'];
        yield [new \DateInterval('PT10H10M10S'), '10 hours, 10 minutes and 10 seconds'];
        yield [new \DateInterval('P1DT10H10M10S'), '1 day, 10 hours, 10 minutes and 10 seconds'];
        yield [new \DateInterval('P10DT10H10M10S'), '10 days, 10 hours, 10 minutes and 10 seconds'];
        yield [new \DateInterval('P1M10DT10H10M10S'), '1 month, 10 days, 10 hours, 10 minutes and 10 seconds'];
        yield [new \DateInterval('P10M10DT10H10M10S'), '10 months, 10 days, 10 hours, 10 minutes and 10 seconds'];
        yield [new \DateInterval('P1Y10M10DT10H10M10S'), '1 year, 10 months, 10 days, 10 hours, 10 minutes and 10 seconds'];
        yield [new \DateInterval('P10Y10M10DT10H10M10S'), '10 years, 10 months, 10 days, 10 hours, 10 minutes and 10 seconds'];
        yield [new \DateInterval('P10Y10M10DT10H10M10S'), '10 years, 10 months and 10 days', 3];
        yield [new \DateInterval('P1Y1DT1S'), '1 year and 1 day', 3];
        yield ['PT1M10S', '1 minute and 10 seconds'];
    }

    /**
     * @dataProvider provideDates
     */
    public function testFormatDates($dateTime, $currentDateTime, string $expected, int $precision = 0)
    {
        $formatter = new DateIntervalFormatter();
        $this->assertSame($expected, $formatter->formatRelative($dateTime, $currentDateTime, $precision));
    }

    public function provideDates(): \Generator
    {
        yield [new \DateTime('2021-01-01'), new \DateTime('2020-01-01'), 'in 1 year'];
        yield [new \DateTime('2020-01-01'), new \DateTime('2021-01-01'), '1 year ago'];
        yield [new \DateTime('2021-01-01'), new \DateTime('2021-01-01'), 'now'];
        yield [new \DateTime('2020-01-01'), new \DateTime('2021-02-02'), '1 year and 1 month ago', 2];
        yield ['2021-01-01', new \DateTime('2020-01-01'), 'in 1 year'];
        yield ['2021-01-01', '2020-01-01', 'in 1 year'];
        yield ['-1 hour', null, '1 hour ago'];
    }
}
