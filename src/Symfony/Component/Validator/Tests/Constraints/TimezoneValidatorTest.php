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
    protected function createValidator(): TimezoneValidator
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
    public function testValidTimezones(string $timezone)
    {
        $this->validator->validate($timezone, new Timezone());

        $this->assertNoViolation();
    }

    public function getValidTimezones(): iterable
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
    public function testValidGroupedTimezones(string $timezone, int $what)
    {
        $constraint = new Timezone(array(
            'zone' => $what,
        ));

        $this->validator->validate($timezone, $constraint);

        $this->assertNoViolation();
    }

    public function getValidGroupedTimezones(): iterable
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
    public function testInvalidTimezonesWithoutZone(string $timezone, string $zoneMessage, string $countryCodeMessage)
    {
        $constraint = new Timezone(array(
            'message' => 'myMessage',
        ));

        $this->validator->validate($timezone, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ zone_message }}', $zoneMessage)
            ->setParameter('{{ country_code_message }}', $countryCodeMessage)
            ->setCode(Timezone::NO_SUCH_TIMEZONE_ERROR)
            ->assertRaised();
    }

    public function getInvalidTimezones(): iterable
    {
        return array(
            array('Buenos_Aires/Argentina/America', '', ''),
            array('Mayotte/Indian', '', ''),
            array('foobar', '', ''),
        );
    }

    /**
     * @dataProvider getInvalidGroupedTimezones
     */
    public function testInvalidGroupedTimezones(string $timezone, int $what, string $zoneMessage, string $countryCodeMessage)
    {
        $constraint = new Timezone(array(
            'zone' => $what,
            'message' => 'myMessage',
        ));

        $this->validator->validate($timezone, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ zone_message }}', $zoneMessage)
            ->setParameter('{{ country_code_message }}', $countryCodeMessage)
            ->setCode(Timezone::NO_SUCH_TIMEZONE_IN_ZONE_ERROR)
            ->assertRaised();
    }

    public function getInvalidGroupedTimezones(): iterable
    {
        return array(
            array('Antarctica/McMurdo', \DateTimeZone::AMERICA, ' at "AMERICA" zone', ''),
            array('America/Barbados', \DateTimeZone::ANTARCTICA, ' at "ANTARCTICA" zone', ''),
            array('Europe/Kiev', \DateTimeZone::ARCTIC, ' at "ARCTIC" zone', ''),
            array('Asia/Ho_Chi_Minh', \DateTimeZone::INDIAN, ' at "INDIAN" zone', ''),
            array('Asia/Ho_Chi_Minh', \DateTimeZone::INDIAN | \DateTimeZone::ANTARCTICA, ' at zone with identifier 260', ''),
        );
    }

    /**
     * @dataProvider getValidGroupedTimezonesByCountry
     */
    public function testValidGroupedTimezonesByCountry(string $timezone, int $what, string $country)
    {
        $constraint = new Timezone(array(
            'zone' => $what,
            'countryCode' => $country,
        ));

        $this->validator->validate($timezone, $constraint);

        $this->assertNoViolation();
    }

    public function getValidGroupedTimezonesByCountry(): iterable
    {
        return array(
            array('America/Argentina/Cordoba', \DateTimeZone::PER_COUNTRY, 'AR'),
            array('America/Barbados', \DateTimeZone::PER_COUNTRY, 'BB'),
            array('Africa/Cairo', \DateTimeZone::PER_COUNTRY, 'EG'),
            array('Atlantic/Cape_Verde', \DateTimeZone::PER_COUNTRY, 'CV'),
            array('Europe/Bratislava', \DateTimeZone::PER_COUNTRY, 'SK'),
            array('Indian/Christmas', \DateTimeZone::PER_COUNTRY, 'CX'),
            array('Pacific/Kiritimati', \DateTimeZone::PER_COUNTRY, 'KI'),
            array('Pacific/Kiritimati', \DateTimeZone::PER_COUNTRY, 'KI'),
            array('Pacific/Kiritimati', \DateTimeZone::PER_COUNTRY, 'KI'),
            array('Arctic/Longyearbyen', \DateTimeZone::PER_COUNTRY, 'SJ'),
            array('Asia/Beirut', \DateTimeZone::PER_COUNTRY, 'LB'),
            array('Atlantic/Bermuda', \DateTimeZone::PER_COUNTRY, 'BM'),
            array('Atlantic/Azores', \DateTimeZone::PER_COUNTRY, 'PT'),
        );
    }

    /**
     * @dataProvider getInvalidGroupedTimezonesByCountry
     */
    public function testInvalidGroupedTimezonesByCountry(string $timezone, int $what, string $country, string $zoneMessage, string $countryCodeMessage)
    {
        $constraint = new Timezone(array(
            'message' => 'myMessage',
            'zone' => $what,
            'countryCode' => $country,
        ));

        $this->validator->validate($timezone, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ zone_message }}', $zoneMessage)
            ->setParameter('{{ country_code_message }}', $countryCodeMessage)
            ->setCode(Timezone::NO_SUCH_TIMEZONE_IN_COUNTRY_ERROR)
            ->assertRaised();
    }

    public function getInvalidGroupedTimezonesByCountry(): iterable
    {
        return array(
            array('America/Argentina/Cordoba', \DateTimeZone::PER_COUNTRY, 'FR', '', ' for ISO 3166-1 country code "FR"'),
            array('America/Barbados', \DateTimeZone::PER_COUNTRY, 'PT', '', ' for ISO 3166-1 country code "PT"'),
        );
    }

    /**
     * @dataProvider getDeprecatedTimezones
     */
    public function testDeprecatedTimezonesAreVaildWithBC(string $timezone)
    {
        $constraint = new Timezone(array(
            'zone' => \DateTimeZone::ALL_WITH_BC,
        ));

        $this->validator->validate($timezone, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getDeprecatedTimezones
     */
    public function testDeprecatedTimezonesAreInvaildWithoutBC(string $timezone)
    {
        $constraint = new Timezone(array(
            'message' => 'myMessage',
        ));

        $this->validator->validate($timezone, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ zone_message }}', '')
            ->setParameter('{{ country_code_message }}', '')
            ->setCode(Timezone::NO_SUCH_TIMEZONE_ERROR)
            ->assertRaised();
    }

    public function getDeprecatedTimezones(): iterable
    {
        return array(
            array('America/Buenos_Aires'),
            array('Etc/GMT'),
            array('US/Pacific'),
        );
    }
}
