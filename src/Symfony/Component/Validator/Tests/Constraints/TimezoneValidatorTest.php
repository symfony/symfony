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

use Symfony\Component\Validator\Constraints\Timezone;
use Symfony\Component\Validator\Constraints\TimezoneValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @author Javier Spagnoletti <phansys@gmail.com>
 */
class TimezoneValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator()
    {
        return new TimezoneValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Timezone());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new Timezone());

        $this->assertNoViolation();
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsStringCompatibleType()
    {
        $this->validator->validate(new \stdClass(), new Timezone());
    }

    /**
     * @dataProvider getValidTimezones
     */
    public function testValidTimezones($timezone)
    {
        $this->validator->validate($timezone, new Timezone());

        $this->assertNoViolation();
    }

    public function getValidTimezones()
    {
        return array(
            array('America/Argentina/Buenos_Aires'),
            array('America/Barbados'),
            array('Antarctica/Syowa'),
            array('Africa/Douala'),
            array('Atlantic/Canary'),
            array('Asia/Gaza'),
            array('Europe/Copenhagen'),
        );
    }

    /**
     * @dataProvider getValidGroupedTimezones
     */
    public function testValidGroupedTimezones($timezone, $what)
    {
        $constraint = new Timezone(array(
            'value' => $what,
        ));

        $this->validator->validate($timezone, $constraint);

        $this->assertNoViolation();
    }

    public function getValidGroupedTimezones()
    {
        return array(
            array('America/Argentina/Cordoba', \DateTimeZone::AMERICA),
            array('America/Barbados', \DateTimeZone::AMERICA),
            array('Africa/Cairo', \DateTimeZone::AFRICA),
            array('Atlantic/Cape_Verde', \DateTimeZone::ATLANTIC),
            array('Europe/Bratislava', \DateTimeZone::EUROPE),
            array('Indian/Christmas', \DateTimeZone::INDIAN),
            array('Pacific/Kiritimati', \DateTimeZone::ALL),
            array('Pacific/Kiritimati', \DateTimeZone::ALL_WITH_BC),
            array('Pacific/Kiritimati', \DateTimeZone::PACIFIC),
            array('Arctic/Longyearbyen', \DateTimeZone::ARCTIC),
            array('Asia/Beirut', \DateTimeZone::ASIA),
            array('Atlantic/Bermuda', \DateTimeZone::ASIA | \DateTimeZone::ATLANTIC),
            array('Atlantic/Azores', \DateTimeZone::ATLANTIC | \DateTimeZone::ASIA),
        );
    }

    /**
     * @dataProvider getInvalidTimezones
     */
    public function testInvalidTimezones($timezone)
    {
        $constraint = new Timezone(array(
            'message' => 'myMessage',
        ));

        $this->validator->validate($timezone, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ timezone_group }}', '"'.$timezone.'"')
            ->setCode(Timezone::NO_SUCH_TIMEZONE_ERROR)
            ->assertRaised();
    }

    public function getInvalidTimezones()
    {
        return array(
            array('Buenos_Aires/Argentina/America'),
            array('Mayotte/Indian'),
            array('foobar'),
        );
    }

    /**
     * @dataProvider getInvalidGroupedTimezones
     */
    public function testInvalidGroupedTimezones($timezone, $what)
    {
        $constraint = new Timezone(array(
            'value' => $what,
            'message' => 'myMessage',
        ));

        $this->validator->validate($timezone, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ timezone_group }}', '"'.$timezone.'"')
            ->setCode(Timezone::NO_SUCH_TIMEZONE_ERROR)
            ->assertRaised();
    }

    public function getInvalidGroupedTimezones()
    {
        return array(
            array('Antarctica/McMurdo', \DateTimeZone::AMERICA),
            array('America/Barbados', \DateTimeZone::ANTARCTICA),
            array('Europe/Kiev', \DateTimeZone::ARCTIC),
            array('Asia/Ho_Chi_Minh', \DateTimeZone::INDIAN),
        );
    }
}
