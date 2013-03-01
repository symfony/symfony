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

use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\RegexValidator;

class RegexValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new RegexValidator();
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

        $this->validator->validate(null, new Regex(array('pattern' => '/^[0-9]+$/')));
    }

    public function testEmptyStringIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate('', new Regex(array('pattern' => '/^[0-9]+$/')));
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsStringCompatibleType()
    {
        $this->validator->validate(new \stdClass(), new Regex(array('pattern' => '/^[0-9]+$/')));
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues($value)
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new Regex(array('pattern' => '/^[0-9]+$/'));
        $this->validator->validate($value, $constraint);
    }

    public function getValidValues()
    {
        return array(
            array(0),
            array('0'),
            array('090909'),
            array(90909),
        );
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($value)
    {
        $constraint = new Regex(array(
            'pattern' => '/^[0-9]+$/',
            'message' => 'myMessage'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ value }}' => $value,
            ));

        $this->validator->validate($value, $constraint);
    }

    public function getInvalidValues()
    {
        return array(
            array('abcd'),
            array('090foo'),
        );
    }

    public function testConstraintGetDefaultOption()
    {
        $constraint = new Regex(array(
            'pattern' => '/^[0-9]+$/',
        ));

        $this->assertEquals('pattern', $constraint->getDefaultOption());
    }

    public function testHtmlPatternEscaping()
    {
        $constraint = new Regex(array(
            'pattern' => '/^[0-9]+\/$/',
        ));

        $this->assertEquals('[0-9]+/', $constraint->getHtmlPattern());

        $constraint = new Regex(array(
            'pattern' => '#^[0-9]+\#$#',
        ));

        $this->assertEquals('[0-9]+#', $constraint->getHtmlPattern());
    }

    public function testHtmlPattern()
    {
        // Specified htmlPattern
        $constraint = new Regex(array(
            'pattern' => '/^[a-z]+$/i',
            'htmlPattern' => '[a-zA-Z]+',
        ));
        $this->assertEquals('[a-zA-Z]+', $constraint->getHtmlPattern());

        // Disabled htmlPattern
        $constraint = new Regex(array(
            'pattern' => '/^[a-z]+$/i',
            'htmlPattern' => false,
        ));
        $this->assertNull($constraint->getHtmlPattern());

        // Cannot be converted
        $constraint = new Regex(array(
            'pattern' => '/^[a-z]+$/i',
        ));
        $this->assertNull($constraint->getHtmlPattern());

        // Automaticaly converted
        $constraint = new Regex(array(
            'pattern' => '/^[a-z]+$/',
        ));
        $this->assertEquals('[a-z]+', $constraint->getHtmlPattern());

        // Automaticaly converted, adds .*
        $constraint = new Regex(array(
            'pattern' => '/[a-z]+/',
        ));
        $this->assertEquals('.*[a-z]+.*', $constraint->getHtmlPattern());

        // Dropped because of match=false
        $constraint = new Regex(array(
            'pattern' => '/[a-z]+/',
            'match' => false
        ));
        $this->assertNull($constraint->getHtmlPattern());
    }
}
