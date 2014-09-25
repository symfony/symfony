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

    public static function validateStatic(ExecutionContextInterface $context)
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
        $constraint = new Callback(array('validate'));

        $this->validator->validate($object, $constraint);

        $this->buildViolation('My message')
            ->setParameter('{{ value }}', 'foobar')
            ->assertRaised();
    }

    public function testSingleMethodExplicitName()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(array('methods' => array('validate')));

        $this->validator->validate($object, $constraint);

        $this->buildViolation('My message')
            ->setParameter('{{ value }}', 'foobar')
            ->assertRaised();
    }

    public function testMultipleMethods()
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

    public function testMultipleMethodsExplicitName()
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

    public function testSingleStaticMethod()
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

    public function testSingleStaticMethodExplicitName()
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

    public function testConstraintGetTargets()
    {
        $constraint = new Callback(array('foo'));

        $this->assertEquals(Constraint::CLASS_CONSTRAINT, $constraint->getTargets());
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\MissingOptionsException
     */
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
