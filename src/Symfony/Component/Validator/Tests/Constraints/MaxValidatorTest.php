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

use Symfony\Component\Validator\Constraints\Max;
use Symfony\Component\Validator\Constraints\MaxValidator;

class MaxValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new MaxValidator();
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

        $this->validator->validate(null, new Max(array('limit' => 10)));
    }

    public function testEmptyStringIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate('', new Max(array('limit' => 10)));
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues($value)
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new Max(array('limit' => 10));
        $this->validator->validate($value, $constraint);
    }

    public function getValidValues()
    {
        return array(
            array(9.999999),
            array(10),
            array(10.0),
            array('10'),
        );
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($value)
    {
        $constraint = new Max(array(
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
            array(10.00001),
            array('10.00001'),
            array(new \stdClass()),
        );
    }

    public function testConstraintGetDefaultOption()
    {
        $constraint = new Max(array(
            'limit' => 10,
        ));

        $this->assertEquals('limit', $constraint->getDefaultOption());
    }
}
