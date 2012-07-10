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

use Symfony\Component\Validator\ExecutionContext;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\CallbackValidator;

class CallbackValidatorTest_Class
{
    public static function validateStatic($object, ExecutionContext $context)
    {
        $context->addViolation('Static message', array('{{ value }}' => 'foobar'), 'invalidValue');

        return false;
    }

    public static function validateStaticTwo($object, ExecutionContext $context)
    {
        $context->addViolation('Static message two', array('{{ value }}' => $object), 'invalidValue2');

        return false;
    }
}

class CallbackValidatorTest_Object
{
    public function validateOne(ExecutionContext $context)
    {
        $context->addViolation('My message', array('{{ value }}' => 'foobar'), 'invalidValue');

        return false;
    }

    public function validateTwo(ExecutionContext $context)
    {
        $context->addViolation('Other message', array('{{ value }}' => 'baz'), 'otherInvalidValue');

        return false;
    }

    public function validateProperty($property, ExecutionContext $context)
    {
        $context->addViolation('Property message', array('{{ value }}' => $property), 'invalidProperty');

        return false;
    }
}

class CallbackValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new CallbackValidator();
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

        $this->validator->validate(null, new Callback(array('foo')));
    }

    public function testCallbackSingleMethod()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(array('validateOne'));

        $this->context->expects($this->once())
            ->method('getCurrentObject')
            ->will($this->returnValue($object));
        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('My message', array(
                '{{ value }}' => 'foobar',
            ));

        $this->validator->validate($object, $constraint);
    }

    public function testCallbackSingleStaticMethod()
    {
        $object = new CallbackValidatorTest_Object();

        $this->context->expects($this->once())
            ->method('getCurrentObject')
            ->will($this->returnValue($object));
        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('Static message', array(
                '{{ value }}' => 'foobar',
            ));

        $this->validator->validate($object, new Callback(array(
            array(__CLASS__.'_Class', 'validateStatic')
        )));
    }

    public function testCallbackMultipleMethods()
    {
        $object = new CallbackValidatorTest_Object();

        $this->context->expects($this->once())
            ->method('getCurrentObject')
            ->will($this->returnValue($object));
        $this->context->expects($this->at(2))
            ->method('addViolation')
            ->with('My message', array(
                '{{ value }}' => 'foobar',
            ));
        $this->context->expects($this->at(3))
            ->method('addViolation')
            ->with('Other message', array(
                '{{ value }}' => 'baz',
            ));

        $this->validator->validate($object, new Callback(array(
            'validateOne', 'validateTwo'
        )));
    }

    public function testPropertyCallbackMethod()
    {
        $attribute = 'test value';
        $object = new CallbackValidatorTest_Object();

        $this->context->expects($this->once())
            ->method('getCurrentObject')
            ->will($this->returnValue($object));
        $this->context->expects($this->once())
            ->method('getCurrentProperty')
            ->will($this->returnValue('attribute'));
        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('Property message', array(
                '{{ value }}' => $attribute,
            ));

        $this->validator->validate($attribute, new Callback(array('validateProperty')));
    }

    public function testPropertyCallbackStaticMethod()
    {
        $attribute = 'test value';
        $object = new CallbackValidatorTest_Object();

        $this->context->expects($this->once())
            ->method('getCurrentObject')
            ->will($this->returnValue($object));
        $this->context->expects($this->once())
            ->method('getCurrentProperty')
            ->will($this->returnValue('attribute'));
        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('Static message two', array(
                '{{ value }}' => $attribute,
            ));

        $this->validator->validate($attribute, new Callback(array(
            array(__CLASS__.'_Class', 'validateStaticTwo')
        )));
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectCallbackArray()
    {
        $object = new CallbackValidatorTest_Object();

        $this->context->expects($this->once())
            ->method('getCurrentObject')
            ->will($this->returnValue($object));

        $this->validator->validate($object, new Callback('foobar'));
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testExpectValidMethods()
    {
        $object = new CallbackValidatorTest_Object();

        $this->context->expects($this->once())
            ->method('getCurrentObject')
            ->will($this->returnValue($object));

        $this->validator->validate($object, new Callback(array('foobar')));
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testExpectValidCallbacks()
    {
        $object = new CallbackValidatorTest_Object();

        $this->context->expects($this->once())
            ->method('getCurrentObject')
            ->will($this->returnValue($object));

        $this->validator->validate($object, new Callback(array(array('foo', 'bar'))));
    }

    public function testConstraintGetTargets()
    {
        $constraint = new Callback(array('foo'));

        $this->assertEquals(array('class', 'property'), $constraint->getTargets());
    }
}
