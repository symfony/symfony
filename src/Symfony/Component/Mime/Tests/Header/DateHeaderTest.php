<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests\Header;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Header\DateHeader;

class DateHeaderTest extends TestCase
{
    /* --
    The following tests refer to RFC 2822, section 3.6.1 and 3.3.
    */

    public function testGetDateTime()
    {
        $header = new DateHeader('Date', $dateTime = new \DateTimeImmutable());
        self::assertSame($dateTime, $header->getDateTime());
    }

    public function testDateTimeCanBeSetBySetter()
    {
        $header = new DateHeader('Date', new \DateTimeImmutable());
        $header->setDateTime($dateTime = new \DateTimeImmutable());
        self::assertSame($dateTime, $header->getDateTime());
    }

    public function testDateTimeIsConvertedToImmutable()
    {
        $dateTime = new \DateTime();
        $header = new DateHeader('Date', $dateTime);
        self::assertInstanceOf(\DateTimeImmutable::class, $header->getDateTime());
        self::assertEquals($dateTime->getTimestamp(), $header->getDateTime()->getTimestamp());
        self::assertEquals($dateTime->getTimezone(), $header->getDateTime()->getTimezone());
    }

    public function testDateTimeIsImmutable()
    {
        $header = new DateHeader('Date', $dateTime = new \DateTime('2000-01-01 12:00:00 Europe/Berlin'));
        $dateTime->setDate(2002, 2, 2);
        self::assertEquals('Sat, 01 Jan 2000 12:00:00 +0100', $header->getDateTime()->format('r'));
        self::assertEquals('Sat, 01 Jan 2000 12:00:00 +0100', $header->getBodyAsString());
    }

    public function testDateTimeIsConvertedToRfc2822Date()
    {
        $header = new DateHeader('Date', $dateTime = new \DateTimeImmutable('2000-01-01 12:00:00 Europe/Berlin'));
        $header->setDateTime($dateTime);
        self::assertEquals('Sat, 01 Jan 2000 12:00:00 +0100', $header->getBodyAsString());
    }

    public function testSetBody()
    {
        $header = new DateHeader('Date', $dateTime = new \DateTimeImmutable());
        $header->setBody($dateTime);
        self::assertEquals($dateTime->format('r'), $header->getBodyAsString());
    }

    public function testGetBody()
    {
        $header = new DateHeader('Date', $dateTime = new \DateTimeImmutable());
        $header->setDateTime($dateTime);
        self::assertEquals($dateTime, $header->getBody());
    }

    public function testToString()
    {
        $header = new DateHeader('Date', $dateTime = new \DateTimeImmutable('2000-01-01 12:00:00 Europe/Berlin'));
        $header->setDateTime($dateTime);
        self::assertEquals('Date: Sat, 01 Jan 2000 12:00:00 +0100', $header->toString());
    }
}
