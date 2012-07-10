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

use Symfony\Component\Validator\Constraints\Min;
use Symfony\Component\Validator\Constraints\MinValidator;

class MinValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new MinValidator();
        $this->validator->initialize($this->context);
    }

    public function testNullIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate(null, new Min(array('limit' => 10)));
    }

    public function testEmptyStringIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate('', new Min(array('limit' => 10)));
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues($value)
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new Min(array('limit' => 10));
        $this->validator->validate($value, $constraint);
    }

    public function getValidValues()
    {
        return array(
            array(10.00001),
            array('10.00001'),
            array(10),
            array(10.0),
        );
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($value)
    {
        $constraint = new Min(array(
            'limit' => 10,
            'message' => 'myMessage',
            'invalidMessage' => 'myMessage'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ value }}' => $value,
                '{{ limit }}' => 10,
            ));

        $this->validator->validate($value, $constraint);
    }

    public function getInvalidValues()
    {
        return array(
            array(9.999999),
            array('9.999999'),
            array(new \stdClass()),
        );
    }

    public function testConstraintGetDefaultOption()
    {
        $constraint = new Min(array(
            'limit' => 10,
        ));

        $this->assertEquals('limit', $constraint->getDefaultOption());
    }
}
