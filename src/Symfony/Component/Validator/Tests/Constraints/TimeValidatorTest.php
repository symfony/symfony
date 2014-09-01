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

use Symfony\Component\Validator\Constraints\Time;
use Symfony\Component\Validator\Constraints\TimeValidator;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Tests\Fixtures\InvalidConstraint;

class TimeValidatorTest extends AbstractConstraintValidatorTest
{
    protected function getApiVersion()
    {
        return Validation::API_VERSION_2_5;
    }

    protected function createValidator()
    {
        return new TimeValidator();
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\InvalidConfigurationException
     */
    public function testThrowsExceptionIfConfigurationIsInvalid()
    {
        new Time(array(
            'withMinutes' => false,
            'withSeconds' => true,
        ));
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Time());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new Time());

        $this->assertNoViolation();
    }

    public function testDateTimeClassIsValid()
    {
        $this->validator->validate(new \DateTime(), new Time());

        $this->assertNoViolation();
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsStringCompatibleType()
    {
        $this->validator->validate(new \stdClass(), new Time());
    }

    /**
     * @dataProvider getValidTimes
     */
    public function testValidTimes($time)
    {
        $this->validator->validate($time, new Time());

        $this->assertNoViolation();
    }

    public function getValidTimes()
    {
        return array(
            array('01:02:03'),
            array('00:00:00'),
            array('23:59:59'),
        );
    }

    /**
     * @dataProvider getValidTimesWithoutMinutes
     */
    public function testValidTimesWithoutMinutes($time)
    {
        $this->validator->validate($time, new Time(array(
            'withMinutes' => false,
        )));

        $this->assertNoViolation();
    }

    public function getValidTimesWithoutMinutes()
    {
        return array(
            array('01'),
            array('00'),
            array('0'),
            array('23'),
            array('5'),
        );
    }

    /**
     * @dataProvider getValidTimesWithoutSeconds
     */
    public function testValidTimesWithoutSeconds($time)
    {
        $this->validator->validate($time, new Time(array(
            'withSeconds' => false,
        )));

        $this->assertNoViolation();
    }

    public function getValidTimesWithoutSeconds()
    {
        return array(
            array('01:02'),
            array('00:00'),
            array('4:00'),
            array('23:59'),
        );
    }

    /**
     * @dataProvider getInvalidTimes
     */
    public function testInvalidTimes($time)
    {
        $constraint = new Time(array(
            'message' => 'myMessage'
        ));

        $this->validator->validate($time, $constraint);

        $this->assertViolation('myMessage', array(
            '{{ value }}' => $time,
        ));
    }

    public function getInvalidTimes()
    {
        return array(
            array('foobar'),
            array('foobar 12:34:56'),
            array('12:34:56 foobar'),
            array('00:00'),
            array('05:3'),
            array('24:00:00'),
            array('00:60:00'),
            array('00:00:60'),
        );
    }

    /**
     * @dataProvider getInvalidTimesWithoutMinutes
     */
    public function testInvalidTimesWithoutMinutes($time)
    {
        $constraint = new Time(array(
            'message' => 'myMessage',
            'withMinutes' => false,
        ));

        $this->validator->validate($time, $constraint);

        $this->assertViolation('myMessage', array(
            '{{ value }}' => $time,
        ));
    }

    public function getInvalidTimesWithoutMinutes()
    {
        return array(
            array('foobar'),
            array('foobar 12'),
            array('12 foobar'),
            array('24'),
            array('60'),
        );
    }

    /**
     * @dataProvider getInvalidTimesWithoutSeconds
     */
    public function testInvalidTimesWithoutSeconds($time)
    {
        $constraint = new Time(array(
            'message' => 'myMessage',
            'withSeconds' => false,
        ));

        $this->validator->validate($time, $constraint);

        $this->assertViolation('myMessage', array(
            '{{ value }}' => $time,
        ));
    }

    public function getInvalidTimesWithoutSeconds()
    {
        return array(
            array('foobar'),
            array('foobar 12:34'),
            array('12:34 foobar'),
            array('01:02:03'),
            array('24:00'),
            array('00:60'),
        );
    }
}
