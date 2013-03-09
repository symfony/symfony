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

use Symfony\Component\Validator\Constraints\Locale;
use Symfony\Component\Validator\Constraints\LocaleValidator;

class LocaleValidatorTest extends LocalizedTestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        parent::setUp();

        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new LocaleValidator();
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

        $this->validator->validate(null, new Locale());
    }

    public function testEmptyStringIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate('', new Locale());
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsStringCompatibleType()
    {
        $this->validator->validate(new \stdClass(), new Locale());
    }

    /**
     * @dataProvider getValidLocales
     */
    public function testValidLocales($locale)
    {
        if (!class_exists('Symfony\Component\Locale\Locale')) {
            $this->markTestSkipped('The "Locale" component is not available');
        }

        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate($locale, new Locale());
    }

    public function getValidLocales()
    {
        return array(
            array('en'),
            array('en_US'),
            array('pt'),
            array('pt_PT'),
            array('zh_Hans'),
        );
    }

    /**
     * @dataProvider getInvalidLocales
     */
    public function testInvalidLocales($locale)
    {
        if (!class_exists('Symfony\Component\Locale\Locale')) {
            $this->markTestSkipped('The "Locale" component is not available');
        }

        $constraint = new Locale(array(
            'message' => 'myMessage'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ value }}' => $locale,
            ));

        $this->validator->validate($locale, $constraint);
    }

    public function getInvalidLocales()
    {
        return array(
            array('EN'),
            array('foobar'),
        );
    }
}
