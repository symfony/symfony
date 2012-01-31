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

use Symfony\Component\Validator\GlobalExecutionContext;
use Symfony\Component\Validator\ExecutionContext;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\CallbackValidator;

class CallbackValidatorTest_Class
{
    public static function validateStatic($object, ExecutionContext $context)
    {
        $context->addViolation('Static message', array('parameter'), 'invalidValue');
    }
}

class CallbackValidatorTest_Object
{
    public function validateOne(ExecutionContext $context)
    {
        $context->addViolation('My message', array('parameter'), 'invalidValue');
    }

    public function validateTwo(ExecutionContext $context)
    {
        $context->addViolation('Other message', array('other parameter'), 'otherInvalidValue');
    }
}

class CallbackValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;
    protected $walker;
    protected $context;

    protected function setUp()
    {
        $this->walker = $this->getMock('Symfony\Component\Validator\GraphWalker', array(), array(), '', false);
        $metadataFactory = $this->getMock('Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface');
        $globalContext = new GlobalExecutionContext('Root', $this->walker, $metadataFactory);

        $this->context = new ExecutionContext($globalContext, 'value', 'foo.bar', 'Group', 'ClassName', 'propertyName');
        $this->validator = new CallbackValidator();
        $this->validator->initialize($this->context);
    }

    protected function tearDown()
    {
        $this->validator = null;
        $this->walker = null;
        $this->context = null;
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new Callback(array('foo'))));
    }

    public function testCallbackSingleMethod()
    {
        $object = new CallbackValidatorTest_Object();

        $this->assertTrue($this->validator->isValid($object, new Callback(array('validateOne'))));

        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            'My message',
            array('parameter'),
            'Root',
            'foo.bar',
            'invalidValue'
        ));

        $this->assertEquals($violations, $this->context->getViolations());
    }

    public function testCallbackSingleStaticMethod()
    {
        $object = new CallbackValidatorTest_Object();

        $this->assertTrue($this->validator->isValid($object, new Callback(array(
            array(__NAMESPACE__.'\CallbackValidatorTest_Class', 'validateStatic')
        ))));

        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            'Static message',
            array('parameter'),
            'Root',
            'foo.bar',
            'invalidValue'
        ));

        $this->assertEquals($violations, $this->context->getViolations());
    }

    public function testCallbackMultipleMethods()
    {
        $object = new CallbackValidatorTest_Object();

        $this->assertTrue($this->validator->isValid($object, new Callback(array(
            'validateOne', 'validateTwo'
        ))));

        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            'My message',
            array('parameter'),
            'Root',
            'foo.bar',
            'invalidValue'
        ));
        $violations->add(new ConstraintViolation(
            'Other message',
            array('other parameter'),
            'Root',
            'foo.bar',
            'otherInvalidValue'
        ));

        $this->assertEquals($violations, $this->context->getViolations());
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectCallbackArray()
    {
        $object = new CallbackValidatorTest_Object();

        $this->validator->isValid($object, new Callback('foobar'));
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testExpectValidMethods()
    {
        $object = new CallbackValidatorTest_Object();

        $this->validator->isValid($object, new Callback(array('foobar')));
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testExpectValidCallbacks()
    {
        $object = new CallbackValidatorTest_Object();

        $this->validator->isValid($object, new Callback(array(array('foo', 'bar'))));
    }

    public function testConstraintGetTargets()
    {
        $constraint = new Callback(array('foo'));

        $this->assertEquals('class', $constraint->getTargets());
    }
}
