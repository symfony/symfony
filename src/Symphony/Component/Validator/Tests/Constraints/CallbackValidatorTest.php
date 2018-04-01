<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Tests\Constraints;

use Symphony\Component\Validator\Constraint;
use Symphony\Component\Validator\Constraints\Callback;
use Symphony\Component\Validator\Constraints\CallbackValidator;
use Symphony\Component\Validator\Context\ExecutionContextInterface;
use Symphony\Component\Validator\Test\ConstraintValidatorTestCase;

class CallbackValidatorTest_Class
{
    public static function validateCallback($object, ExecutionContextInterface $context)
    {
        $context->addViolation('Callback message', array('{{ value }}' => 'foobar'));

        return false;
    }
}

class CallbackValidatorTest_Object
{
    public function validate(ExecutionContextInterface $context)
    {
        $context->addViolation('My message', array('{{ value }}' => 'foobar'));

        return false;
    }

    public static function validateStatic($object, ExecutionContextInterface $context)
    {
        $context->addViolation('Static message', array('{{ value }}' => 'baz'));

        return false;
    }
}

class CallbackValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator()
    {
        return new CallbackValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Callback());

        $this->assertNoViolation();
    }

    public function testSingleMethod()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback('validate');

        $this->validator->validate($object, $constraint);

        $this->buildViolation('My message')
            ->setParameter('{{ value }}', 'foobar')
            ->assertRaised();
    }

    public function testSingleMethodExplicitName()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(array('callback' => 'validate'));

        $this->validator->validate($object, $constraint);

        $this->buildViolation('My message')
            ->setParameter('{{ value }}', 'foobar')
            ->assertRaised();
    }

    public function testSingleStaticMethod()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback('validateStatic');

        $this->validator->validate($object, $constraint);

        $this->buildViolation('Static message')
            ->setParameter('{{ value }}', 'baz')
            ->assertRaised();
    }

    public function testClosure()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(function ($object, ExecutionContextInterface $context) {
            $context->addViolation('My message', array('{{ value }}' => 'foobar'));

            return false;
        });

        $this->validator->validate($object, $constraint);

        $this->buildViolation('My message')
            ->setParameter('{{ value }}', 'foobar')
            ->assertRaised();
    }

    public function testClosureNullObject()
    {
        $constraint = new Callback(function ($object, ExecutionContextInterface $context) {
            $context->addViolation('My message', array('{{ value }}' => 'foobar'));

            return false;
        });

        $this->validator->validate(null, $constraint);

        $this->buildViolation('My message')
            ->setParameter('{{ value }}', 'foobar')
            ->assertRaised();
    }

    public function testClosureExplicitName()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(array(
            'callback' => function ($object, ExecutionContextInterface $context) {
                $context->addViolation('My message', array('{{ value }}' => 'foobar'));

                return false;
            },
        ));

        $this->validator->validate($object, $constraint);

        $this->buildViolation('My message')
            ->setParameter('{{ value }}', 'foobar')
            ->assertRaised();
    }

    public function testArrayCallable()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(array(__CLASS__.'_Class', 'validateCallback'));

        $this->validator->validate($object, $constraint);

        $this->buildViolation('Callback message')
            ->setParameter('{{ value }}', 'foobar')
            ->assertRaised();
    }

    public function testArrayCallableNullObject()
    {
        $constraint = new Callback(array(__CLASS__.'_Class', 'validateCallback'));

        $this->validator->validate(null, $constraint);

        $this->buildViolation('Callback message')
            ->setParameter('{{ value }}', 'foobar')
            ->assertRaised();
    }

    public function testArrayCallableExplicitName()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(array(
            'callback' => array(__CLASS__.'_Class', 'validateCallback'),
        ));

        $this->validator->validate($object, $constraint);

        $this->buildViolation('Callback message')
            ->setParameter('{{ value }}', 'foobar')
            ->assertRaised();
    }

    /**
     * @expectedException \Symphony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testExpectValidMethods()
    {
        $object = new CallbackValidatorTest_Object();

        $this->validator->validate($object, new Callback(array('callback' => array('foobar'))));
    }

    /**
     * @expectedException \Symphony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testExpectValidCallbacks()
    {
        $object = new CallbackValidatorTest_Object();

        $this->validator->validate($object, new Callback(array('callback' => array('foo', 'bar'))));
    }

    public function testConstraintGetTargets()
    {
        $constraint = new Callback(array());
        $targets = array(Constraint::CLASS_CONSTRAINT, Constraint::PROPERTY_CONSTRAINT);

        $this->assertEquals($targets, $constraint->getTargets());
    }

    // Should succeed. Needed when defining constraints as annotations.
    public function testNoConstructorArguments()
    {
        $constraint = new Callback();

        $this->assertSame(array(Constraint::CLASS_CONSTRAINT, Constraint::PROPERTY_CONSTRAINT), $constraint->getTargets());
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

    public function testPayloadIsPassedToCallback()
    {
        $object = new \stdClass();
        $payloadCopy = null;

        $constraint = new Callback(array(
            'callback' => function ($object, ExecutionContextInterface $constraint, $payload) use (&$payloadCopy) {
                $payloadCopy = $payload;
            },
            'payload' => 'Hello world!',
        ));
        $this->validator->validate($object, $constraint);
        $this->assertEquals('Hello world!', $payloadCopy);

        $payloadCopy = null;
        $constraint = new Callback(array(
            'callback' => function ($object, ExecutionContextInterface $constraint, $payload) use (&$payloadCopy) {
                $payloadCopy = $payload;
            },
        ));
        $this->validator->validate($object, $constraint);
        $this->assertNull($payloadCopy);
    }
}
