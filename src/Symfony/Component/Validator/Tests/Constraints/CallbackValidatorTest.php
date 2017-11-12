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

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\CallbackValidator;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

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

    public function testNullIsValid(): void
    {
        $this->validator->validate(null, new Callback());

        $this->assertNoViolation();
    }

    public function testSingleMethod(): void
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback('validate');

        $this->validator->validate($object, $constraint);

        $this->buildViolation('My message')
            ->setParameter('{{ value }}', 'foobar')
            ->assertRaised();
    }

    public function testSingleMethodExplicitName(): void
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(array('callback' => 'validate'));

        $this->validator->validate($object, $constraint);

        $this->buildViolation('My message')
            ->setParameter('{{ value }}', 'foobar')
            ->assertRaised();
    }

    public function testSingleStaticMethod(): void
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback('validateStatic');

        $this->validator->validate($object, $constraint);

        $this->buildViolation('Static message')
            ->setParameter('{{ value }}', 'baz')
            ->assertRaised();
    }

    public function testClosure(): void
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

    public function testClosureNullObject(): void
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

    public function testClosureExplicitName(): void
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

    public function testArrayCallable(): void
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(array(__CLASS__.'_Class', 'validateCallback'));

        $this->validator->validate($object, $constraint);

        $this->buildViolation('Callback message')
            ->setParameter('{{ value }}', 'foobar')
            ->assertRaised();
    }

    public function testArrayCallableNullObject(): void
    {
        $constraint = new Callback(array(__CLASS__.'_Class', 'validateCallback'));

        $this->validator->validate(null, $constraint);

        $this->buildViolation('Callback message')
            ->setParameter('{{ value }}', 'foobar')
            ->assertRaised();
    }

    public function testArrayCallableExplicitName(): void
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
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testExpectValidMethods(): void
    {
        $object = new CallbackValidatorTest_Object();

        $this->validator->validate($object, new Callback(array('callback' => array('foobar'))));
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testExpectValidCallbacks(): void
    {
        $object = new CallbackValidatorTest_Object();

        $this->validator->validate($object, new Callback(array('callback' => array('foo', 'bar'))));
    }

    public function testConstraintGetTargets(): void
    {
        $constraint = new Callback(array());
        $targets = array(Constraint::CLASS_CONSTRAINT, Constraint::PROPERTY_CONSTRAINT);

        $this->assertEquals($targets, $constraint->getTargets());
    }

    // Should succeed. Needed when defining constraints as annotations.
    public function testNoConstructorArguments(): void
    {
        $constraint = new Callback();

        $this->assertSame(array(Constraint::CLASS_CONSTRAINT, Constraint::PROPERTY_CONSTRAINT), $constraint->getTargets());
    }

    public function testAnnotationInvocationSingleValued(): void
    {
        $constraint = new Callback(array('value' => 'validateStatic'));

        $this->assertEquals(new Callback('validateStatic'), $constraint);
    }

    public function testAnnotationInvocationMultiValued(): void
    {
        $constraint = new Callback(array('value' => array(__CLASS__.'_Class', 'validateCallback')));

        $this->assertEquals(new Callback(array(__CLASS__.'_Class', 'validateCallback')), $constraint);
    }

    public function testPayloadIsPassedToCallback(): void
    {
        $object = new \stdClass();
        $payloadCopy = null;

        $constraint = new Callback(array(
            'callback' => function ($object, ExecutionContextInterface $constraint, $payload) use (&$payloadCopy): void {
                $payloadCopy = $payload;
            },
            'payload' => 'Hello world!',
        ));
        $this->validator->validate($object, $constraint);
        $this->assertEquals('Hello world!', $payloadCopy);

        $payloadCopy = null;
        $constraint = new Callback(array(
            'callback' => function ($object, ExecutionContextInterface $constraint, $payload) use (&$payloadCopy): void {
                $payloadCopy = $payload;
            },
        ));
        $this->validator->validate($object, $constraint);
        $this->assertNull($payloadCopy);
    }
}
