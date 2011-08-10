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

use Symfony\Component\Validator\ExecutionContext;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\CallbackValidator;

class CallbackValidatorTest_Class
{
    public static function validateStatic($object, ExecutionContext $context)
    {
        $context->setCurrentClass('Foo');
        $context->setCurrentProperty('bar');
        $context->setGroup('mygroup');
        $context->setPropertyPath('foo.bar');

        $context->addViolation('Static message', array('parameter'), 'invalidValue');
    }
}

class CallbackValidatorTest_Object
{
    public function validateOne(ExecutionContext $context)
    {
        $context->setCurrentClass('Foo');
        $context->setCurrentProperty('bar');
        $context->setGroup('mygroup');
        $context->setPropertyPath('foo.bar');

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

        $this->context = new ExecutionContext('Root', $this->walker, $metadataFactory);
        $this->context->setCurrentClass('InitialClass');
        $this->context->setCurrentProperty('initialProperty');
        $this->context->setGroup('InitialGroup');
        $this->context->setPropertyPath('initial.property.path');

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
        $this->assertEquals('InitialClass', $this->context->getCurrentClass());
        $this->assertEquals('initialProperty', $this->context->getCurrentProperty());
        $this->assertEquals('InitialGroup', $this->context->getGroup());
        $this->assertEquals('initial.property.path', $this->context->getPropertyPath());
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
        $this->assertEquals('InitialClass', $this->context->getCurrentClass());
        $this->assertEquals('initialProperty', $this->context->getCurrentProperty());
        $this->assertEquals('InitialGroup', $this->context->getGroup());
        $this->assertEquals('initial.property.path', $this->context->getPropertyPath());
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

        // context was reset
        $violations->add(new ConstraintViolation(
            'Other message',
            array('other parameter'),
            'Root',
            'initial.property.path',
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
