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
 * @author Hugo Hamon <hugohamon@neuf.fr>
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
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedValueException
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
        yield ['America/Argentina/Buenos_Aires'];
        yield ['America/Barbados'];
        yield ['America/Toronto'];
        yield ['Antarctica/Syowa'];
        yield ['Africa/Douala'];
        yield ['Atlantic/Canary'];
        yield ['Asia/Gaza'];
        yield ['Australia/Sydney'];
        yield ['Europe/Copenhagen'];
        yield ['Europe/Paris'];
        yield ['Pacific/Noumea'];
        yield ['UTC'];
    }

    /**
     * @dataProvider getValidGroupedTimezones
     */
    public function testValidGroupedTimezones(string $timezone, int $zone)
    {
        $constraint = new Timezone([
            'zone' => $zone,
        ]);

        $this->validator->validate($timezone, $constraint);

        $this->assertNoViolation();
    }

    public function getValidGroupedTimezones(): iterable
    {
        yield ['America/Argentina/Cordoba', \DateTimeZone::AMERICA];
        yield ['America/Barbados', \DateTimeZone::AMERICA];
        yield ['Africa/Cairo', \DateTimeZone::AFRICA];
        yield ['Atlantic/Cape_Verde', \DateTimeZone::ATLANTIC];
        yield ['Europe/Bratislava', \DateTimeZone::EUROPE];
        yield ['Indian/Christmas', \DateTimeZone::INDIAN];
        yield ['Pacific/Kiritimati', \DateTimeZone::ALL];
        yield ['Pacific/Kiritimati', \DateTimeZone::ALL_WITH_BC];
        yield ['Pacific/Kiritimati', \DateTimeZone::PACIFIC];
        yield ['Arctic/Longyearbyen', \DateTimeZone::ARCTIC];
        yield ['Asia/Beirut', \DateTimeZone::ASIA];
        yield ['Atlantic/Bermuda', \DateTimeZone::ASIA | \DateTimeZone::ATLANTIC];
        yield ['Atlantic/Azores', \DateTimeZone::ATLANTIC | \DateTimeZone::ASIA];
    }

    /**
     * @dataProvider getInvalidTimezones
     */
    public function testInvalidTimezoneWithoutZone(string $timezone)
    {
        $constraint = new Timezone([
            'message' => 'myMessage',
        ]);

        $this->validator->validate($timezone, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', sprintf('"%s"', $timezone))
            ->setCode(Timezone::TIMEZONE_IDENTIFIER_ERROR)
            ->assertRaised();
    }

    public function getInvalidTimezones(): iterable
    {
        yield ['Buenos_Aires/Argentina/America'];
        yield ['Mayotte/Indian'];
        yield ['foobar'];
    }

    /**
     * @dataProvider getInvalidGroupedTimezones
     */
    public function testInvalidGroupedTimezones(string $timezone, int $zone)
    {
        $constraint = new Timezone([
            'zone' => $zone,
            'message' => 'myMessage',
        ]);

        $this->validator->validate($timezone, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', sprintf('"%s"', $timezone))
            ->setCode(Timezone::TIMEZONE_IDENTIFIER_IN_ZONE_ERROR)
            ->assertRaised();
    }

    public function getInvalidGroupedTimezones(): iterable
    {
        yield ['Antarctica/McMurdo', \DateTimeZone::AMERICA];
        yield ['America/Barbados', \DateTimeZone::ANTARCTICA];
        yield ['Europe/Kiev', \DateTimeZone::ARCTIC];
        yield ['Asia/Ho_Chi_Minh', \DateTimeZone::INDIAN];
        yield ['Asia/Ho_Chi_Minh', \DateTimeZone::INDIAN | \DateTimeZone::ANTARCTICA];
    }

    /**
     * @dataProvider getValidGroupedTimezonesByCountry
     */
    public function testValidGroupedTimezonesByCountry(string $timezone, string $country)
    {
        $constraint = new Timezone([
            'zone' => \DateTimeZone::PER_COUNTRY,
            'countryCode' => $country,
        ]);

        $this->validator->validate($timezone, $constraint);

        $this->assertNoViolation();
    }

    public function getValidGroupedTimezonesByCountry(): iterable
    {
        yield ['America/Argentina/Cordoba', 'AR'];
        yield ['America/Barbados', 'BB'];
        yield ['Africa/Cairo', 'EG'];
        yield ['Arctic/Longyearbyen', 'SJ'];
        yield ['Asia/Beirut', 'LB'];
        yield ['Atlantic/Azores', 'PT'];
        yield ['Atlantic/Bermuda', 'BM'];
        yield ['Atlantic/Cape_Verde', 'CV'];
        yield ['Australia/Sydney', 'AU'];
        yield ['Australia/Melbourne', 'AU'];
        yield ['Europe/Bratislava', 'SK'];
        yield ['Europe/Paris', 'FR'];
        yield ['Europe/Madrid', 'ES'];
        yield ['Europe/Monaco', 'MC'];
        yield ['Indian/Christmas', 'CX'];
        yield ['Pacific/Kiritimati', 'KI'];
        yield ['Pacific/Kiritimati', 'KI'];
        yield ['Pacific/Kiritimati', 'KI'];
    }

    /**
     * @dataProvider getInvalidGroupedTimezonesByCountry
     */
    public function testInvalidGroupedTimezonesByCountry(string $timezone, string $invalidCountryCode)
    {
        $constraint = new Timezone([
            'message' => 'myMessage',
            'zone' => \DateTimeZone::PER_COUNTRY,
            'countryCode' => $invalidCountryCode,
        ]);

        $this->validator->validate($timezone, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', sprintf('"%s"', $timezone))
            ->setCode(Timezone::TIMEZONE_IDENTIFIER_IN_COUNTRY_ERROR)
            ->assertRaised();
    }

    public function getInvalidGroupedTimezonesByCountry(): iterable
    {
        yield ['America/Argentina/Cordoba', 'FR'];
        yield ['America/Barbados', 'PT'];
        yield ['Europe/Bern', 'FR'];
    }

    /**
     * @dataProvider getDeprecatedTimezones
     */
    public function testDeprecatedTimezonesAreValidWithBC(string $timezone)
    {
        $constraint = new Timezone([
            'zone' => \DateTimeZone::ALL_WITH_BC,
        ]);

        $this->validator->validate($timezone, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getDeprecatedTimezones
     */
    public function testDeprecatedTimezonesAreInvalidWithoutBC(string $timezone)
    {
        $constraint = new Timezone([
            'message' => 'myMessage',
        ]);

        $this->validator->validate($timezone, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', sprintf('"%s"', $timezone))
            ->setCode(Timezone::TIMEZONE_IDENTIFIER_ERROR)
            ->assertRaised();
    }

    public function getDeprecatedTimezones(): iterable
    {
        yield ['America/Buenos_Aires'];
        yield ['America/Montreal'];
        yield ['Australia/ACT'];
        yield ['Australia/LHI'];
        yield ['Australia/Queensland'];
        yield ['Canada/Eastern'];
        yield ['Canada/Central'];
        yield ['Canada/Mountain'];
        yield ['Canada/Pacific'];
        yield ['CET'];
        yield ['CST6CDT'];
        yield ['Etc/GMT'];
        yield ['Etc/Greenwich'];
        yield ['Etc/UCT'];
        yield ['Etc/Universal'];
        yield ['Etc/UTC'];
        yield ['Etc/Zulu'];
        yield ['US/Pacific'];
    }
}
