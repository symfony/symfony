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
    public static function validateCallback($object, ExecutionContext $context)
    {
        $context->addViolation('Callback message', array('{{ value }}' => 'foobar'), 'invalidValue');

        return false;
    }
}

class CallbackValidatorTest_Object
{
    public function validate(ExecutionContext $context)
    {
        $context->addViolation('My message', array('{{ value }}' => 'foobar'), 'invalidValue');

        return false;
    }

    public static function validateStatic($object, ExecutionContext $context)
    {
        $context->addViolation('Static message', array('{{ value }}' => 'baz'), 'otherInvalidValue');

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

    public function testSingleMethod()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback('validate');

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('My message', array(
                '{{ value }}' => 'foobar',
            ));

        $this->validator->validate($object, $constraint);
    }

    public function testSingleMethodExplicitName()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(array('callback' => 'validate'));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('My message', array(
                '{{ value }}' => 'foobar',
            ));

        $this->validator->validate($object, $constraint);
    }

    public function testSingleStaticMethod()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback('validateStatic');

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('Static message', array(
                '{{ value }}' => 'baz',
            ));

        $this->validator->validate($object, $constraint);
    }

    public function testClosure()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(function ($object, ExecutionContext $context) {
            $context->addViolation('My message', array('{{ value }}' => 'foobar'), 'invalidValue');

            return false;
        });

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('My message', array(
                '{{ value }}' => 'foobar',
            ));

        $this->validator->validate($object, $constraint);
    }

    public function testClosureExplicitName()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(array(
            'callback' => function ($object, ExecutionContext $context) {
                $context->addViolation('My message', array('{{ value }}' => 'foobar'), 'invalidValue');

                return false;
            },
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('My message', array(
                '{{ value }}' => 'foobar',
            ));

        $this->validator->validate($object, $constraint);
    }

    public function testArrayCallable()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(array(__CLASS__.'_Class', 'validateCallback'));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('Callback message', array(
                '{{ value }}' => 'foobar',
            ));

        $this->validator->validate($object, $constraint);
    }

    public function testArrayCallableExplicitName()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(array(
            'callback' => array(__CLASS__.'_Class', 'validateCallback'),
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('Callback message', array(
                '{{ value }}' => 'foobar',
            ));

        $this->validator->validate($object, $constraint);
    }

    // BC with Symfony < 2.4
    public function testSingleMethodBc()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(array('validate'));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('My message', array(
                '{{ value }}' => 'foobar',
            ));

        $this->validator->validate($object, $constraint);
    }

    // BC with Symfony < 2.4
    public function testSingleMethodBcExplicitName()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(array('methods' => array('validate')));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('My message', array(
                '{{ value }}' => 'foobar',
            ));

        $this->validator->validate($object, $constraint);
    }

    // BC with Symfony < 2.4
    public function testMultipleMethodsBc()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(array('validate', 'validateStatic'));

        $this->context->expects($this->at(0))
            ->method('addViolation')
            ->with('My message', array(
                '{{ value }}' => 'foobar',
            ));
        $this->context->expects($this->at(1))
            ->method('addViolation')
            ->with('Static message', array(
                '{{ value }}' => 'baz',
            ));

        $this->validator->validate($object, $constraint);
    }

    // BC with Symfony < 2.4
    public function testMultipleMethodsBcExplicitName()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(array(
            'methods' => array('validate', 'validateStatic'),
        ));

        $this->context->expects($this->at(0))
            ->method('addViolation')
            ->with('My message', array(
                '{{ value }}' => 'foobar',
            ));
        $this->context->expects($this->at(1))
            ->method('addViolation')
            ->with('Static message', array(
                '{{ value }}' => 'baz',
            ));

        $this->validator->validate($object, $constraint);
    }

    // BC with Symfony < 2.4
    public function testSingleStaticMethodBc()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(array(
            array(__CLASS__.'_Class', 'validateCallback')
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('Callback message', array(
                '{{ value }}' => 'foobar',
            ));

        $this->validator->validate($object, $constraint);
    }

    // BC with Symfony < 2.4
    public function testSingleStaticMethodBcExplicitName()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(array(
            'methods' => array(array(__CLASS__.'_Class', 'validateCallback')),
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('Callback message', array(
                '{{ value }}' => 'foobar',
            ));

        $this->validator->validate($object, $constraint);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testExpectValidMethods()
    {
        $object = new CallbackValidatorTest_Object();

        $this->validator->validate($object, new Callback(array('foobar')));
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testExpectValidCallbacks()
    {
        $object = new CallbackValidatorTest_Object();

        $this->validator->validate($object, new Callback(array(array('foo', 'bar'))));
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testExpectEitherCallbackOrMethods()
    {
        $object = new CallbackValidatorTest_Object();

        $this->validator->validate($object, new Callback(array(
            'callback' => 'validate',
            'methods' => array('validateStatic'),
        )));
    }

    public function testConstraintGetTargets()
    {
        $constraint = new Callback(array('foo'));

        $this->assertEquals('class', $constraint->getTargets());
    }

    // Should succeed. Needed when defining constraints as annotations.
    public function testNoConstructorArguments()
    {
        new Callback();
    }

    public function testAnnotationInvocationSingleValued()
    {
        $constraint = new Callback(array('value' => 'validateStatic'));

        $this->assertEquals(new Callback('validateStatic'), $constraint);
    }

    public function testAnnotationInvocationMultiValued()
    {
        $constraint = new Callback(array('value' => array(__CLASS__.'_Class', 'validateCallback')));

        $this->assertEquals(new Callback(array(__CLASS__.'_Class', 'validateCallback')), $constraint);
    }
}
