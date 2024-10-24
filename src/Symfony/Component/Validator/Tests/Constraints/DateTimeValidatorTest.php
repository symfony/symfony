<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Validator\Constraints\DateTime;
use Symfony\Component\Validator\Constraints\DateTimeValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class DateTimeValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): DateTimeValidator
    {
        return new DateTimeValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new DateTime());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new DateTime());

        $this->assertNoViolation();
    }

    public function testExpectsStringCompatibleType()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->validator->validate(new \stdClass(), new DateTime());
    }

    public function testDateTimeWithDefaultFormat()
    {
        $this->validator->validate('1995-05-10 19:33:00', new DateTime());

        $this->assertNoViolation();

        $this->validator->validate('1995-03-24', new DateTime());

        $this->buildViolation('This value is not a valid datetime.')
            ->setParameter('{{ value }}', '"1995-03-24"')
            ->setCode(DateTime::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getValidDateTimes
     */
    public function testValidDateTimes($format, $dateTime)
    {
        $constraint = new DateTime(format: $format);

        $this->validator->validate($dateTime, $constraint);

        $this->assertNoViolation();
    }

    public static function getValidDateTimes()
    {
        return [
            ['Y-m-d H:i:s e', '1995-03-24 00:00:00 UTC'],
            ['Y-m-d H:i:s', '2010-01-01 01:02:03'],
            ['Y/m/d H:i', '2010/01/01 01:02'],
            ['F d, Y', 'December 31, 1999'],
            ['d-m-Y', '10-05-1995'],
        ];
    }

    /**
     * @dataProvider getInvalidDateTimes
     */
    public function testInvalidDateTimes($format, $dateTime, $code)
    {
        $constraint = new DateTime(
            message: 'myMessage',
            format: $format,
        );

        $this->validator->validate($dateTime, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$dateTime.'"')
            ->setCode($code)
            ->assertRaised();
    }

    public static function getInvalidDateTimes()
    {
        return [
            ['Y-m-d', 'foobar', DateTime::INVALID_FORMAT_ERROR],
            ['H:i', '00:00:00', DateTime::INVALID_FORMAT_ERROR],
            ['Y-m-d', '2010-01-01 00:00', DateTime::INVALID_FORMAT_ERROR],
            ['Y-m-d e', '2010-01-01 TCU', DateTime::INVALID_FORMAT_ERROR],
            ['Y-m-d H:i:s', '2010-13-01 00:00:00', DateTime::INVALID_DATE_ERROR],
            ['Y-m-d H:i:s', '2010-04-32 00:00:00', DateTime::INVALID_DATE_ERROR],
            ['Y-m-d H:i:s', '2010-02-29 00:00:00', DateTime::INVALID_DATE_ERROR],
            ['Y-m-d H:i:s', '2010-01-01 24:00:00', DateTime::INVALID_TIME_ERROR],
            ['Y-m-d H:i:s', '2010-01-01 00:60:00', DateTime::INVALID_TIME_ERROR],
            ['Y-m-d H:i:s', '2010-01-01 00:00:60', DateTime::INVALID_TIME_ERROR],
        ];
    }

    public function testInvalidDateTimeNamed()
    {
        $constraint = new DateTime(message: 'myMessage', format: 'Y-m-d');

        $this->validator->validate('2010-01-01 00:00:00', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"2010-01-01 00:00:00"')
            ->setCode(DateTime::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    public function testDateTimeWithTrailingData()
    {
        $this->validator->validate('1995-05-10 00:00:00', new DateTime(format: 'Y-m-d+'));
        $this->assertNoViolation();
    }
}
