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
use Symfony\Component\Validator\ExecutionContextInterface;

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

class CallbackValidatorTest extends AbstractConstraintValidatorTest
{
    protected function createValidator()
    {
        return new CallbackValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Callback(array('foo')));

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

        $this->assertViolation('My message', array(
            '{{ value }}' => 'foobar',
        ));
    }

    public function testSingleStaticMethod()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback('validateStatic');

        $this->validator->validate($object, $constraint);

        $this->assertViolation('Static message', array(
            '{{ value }}' => 'baz',
        ));
    }

    public function testClosure()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(function ($object, ExecutionContextInterface $context) {
            $context->addViolation('My message', array('{{ value }}' => 'foobar'));

            return false;
        });

        $this->validator->validate($object, $constraint);

        $this->assertViolation('My message', array(
            '{{ value }}' => 'foobar',
        ));
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

        $this->assertViolation('My message', array(
            '{{ value }}' => 'foobar',
        ));
    }

    public function testArrayCallable()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(array(__CLASS__.'_Class', 'validateCallback'));

        $this->validator->validate($object, $constraint);

        $this->assertViolation('Callback message', array(
            '{{ value }}' => 'foobar',
        ));
    }

    public function testArrayCallableExplicitName()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(array(
            'callback' => array(__CLASS__.'_Class', 'validateCallback'),
        ));

        $this->validator->validate($object, $constraint);

        $this->assertViolation('Callback message', array(
            '{{ value }}' => 'foobar',
        ));
    }

    // BC with Symfony < 2.4
    public function testSingleMethodBc()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(array('validate'));

        $this->validator->validate($object, $constraint);

        $this->assertViolation('My message', array(
            '{{ value }}' => 'foobar',
        ));
    }

    // BC with Symfony < 2.4
    public function testSingleMethodBcExplicitName()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(array('methods' => array('validate')));

        $this->validator->validate($object, $constraint);

        $this->buildViolation('My message')
            ->setParameter('{{ value }}', 'foobar')
            ->assertRaised();
    }

    // BC with Symfony < 2.4
    public function testMultipleMethodsBc()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(array('validate', 'validateStatic'));

        $this->validator->validate($object, $constraint);

        $this->buildViolation('My message')
            ->setParameter('{{ value }}', 'foobar')
            ->buildNextViolation('Static message')
            ->setParameter('{{ value }}', 'baz')
            ->assertRaised();
    }

    // BC with Symfony < 2.4
    public function testMultipleMethodsBcExplicitName()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(array(
            'methods' => array('validate', 'validateStatic'),
        ));

        $this->validator->validate($object, $constraint);

        $this->buildViolation('My message')
            ->setParameter('{{ value }}', 'foobar')
            ->buildNextViolation('Static message')
            ->setParameter('{{ value }}', 'baz')
            ->assertRaised();
    }

    // BC with Symfony < 2.4
    public function testSingleStaticMethodBc()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(array(
            array(__CLASS__.'_Class', 'validateCallback'),
        ));

        $this->validator->validate($object, $constraint);

        $this->buildViolation('Callback message')
            ->setParameter('{{ value }}', 'foobar')
            ->assertRaised();
    }

    // BC with Symfony < 2.4
    public function testSingleStaticMethodBcExplicitName()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(array(
            'methods' => array(array(__CLASS__.'_Class', 'validateCallback')),
        ));

        $this->validator->validate($object, $constraint);

        $this->buildViolation('Callback message')
            ->setParameter('{{ value }}', 'foobar')
            ->assertRaised();
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

        $this->assertEquals(Constraint::CLASS_CONSTRAINT, $constraint->getTargets());
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
