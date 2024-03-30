<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Clock\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

class DatePointTest extends TestCase
{
    use ClockSensitiveTrait;

    public function testDatePoint()
    {
        self::mockTime('2010-01-28 15:00:00 UTC');

        $date = new DatePoint();
        $this->assertSame('2010-01-28 15:00:00 UTC', $date->format('Y-m-d H:i:s e'));

        $date = new DatePoint('+1 day Europe/Paris');
        $this->assertSame('2010-01-29 16:00:00 Europe/Paris', $date->format('Y-m-d H:i:s e'));

        $date = new DatePoint('2022-01-28 15:00:00 Europe/Paris');
        $this->assertSame('2022-01-28 15:00:00 Europe/Paris', $date->format('Y-m-d H:i:s e'));
    }

    public function testCreateFromFormat()
    {
        $date = DatePoint::createFromFormat('Y-m-d H:i:s', '2010-01-28 15:00:00');

        $this->assertInstanceOf(DatePoint::class, $date);
        $this->assertSame('2010-01-28 15:00:00', $date->format('Y-m-d H:i:s'));

        $this->expectException(\DateMalformedStringException::class);
        $this->expectExceptionMessage('A four digit year could not be found');
        DatePoint::createFromFormat('Y-m-d H:i:s', 'Bad Date');
    }

    public function testModify()
    {
        $date = new DatePoint('2010-01-28 15:00:00');
        $date = $date->modify('+1 day');

        $this->assertInstanceOf(DatePoint::class, $date);
        $this->assertSame('2010-01-29 15:00:00', $date->format('Y-m-d H:i:s'));

        $this->expectException(\DateMalformedStringException::class);
        $this->expectExceptionMessage('Failed to parse time string (Bad Date)');
        $date->modify('Bad Date');
    }

    public function testMicrosecond()
    {
        $date = new DatePoint('2010-01-28 15:00:00.123456');

        $this->assertSame('2010-01-28 15:00:00.123456', $date->format('Y-m-d H:i:s.u'));

        $date = $date->setMicrosecond(789);

        $this->assertSame('2010-01-28 15:00:00.000789', $date->format('Y-m-d H:i:s.u'));
        $this->assertSame(789, $date->getMicrosecond());

        $this->expectException(\DateRangeError::class);
        $this->expectExceptionMessage('DatePoint::setMicrosecond(): Argument #1 ($microsecond) must be between 0 and 999999, 1000000 given');
        $date->setMicrosecond(1000000);
    }
}
