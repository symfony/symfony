<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraints\Number;
use Symfony\Component\Validator\Constraints\NumberValidator;

class NumberValidatorTest extends LocalizedTestCase
{
    protected $validator;

    protected function setUp()
    {
        parent::setUp();

        \Locale::setDefault('en');

        $this->validator = new NumberValidator();
    }

    protected function tearDown()
    {
        $this->validator = null;
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new Number()));
    }

    public function testEmptyStringIsValid()
    {
        $this->assertTrue($this->validator->isValid('', new Number()));
    }

    public function testExpectsStringCompatibleType()
    {
        $this->setExpectedException('Symfony\Component\Validator\Exception\UnexpectedTypeException');

        $this->validator->isValid(new \stdClass(), new Number());
    }

    /**
     * @dataProvider getValidLocales
     */
    public function testValidLocales($date)
    {
        $this->assertTrue($this->validator->isValid($date, new Number()));
    }

    public function getValidLocales()
    {
        return array(
            array('1'),
            array('12.45'),
            array('0.200'),
            array('1,000,000'),
        );
    }

    /**
     * @dataProvider getInvalidLocales
     */
    public function testInvalidLocales($date)
    {
        $this->assertFalse($this->validator->isValid($date, new Number()));
    }

    public function getInvalidLocales()
    {
        return array(
            array('1 200.50'),
            array('........'),
            array('1!200.50'),
        );
    }

    /**
     * @dataProvider getValidCustomLocales
     */
    public function testCustomLocaleIsValid($data)
    {
        $this->assertTrue($this->validator->isValid($data, new Number(array('locale' => 'fr_FR'))));
    }

    public function getValidCustomLocales()
    {
        return array(
            array('1'),
            array('12,45'),
            array('0,200'),
            array('1,000,000'),
        );
    }

    /**
     * @dataProvider getInvalidCustomLocales
     */
    public function testInvalidCustomLocaleIsValid($data)
    {
        $this->assertFalse($this->validator->isValid($data, new Number(array('locale' => 'fr_FR'))));
    }

    public function getInvalidCustomLocales()
    {
        return array(
            array('1 200,50'),
            array('50.1234'),
            array('1!200.50'),
        );
    }

    /**
     * @dataProvider getValidNoLatinLocales
     */
    public function testNoLatinLocaleIsValid($data)
    {
        $this->assertTrue($this->validator->isValid($data, new Number(array('locale' => 'ar'))));
    }

    public function getValidNoLatinLocales()
    {
        return array(
            array('1'),
            array('١٢٫٤٥'),
            array('١٬٢٣٤٫٥٦'),
            array('1000000'),
        );
    }

    /**
     * @dataProvider getInvalidNoLatinLocales
     */
    public function testInvalidNoLatinLocaleIsValid($data)
    {
        $this->assertFalse($this->validator->isValid($data, new Number(array('locale' => 'fr_FR'))));
    }

    public function getInvalidNoLatinLocales()
    {
        return array(
            array('1 200,50'),
            array('50.1234'),
            array('1!200.50'),
        );
    }

    public function testMessageIsSet()
    {
        $constraint = new Number(array(
            'message' => 'myMessage'
        ));

        $this->assertFalse($this->validator->isValid('foobar', $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            '{{ value }}'    => 'foobar',
            '{{ language }}' => 'English',
            '{{ format }}'   => '1,234.56',
        ));
    }
}
