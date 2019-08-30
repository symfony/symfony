<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class ConstraintValidatorTest extends TestCase
{
    /**
     * @dataProvider formatValueProvider
     */
    public function testFormatValue($expected, $value, $format = 0)
    {
        $this->assertSame($expected, (new TestFormatValueConstraintValidator())->formatValueProxy($value, $format));
    }

    public function formatValueProvider()
    {
        $defaultTimezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Moscow'); // GMT+3

        $negativeDateInterval = new \DateInterval('PT30S');
        $negativeDateInterval->invert = 1;

        $data = [
            ['true', true],
            ['false', false],
            ['null', null],
            ['resource', fopen('php://memory', 'r')],
            ['"foo"', 'foo'],
            ['array', []],
            ['object', $toString = new TestToStringObject()],
            ['ccc', $toString, ConstraintValidator::OBJECT_TO_STRING],
            ['object', $dateTime = new \DateTimeImmutable('1971-02-02T08:00:00UTC')],
            [class_exists(\IntlDateFormatter::class) ? 'Oct 4, 2019, 11:02 AM' : '2019-10-04 11:02:03', new \DateTimeImmutable('2019-10-04T11:02:03+09:00'), ConstraintValidator::PRETTY_DATE],
            [class_exists(\IntlDateFormatter::class) ? 'Feb 2, 1971, 8:00 AM' : '1971-02-02 08:00:00', $dateTime, ConstraintValidator::PRETTY_DATE],
            [class_exists(\IntlDateFormatter::class) ? 'Jan 1, 1970, 6:00 AM' : '1970-01-01 06:00:00', new \DateTimeImmutable('1970-01-01T06:00:00Z'), ConstraintValidator::PRETTY_DATE],
            [class_exists(\IntlDateFormatter::class) ? 'Jan 1, 1970, 3:00 PM' : '1970-01-01 15:00:00', (new \DateTimeImmutable('1970-01-01T23:00:00'))->setTimezone(new \DateTimeZone('America/New_York')), ConstraintValidator::PRETTY_DATE],
            ['object', new \DateInterval('PT30S')],
            ['1 year, 1 month, 1 day, 1 hour, 1 minute and 1 second', new \DateInterval('P1Y1M1DT1H1M1S'), ConstraintValidator::PRETTY_DATE_INTERVAL],
            ['3 months and 4 seconds', new \DateInterval('P3MT4S'), ConstraintValidator::PRETTY_DATE_INTERVAL],
            ['0', new \DateInterval('PT0S'), ConstraintValidator::PRETTY_DATE_INTERVAL],
            ['0', ($dateTime = new \DateTimeImmutable())->diff($dateTime), ConstraintValidator::PRETTY_DATE_INTERVAL],
            ['7 days', new \DateInterval('P1W'), ConstraintValidator::PRETTY_DATE_INTERVAL],
            ['-30 seconds', $negativeDateInterval, ConstraintValidator::PRETTY_DATE_INTERVAL],
        ];

        date_default_timezone_set($defaultTimezone);

        return $data;
    }
}

final class TestFormatValueConstraintValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
    }

    public function formatValueProxy($value, $format)
    {
        return $this->formatValue($value, $format);
    }
}

final class TestToStringObject
{
    public function __toString()
    {
        return 'ccc';
    }
}
