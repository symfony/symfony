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

use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\RegexValidator;

class RegexValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;

    protected function setUp()
    {
        $this->validator = new RegexValidator();
    }

    protected function tearDown()
    {
        $this->validator = null;
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new Regex(array('pattern' => '/^[0-9]+$/'))));
    }

    public function testEmptyStringIsValid()
    {
        $this->assertTrue($this->validator->isValid('', new Regex(array('pattern' => '/^[0-9]+$/'))));
    }

    public function testExpectsStringCompatibleType()
    {
        $this->setExpectedException('Symfony\Component\Validator\Exception\UnexpectedTypeException');

        $this->validator->isValid(new \stdClass(), new Regex(array('pattern' => '/^[0-9]+$/')));
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues($value)
    {
        $constraint = new Regex(array('pattern' => '/^[0-9]+$/'));
        $this->assertTrue($this->validator->isValid($value, $constraint));
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
        $constraint = new Regex(array('pattern' => '/^[0-9]+$/'));
        $this->assertFalse($this->validator->isValid($value, $constraint));
    }

    public function getInvalidValues()
    {
        return array(
            array('abcd'),
            array('090foo'),
        );
    }

    public function testMessageIsSet()
    {
        $constraint = new Regex(array(
            'pattern' => '/^[0-9]+$/',
            'message' => 'myMessage'
        ));

        $this->assertFalse($this->validator->isValid('foobar', $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            '{{ value }}' => 'foobar',
        ));
    }

    public function testConstraintGetDefaultOption()
    {
        $constraint = new Regex(array(
            'pattern' => '/^[0-9]+$/',
        ));

        $this->assertEquals('pattern', $constraint->getDefaultOption());
    }
}
