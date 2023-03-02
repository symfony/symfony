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
use Symfony\Component\Validator\Constraints\Currency;
use Symfony\Component\Validator\Constraints\CurrencyValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class CurrencyValidatorTest extends ConstraintValidatorTestCase
{
    private $defaultLocale;

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

    protected function createValidator(): CurrencyValidator
    {
        return new CurrencyValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Currency());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new Currency());

        $this->assertNoViolation();
    }

    public function testExpectsStringCompatibleType()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->validator->validate(new \stdClass(), new Currency());
    }

    /**
     * @dataProvider getValidCurrencies
     */
    public function testValidCurrencies($currency)
    {
        $this->validator->validate($currency, new Currency());

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidCurrencies
     **/
    public function testValidCurrenciesWithCountrySpecificLocale($currency)
    {
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('en_GB');

        $this->validator->validate($currency, new Currency());

        $this->assertNoViolation();
    }

    public static function getValidCurrencies()
    {
        return [
            ['EUR'],
            ['USD'],
            ['SIT'],
            ['AUD'],
            ['CAD'],
        ];
    }

    /**
     * @dataProvider getInvalidCurrencies
     */
    public function testInvalidCurrencies($currency)
    {
        $constraint = new Currency([
            'message' => 'myMessage',
        ]);

        $this->validator->validate($currency, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$currency.'"')
            ->setCode(Currency::NO_SUCH_CURRENCY_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getInvalidCurrencies
     */
    public function testInvalidCurrenciesNamed($currency)
    {
        $constraint = new Currency(message: 'myMessage');

        $this->validator->validate($currency, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$currency.'"')
            ->setCode(Currency::NO_SUCH_CURRENCY_ERROR)
            ->assertRaised();
    }

    public static function getInvalidCurrencies()
    {
        return [
            ['EN'],
            ['foobar'],
        ];
    }
}
