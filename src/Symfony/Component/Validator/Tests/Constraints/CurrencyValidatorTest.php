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

class CurrencyValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        IntlTestHelper::requireFullIntl($this);

        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new CurrencyValidator();
        $this->validator->initialize($this->context);
    }

    protected function tearDown()
    {
        $this->context = null;
        $this->validator = null;
    }

    public function testNullIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate(null, new Currency());
    }

    public function testEmptyStringIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate('', new Currency());
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsStringCompatibleType()
    {
        $this->validator->validate(new \stdClass(), new Currency());
    }

    /**
     * @dataProvider getValidCurrencies
     */
    public function testValidCurrencies($currency)
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate($currency, new Currency());
    }

    /**
     * @dataProvider getValidCurrencies
     **/
    public function testValidCurrenciesWithCountrySpecificLocale($currency)
    {
        \Locale::setDefault('en_GB');
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate($currency, new Currency());
    }

    public function getValidCurrencies()
    {
        return array(
            array('EUR'),
            array('USD'),
            array('SIT'),
            array('AUD'),
            array('CAD'),
        );
    }

    /**
     * @dataProvider getInvalidCurrencies
     */
    public function testInvalidCurrencies($currency)
    {
        $constraint = new Currency(array(
            'message' => 'myMessage'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ value }}' => $currency,
            ));

        $this->validator->validate($currency, $constraint);
    }

    public function getInvalidCurrencies()
    {
        return array(
            array('EN'),
            array('foobar'),
        );
    }
}
