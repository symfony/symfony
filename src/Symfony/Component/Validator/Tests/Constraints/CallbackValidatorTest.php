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

use PHPUnit\Framework\Attributes\RequiresPhp;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\CallbackValidator;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\Tests\Constraints\Fixtures\CallbackTestWithClosure;

class CallbackValidatorTest_Class
{
    public static function validateCallback($object, ExecutionContextInterface $context)
    {
        $context->addViolation('Callback message', ['{{ value }}' => 'foobar']);

        return false;
    }
}

class CallbackValidatorTest_Object
{
    public $data;

    public function validate(ExecutionContextInterface $context)
    {
        $context->addViolation('My message', ['{{ value }}' => 'foobar']);

        return false;
    }

    public static function validateStatic($object, ExecutionContextInterface $context)
    {
        $context->addViolation('Static message', ['{{ value }}' => 'baz']);

        return false;
    }

    public function isNeverSatisfied(ExecutionContextInterface $context)
    {
        return false;
    }
}

class CallbackValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): CallbackValidator
    {
        return new CallbackValidator();
    }

    public function testNullIsValid()
    {
        $this->validate(null, new Callback());

        $this->assertNoViolation();
    }

    public function testSingleMethod()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback('validate');

        $this->validate($object, $constraint);

        $this->buildViolation('My message')
            ->setParameter('{{ value }}', 'foobar')
            ->assertRaised();
    }

    public function testSingleMethodExplicitName()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(callback: 'validate');

        $this->validate($object, $constraint);

        $this->buildViolation('My message')
            ->setParameter('{{ value }}', 'foobar')
            ->assertRaised();
    }

    public function testSingleStaticMethod()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback('validateStatic');

        $this->validate($object, $constraint);

        $this->buildViolation('Static message')
            ->setParameter('{{ value }}', 'baz')
            ->assertRaised();
    }

    public function testClosure()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(static function ($object, ExecutionContextInterface $context) {
            $context->addViolation('My message', ['{{ value }}' => 'foobar']);

            return false;
        });

        $this->validate($object, $constraint);

        $this->buildViolation('My message')
            ->setParameter('{{ value }}', 'foobar')
            ->assertRaised();
    }

    public function testClosureNullObject()
    {
        $constraint = new Callback(static function ($object, ExecutionContextInterface $context) {
            $context->addViolation('My message', ['{{ value }}' => 'foobar']);

            return false;
        });

        $this->validate(null, $constraint);

        $this->buildViolation('My message')
            ->setParameter('{{ value }}', 'foobar')
            ->assertRaised();
    }

    public function testClosureExplicitName()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(callback: static function ($object, ExecutionContextInterface $context) {
            $context->addViolation('My message', ['{{ value }}' => 'foobar']);

            return false;
        });

        $this->validate($object, $constraint);

        $this->buildViolation('My message')
            ->setParameter('{{ value }}', 'foobar')
            ->assertRaised();
    }

    public function testArrayCallable()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback([__CLASS__.'_Class', 'validateCallback']);

        $this->validate($object, $constraint);

        $this->buildViolation('Callback message')
            ->setParameter('{{ value }}', 'foobar')
            ->assertRaised();
    }

    public function testArrayCallableNullObject()
    {
        $constraint = new Callback([__CLASS__.'_Class', 'validateCallback']);

        $this->validate(null, $constraint);

        $this->buildViolation('Callback message')
            ->setParameter('{{ value }}', 'foobar')
            ->assertRaised();
    }

    public function testArrayCallableExplicitName()
    {
        $object = new CallbackValidatorTest_Object();
        $constraint = new Callback(callback: [__CLASS__.'_Class', 'validateCallback']);

        $this->validate($object, $constraint);

        $this->buildViolation('Callback message')
            ->setParameter('{{ value }}', 'foobar')
            ->assertRaised();
    }

    public function testConstraintGetTargets()
    {
        $constraint = new Callback(callback: 'strtolower');
        $targets = [Constraint::CLASS_CONSTRAINT, Constraint::PROPERTY_CONSTRAINT];

        $this->assertEquals($targets, $constraint->getTargets());
    }

    // Should succeed. Needed when defining constraints as attributes.
    public function testNoConstructorArguments()
    {
        $constraint = new Callback();

        $this->assertSame([Constraint::CLASS_CONSTRAINT, Constraint::PROPERTY_CONSTRAINT], $constraint->getTargets());
    }

    public function testAttributeInvocationSingleValued()
    {
        $constraint = new Callback(callback: 'validateStatic');

        $this->assertEquals(new Callback(callback: 'validateStatic'), $constraint);
    }

    public function testAttributeInvocationMultiValued()
    {
        $constraint = new Callback(callback: [__CLASS__.'_Class', 'validateCallback']);

        $this->assertEquals(new Callback(callback: [__CLASS__.'_Class', 'validateCallback']), $constraint);
    }

    public function testPayloadIsPassedToCallback()
    {
        $object = new \stdClass();
        $payloadCopy = 'Replace me!';
        $callback = static function ($object, ExecutionContextInterface $constraint, $payload) use (&$payloadCopy) {
            $payloadCopy = $payload;
        };

        $constraint = new Callback(
            callback: $callback,
            payload: 'Hello world!',
        );
        $this->validate($object, $constraint);
        $this->assertEquals('Hello world!', $payloadCopy);

        $payloadCopy = 'Replace me!';
        $constraint = new Callback(callback: $callback, payload: 'Hello world!');
        $this->validate($object, $constraint);
        $this->assertEquals('Hello world!', $payloadCopy);

        $payloadCopy = 'Replace me!';
        $constraint = new Callback(callback: $callback);
        $this->validate($object, $constraint);
        $this->assertNull($payloadCopy);
    }

    public function testMessageDefaultAndCustomValues()
    {
        $constraint = new Callback(static fn (): bool => true);

        $this->assertNull($constraint->message);

        $constraint = new Callback(static fn (): bool => true, message: 'myMessage');

        $this->assertSame('myMessage', $constraint->message);
    }

    public function testSatisfiedClosure()
    {
        $this->validate('foobar', new Callback(static fn ($value): bool => 'foobar' === $value, message: 'myMessage'));

        $this->assertNoViolation();
    }

    public function testNotSatisfiedClosure()
    {
        $this->validate('foobar', new Callback(static fn ($value): bool => 'other' === $value, message: 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"foobar"')
            ->setCode(Callback::NOT_SATISFIED_ERROR)
            ->assertRaised();
    }

    public function testReturnValueIsIgnoredWhenNoMessageIsConfigured()
    {
        $this->validate('foobar', new Callback(static fn (): bool => false));

        $this->assertNoViolation();
    }

    public function testNotSatisfiedClosureWithCustomMessage()
    {
        $this->validate(42, new Callback(static fn (): bool => false, message: 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '42')
            ->setCode(Callback::NOT_SATISFIED_ERROR)
            ->assertRaised();
    }

    public function testClosureCanUseTheObjectOfTheContext()
    {
        $object = new \stdClass();
        $object->min = 10;
        $this->setObject($object);

        $constraint = new Callback(static fn ($value, ExecutionContextInterface $context): bool => $value >= $context->getObject()->min, message: 'This value is not valid.');

        $this->validate(15, $constraint);
        $this->assertNoViolation();

        $this->validate(5, $constraint);
        $this->buildViolation('This value is not valid.')
            ->setParameter('{{ value }}', '5')
            ->setCode(Callback::NOT_SATISFIED_ERROR)
            ->assertRaised();
    }

    public function testNotSatisfiedMethod()
    {
        $object = new CallbackValidatorTest_Object();

        $this->validate($object, new Callback('isNeverSatisfied', message: 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', 'object')
            ->setCode(Callback::NOT_SATISFIED_ERROR)
            ->assertRaised();
    }

    public function testNonBooleanReturnIsRejectedWhenMessageIsSet()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The callback targeted by the Callback constraint on "property.path" must return a boolean when the "message" option is set, "int" returned.');

        $this->validator->validate(new \stdClass(), new Callback(static fn () => 0, message: 'myMessage'));
    }

    public function testNonBooleanReturnNamesTheOffendingProperty()
    {
        $this->setProperty(new CallbackValidatorTest_Object(), 'data');

        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage(\sprintf('The callback targeted by the Callback constraint on "%s::$data" must return a boolean when the "message" option is set, "int" returned.', CallbackValidatorTest_Object::class));

        $this->validator->validate(new \stdClass(), new Callback(static fn () => 0, message: 'myMessage'));
    }

    public function testNonBooleanReturnIsIgnoredWhenMessageIsNotSet()
    {
        $this->validator->validate(new \stdClass(), new Callback(static fn () => 0));

        $this->assertNoViolation();
    }

    #[RequiresPhp('>=8.5.0')]
    public function testClosureInAttribute()
    {
        $metadata = new ClassMetadata(CallbackTestWithClosure::class);
        $loader = new AttributeLoader();
        $this->assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->getPropertyMetadata('a')[0]->getConstraints();
        $this->assertInstanceOf(Callback::class, $aConstraint);
        $this->assertInstanceOf(\Closure::class, $aConstraint->callback);
        $this->assertNull($aConstraint->message);
        $this->assertSame(['Default', 'CallbackTestWithClosure'], $aConstraint->groups);
        $this->assertTrue(($aConstraint->callback)('valid'));
        $this->assertFalse(($aConstraint->callback)('invalid'));

        [$bConstraint] = $metadata->getPropertyMetadata('b')[0]->getConstraints();
        $this->assertInstanceOf(Callback::class, $bConstraint);
        $this->assertSame('myMessage', $bConstraint->message);
        $this->assertSame(['my_group'], $bConstraint->groups);
        $this->assertSame('some attached data', $bConstraint->payload);
        $this->assertTrue(($bConstraint->callback)('valid'));
    }
}
