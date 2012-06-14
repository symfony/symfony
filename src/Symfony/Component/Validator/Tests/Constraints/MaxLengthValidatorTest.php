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

use Symfony\Component\Validator\Constraints\MaxLength;
use Symfony\Component\Validator\Constraints\MaxLengthValidator;

//for "real" validation context

use Symfony\Component\Validator\GlobalExecutionContext;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ExecutionContext;

class MaxLengthValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new MaxLengthValidator();
        $this->validator->initialize($this->context);
        //added for "real" test
        $this->walker = $this->getMock('Symfony\Component\Validator\GraphWalker', array(), array(), '', false);
        $this->metadataFactory = $this->getMock('Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface');
        $this->globalContext = new GlobalExecutionContext('Root', $this->walker, $this->metadataFactory);
        $this->real_context = new ExecutionContext($this->globalContext, 'currentValue', 'foo.bar', 'Group', 'ClassName', 'propertyName');
        $this->real_validator = new MaxLengthValidator();
        $this->real_validator->initialize($this->real_context);
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

        $this->validator->validate(null, new MaxLength(array('limit' => 5)));
    }

    public function testEmptyStringIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate('', new MaxLength(array('limit' => 5)));
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsStringCompatibleType()
    {
        $this->validator->validate(new \stdClass(), new MaxLength(array('limit' => 5)));
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues($value, $mbOnly = false)
    {
        if ($mbOnly && !function_exists('mb_strlen')) {
            return $this->markTestSkipped('mb_strlen does not exist');
        }

        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new MaxLength(array('limit' => 5));
        $this->validator->validate($value, $constraint);
    }

    public function testInvalidValuePassedtoViolationList()
    {
        $constraint = new MaxLength(array('limit' => 3));
        $this->real_validator->validate($value = '1234', $constraint);
        $foo = $this->real_context->getViolations();
        $this->assertNotEmpty($foo->count(), 'The violation count should not be empty.');
        $this->assertEquals($value, $foo[0]->getInvalidValue()); 
    }

    public function getValidValues()
    {
        return array(
            array(12345),
            array('12345'),
            array('üüüüü', true),
            array('ééééé', true),
        );
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($value, $mbOnly = false)
    {
        if ($mbOnly && !function_exists('mb_strlen')) {
            return $this->markTestSkipped('mb_strlen does not exist');
        }

        $constraint = new MaxLength(array(
            'limit' => 5,
            'message' => 'myMessage'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ value }}' => $value,
                '{{ limit }}' => 5,
            ), null, 5);

        $this->validator->validate($value, $constraint);
    }

    public function getInvalidValues()
    {
        return array(
            array(123456),
            array('123456'),
            array('üüüüüü', true),
            array('éééééé', true),
        );
    }

    public function testConstraintGetDefaultOption()
    {
        $constraint = new MaxLength(array(
            'limit' => 5,
        ));

        $this->assertEquals('limit', $constraint->getDefaultOption());
    }
}
