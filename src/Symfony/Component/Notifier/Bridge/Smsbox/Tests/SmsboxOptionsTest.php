<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Smsbox\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Notifier\Bridge\Smsbox\Enum\Charset;
use Symfony\Component\Notifier\Bridge\Smsbox\Enum\Day;
use Symfony\Component\Notifier\Bridge\Smsbox\Enum\Mode;
use Symfony\Component\Notifier\Bridge\Smsbox\Enum\Strategy;
use Symfony\Component\Notifier\Bridge\Smsbox\Enum\Udh;
use Symfony\Component\Notifier\Bridge\Smsbox\SmsboxOptions;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;

class SmsboxOptionsTest extends TestCase
{
    public function testSmsboxOptions()
    {
        $smsboxOptions = (new SmsboxOptions())
            ->mode(Mode::Expert)
            ->sender('SENDER')
            ->strategy(Strategy::Marketing)
            ->charset(Charset::Utf8)
            ->udh(Udh::DisabledConcat)
            ->maxParts(2)
            ->validity(100)
            ->destIso('FR');

        self::assertSame([
            'mode' => 'Expert',
            'sender' => 'SENDER',
            'strategy' => 4,
            'charset' => 'utf-8',
            'udh' => 0,
            'max_parts' => 2,
            'validity' => 100,
            'dest_iso' => 'FR',
        ], $smsboxOptions->toArray());
    }

    public function testSmsboxOptionsInvalidDestIso()
    {
        if (!class_exists(Countries::class)) {
            $this->markTestSkipped('The "symfony/intl" component is required to run this test.');
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The country code "X1" is not valid.');

        (new SmsboxOptions())
            ->mode(Mode::Expert)
            ->sender('SENDER')
            ->strategy(Strategy::Marketing)
            ->destIso('X1');
    }

    public function testDateIsCalledWithDateTime()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Either Symfony\Component\Notifier\Bridge\Smsbox\SmsboxOptions::dateTime() or Symfony\Component\Notifier\Bridge\Smsbox\SmsboxOptions::date() and Symfony\Component\Notifier\Bridge\Smsbox\SmsboxOptions::hour() must be called, but not both.');

        (new SmsboxOptions())
            ->dateTime(new \DateTimeImmutable('+1 day'))
            ->date('01/01/2021');
    }

    public function testDateInWrongFormat()
    {
        $this->expectException(\DateMalformedStringException::class);
        $this->expectExceptionMessage('The date must be in DD/MM/YYYY format.');

        (new SmsboxOptions())
            ->date('01/2021');
    }

    public function testHourIsCalledWithDateTime()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Either Symfony\Component\Notifier\Bridge\Smsbox\SmsboxOptions::dateTime() or Symfony\Component\Notifier\Bridge\Smsbox\SmsboxOptions::date() and Symfony\Component\Notifier\Bridge\Smsbox\SmsboxOptions::hour() must be called, but not both.');

        (new SmsboxOptions())
            ->dateTime(new \DateTimeImmutable('+1 day'))
            ->hour('12:00');
    }

    public function testHourInWrongFormat()
    {
        $this->expectException(\DateMalformedStringException::class);
        $this->expectExceptionMessage('Hour must be in HH:MM format.');

        (new SmsboxOptions())
            ->hour('12:00:00');
    }

    public function testDateTimeIsCalledWithDate()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Either Symfony\Component\Notifier\Bridge\Smsbox\SmsboxOptions::dateTime() or Symfony\Component\Notifier\Bridge\Smsbox\SmsboxOptions::date() and Symfony\Component\Notifier\Bridge\Smsbox\SmsboxOptions::hour() must be called, but not both.');

        (new SmsboxOptions())
            ->date('01/01/2021')
            ->dateTime(new \DateTimeImmutable('+1 day'));
    }

    public function testDateTimeIsCalledWithHour()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Either Symfony\Component\Notifier\Bridge\Smsbox\SmsboxOptions::dateTime() or Symfony\Component\Notifier\Bridge\Smsbox\SmsboxOptions::date() and Symfony\Component\Notifier\Bridge\Smsbox\SmsboxOptions::hour() must be called, but not both.');

        (new SmsboxOptions())
            ->hour('12:00')
            ->dateTime(new \DateTimeImmutable('+1 day'));
    }

    public function testDateTimeIsInPast()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The given DateTime must be greater to the current date.');

        (new SmsboxOptions())
            ->dateTime(new \DateTimeImmutable('-1 day'));
    }

    /**
     * @testWith [0]
     *           [9]
     */
    public function testMaxPartIsInvalid(int $maxPart)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The "max_parts" option must be an integer between 1 and 8, got "%d".', $maxPart));

        (new SmsboxOptions())
            ->maxParts($maxPart);
    }

    /**
     * @testWith [4]
     *           [1441]
     */
    public function testValidityIsInvalid(int $validity)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The "validity" option must be an integer between 5 and 1440, got "%d".', $validity));

        (new SmsboxOptions())
            ->validity($validity);
    }

    public function testDayMinIsAfterMax()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The minimum day must be before the maximum day or the same.');

        (new SmsboxOptions())
            ->daysMinMax(Day::Sunday, Day::Friday);
    }

    public function testHourIsNegative()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The minimum hour must be greater than 0 and lower than the maximum hour.');

        (new SmsboxOptions())
            ->hoursMinMax(-1, 12);
    }

    public function testMinHourIsAfterMax()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The minimum hour must be greater than 0 and lower than the maximum hour.');

        (new SmsboxOptions())
            ->hoursMinMax(12, 11);
    }

    public function testMaxHourIsOutOfBounds()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The maximum hour must be lower or equal to 23.');

        (new SmsboxOptions())
            ->hoursMinMax(0, 24);
    }
}
