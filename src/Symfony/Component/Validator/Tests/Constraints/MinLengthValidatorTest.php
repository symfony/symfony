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

use Symfony\Component\Validator\Constraints\MinLength;
use Symfony\Component\Validator\Constraints\MinLengthValidator;

class MinLengthValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new MinLengthValidator();
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

        $this->validator->validate(null, new MinLength(array('limit' => 6)));
    }

    public function testEmptyStringIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate('', new MinLength(array('limit' => 6)));
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsStringCompatibleType()
    {
        $this->validator->validate(new \stdClass(), new MinLength(array('limit' => 5)));
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues($value, $mbOnly = false)
    {
        if ($mbOnly && !function_exists('mb_strlen')) {
            $this->markTestSkipped('mb_strlen does not exist');
        }

        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new MinLength(array('limit' => 6));
        $this->validator->validate($value, $constraint);
    }

    public function getValidValues()
    {
        return array(
            array(123456),
            array('123456'),
            array('üüüüüü', true),
            array('éééééé', true),
        );
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($value, $mbOnly = false)
    {
        if ($mbOnly && !function_exists('mb_strlen')) {
            $this->markTestSkipped('mb_strlen does not exist');
        }

        $constraint = new MinLength(array(
            'limit' => 5,
            'message' => 'myMessage'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', $this->identicalTo(array(
                '{{ value }}' => (string) $value,
                '{{ limit }}' => 5,
            )), $this->identicalTo($value), 5);

        $this->validator->validate($value, $constraint);
    }

    public function getInvalidValues()
    {
        return array(
            array(1234),
            array('1234'),
            array('üüüü', true),
            array('éééé', true),
        );
    }

    public function testConstraintGetDefaultOption()
    {
        $constraint = new MinLength(array(
            'limit' => 5,
        ));

        $this->assertEquals('limit', $constraint->getDefaultOption());
    }
}
