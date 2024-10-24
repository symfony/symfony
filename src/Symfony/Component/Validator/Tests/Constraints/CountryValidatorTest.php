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

use Symfony\Component\Intl\Util\IntlTestHelper;
use Symfony\Component\Validator\Constraints\Country;
use Symfony\Component\Validator\Constraints\CountryValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class CountryValidatorTest extends ConstraintValidatorTestCase
{
    private string $defaultLocale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultLocale = \Locale::getDefault();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        \Locale::setDefault($this->defaultLocale);
    }

    protected function createValidator(): CountryValidator
    {
        return new CountryValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Country());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new Country());

        $this->assertNoViolation();
    }

    public function testExpectsStringCompatibleType()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->validator->validate(new \stdClass(), new Country());
    }

    /**
     * @dataProvider getValidCountries
     */
    public function testValidCountries($country)
    {
        $this->validator->validate($country, new Country());

        $this->assertNoViolation();
    }

    public static function getValidCountries()
    {
        return [
            ['GB'],
            ['AT'],
            ['MY'],
        ];
    }

    /**
     * @dataProvider getInvalidCountries
     */
    public function testInvalidCountries($country)
    {
        $constraint = new Country(message: 'myMessage');

        $this->validator->validate($country, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$country.'"')
            ->setCode(Country::NO_SUCH_COUNTRY_ERROR)
            ->assertRaised();
    }

    public static function getInvalidCountries()
    {
        return [
            ['foobar'],
            ['EN'],
        ];
    }

    /**
     * @dataProvider getValidAlpha3Countries
     */
    public function testValidAlpha3Countries($country)
    {
        $this->validator->validate($country, new Country(alpha3: true));

        $this->assertNoViolation();
    }

    public static function getValidAlpha3Countries()
    {
        return [
            ['GBR'],
            ['ATA'],
            ['MYT'],
        ];
    }

    /**
     * @dataProvider getInvalidAlpha3Countries
     */
    public function testInvalidAlpha3Countries($country)
    {
        $constraint = new Country(
            alpha3: true,
            message: 'myMessage',
        );

        $this->validator->validate($country, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$country.'"')
            ->setCode(Country::NO_SUCH_COUNTRY_ERROR)
            ->assertRaised();
    }

    public static function getInvalidAlpha3Countries()
    {
        return [
            ['foobar'],
            ['GB'],
            ['ZZZ'],
            ['zzz'],
        ];
    }

    public function testInvalidAlpha3CountryNamed()
    {
        $this->validator->validate(
            'DE',
            new Country(alpha3: true, message: 'myMessage')
        );

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"DE"')
            ->setCode(Country::NO_SUCH_COUNTRY_ERROR)
            ->assertRaised();
    }

    public function testValidateUsingCountrySpecificLocale()
    {
        // in order to test with "en_GB"
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('en_GB');

        $existingCountry = 'GB';

        $this->validator->validate($existingCountry, new Country());

        $this->assertNoViolation();
    }
}
