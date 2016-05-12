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

class DateTimeValidatorTest extends AbstractConstraintValidatorTest
{
    protected function createValidator()
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

    public function testDateTimeClassIsValid()
    {
        $this->validator->validate(new \DateTime(), new DateTime());

        $this->assertNoViolation();
    }

    public function testDateTimeImmutableClassIsValid()
    {
        $this->validator->validate(new \DateTimeImmutable(), new DateTime());

        $this->assertNoViolation();
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsStringCompatibleType()
    {
        $this->validator->validate(new \stdClass(), new DateTime());
    }

    /**
     * @dataProvider getValidDateTimes
     */
    public function testValidDateTimes($dateTime)
    {
        $this->validator->validate($dateTime, new DateTime());

        $this->assertNoViolation();
    }

    public function getValidDateTimes()
    {
        return array(
            array('2010-01-01 01:02:03'),
            array('1955-12-12 00:00:00'),
            array('2030-05-31 23:59:59'),
        );
    }

    /**
     * @dataProvider getInvalidDateTimes
     */
    public function testInvalidDateTimes($dateTime, $code)
    {
        $constraint = new DateTime(array(
            'message' => 'myMessage',
        ));

        $this->validator->validate($dateTime, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$dateTime.'"')
            ->setCode($code)
            ->assertRaised();
    }

    public function getInvalidDateTimes()
    {
        return array(
            array('foobar', DateTime::INVALID_FORMAT_ERROR),
            array('2010-01-01', DateTime::INVALID_FORMAT_ERROR),
            array('00:00:00', DateTime::INVALID_FORMAT_ERROR),
            array('2010-01-01 00:00', DateTime::INVALID_FORMAT_ERROR),
            array('2010-13-01 00:00:00', DateTime::INVALID_DATE_ERROR),
            array('2010-04-32 00:00:00', DateTime::INVALID_DATE_ERROR),
            array('2010-02-29 00:00:00', DateTime::INVALID_DATE_ERROR),
            array('2010-01-01 24:00:00', DateTime::INVALID_TIME_ERROR),
            array('2010-01-01 00:60:00', DateTime::INVALID_TIME_ERROR),
            array('2010-01-01 00:00:60', DateTime::INVALID_TIME_ERROR),
        );
    }
}
