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

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Validator\Constraints\Expression;
use Symfony\Component\Validator\Constraints\ExpressionValidator;
use Symfony\Component\Validator\Constraints\IdenticalTo;
use Symfony\Component\Validator\Constraints\IsNull;
use Symfony\Component\Validator\Constraints\NotIdenticalTo;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Context\ExecutionContext;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\Tests\Fixtures\Entity;
use Symfony\Component\Validator\Tests\Fixtures\FakeMetadataFactory;
use Symfony\Component\Validator\Tests\Fixtures\ToString;
use Symfony\Component\Validator\ValidatorBuilder;
use Symfony\Contracts\Translation\TranslatorInterface;

class ExpressionValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator()
    {
        return new ExpressionValidator();
    }

    public function testExpressionIsEvaluatedWithNullValue()
    {
        $constraint = new Expression([
            'expression' => 'false',
            'message' => 'myMessage',
        ]);

        $this->validator->validate(null, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', 'null')
            ->setCode(Expression::EXPRESSION_FAILED_ERROR)
            ->assertRaised();
    }

    public function testExpressionIsEvaluatedWithEmptyStringValue()
    {
        $constraint = new Expression([
            'expression' => 'false',
            'message' => 'myMessage',
        ]);

        $this->validator->validate('', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '""')
            ->setCode(Expression::EXPRESSION_FAILED_ERROR)
            ->assertRaised();
    }

    public function testSucceedingExpressionAtObjectLevel()
    {
        $constraint = new Expression('this.data == 1');

        $object = new Entity();
        $object->data = '1';

        $this->setObject($object);

        $this->validator->validate($object, $constraint);

        $this->assertNoViolation();
    }

    public function testFailingExpressionAtObjectLevel()
    {
        $constraint = new Expression([
            'expression' => 'this.data == 1',
            'message' => 'myMessage',
        ]);

        $object = new Entity();
        $object->data = '2';

        $this->setObject($object);

        $this->validator->validate($object, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', 'object')
            ->setCode(Expression::EXPRESSION_FAILED_ERROR)
            ->assertRaised();
    }

    public function testSucceedingExpressionAtObjectLevelWithToString()
    {
        $constraint = new Expression('this.data == 1');

        $object = new ToString();
        $object->data = '1';

        $this->setObject($object);

        $this->validator->validate($object, $constraint);

        $this->assertNoViolation();
    }

    public function testFailingExpressionAtObjectLevelWithToString()
    {
        $constraint = new Expression([
            'expression' => 'this.data == 1',
            'message' => 'myMessage',
        ]);

        $object = new ToString();
        $object->data = '2';

        $this->setObject($object);

        $this->validator->validate($object, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', 'toString')
            ->setCode(Expression::EXPRESSION_FAILED_ERROR)
            ->assertRaised();
    }

    public function testSucceedingExpressionAtPropertyLevel()
    {
        $constraint = new Expression('value == this.data');

        $object = new Entity();
        $object->data = '1';

        $this->setRoot($object);
        $this->setPropertyPath('data');
        $this->setProperty($object, 'data');

        $this->validator->validate('1', $constraint);

        $this->assertNoViolation();
    }

    public function testFailingExpressionAtPropertyLevel()
    {
        $constraint = new Expression([
            'expression' => 'value == this.data',
            'message' => 'myMessage',
        ]);

        $object = new Entity();
        $object->data = '1';

        $this->setRoot($object);
        $this->setPropertyPath('data');
        $this->setProperty($object, 'data');

        $this->validator->validate('2', $constraint);

        $this->buildViolation('myMessage')
            ->atPath('data')
            ->setParameter('{{ value }}', '"2"')
            ->setCode(Expression::EXPRESSION_FAILED_ERROR)
            ->assertRaised();
    }

    public function testSucceedingExpressionAtNestedPropertyLevel()
    {
        $constraint = new Expression('value == this.data');

        $object = new Entity();
        $object->data = '1';

        $root = new Entity();
        $root->reference = $object;

        $this->setRoot($root);
        $this->setPropertyPath('reference.data');
        $this->setProperty($object, 'data');

        $this->validator->validate('1', $constraint);

        $this->assertNoViolation();
    }

    public function testFailingExpressionAtNestedPropertyLevel()
    {
        $constraint = new Expression([
            'expression' => 'value == this.data',
            'message' => 'myMessage',
        ]);

        $object = new Entity();
        $object->data = '1';

        $root = new Entity();
        $root->reference = $object;

        $this->setRoot($root);
        $this->setPropertyPath('reference.data');
        $this->setProperty($object, 'data');

        $this->validator->validate('2', $constraint);

        $this->buildViolation('myMessage')
            ->atPath('reference.data')
            ->setParameter('{{ value }}', '"2"')
            ->setCode(Expression::EXPRESSION_FAILED_ERROR)
            ->assertRaised();
    }

    /**
     * When validatePropertyValue() is called with a class name
     * https://github.com/symfony/symfony/pull/11498.
     */
    public function testSucceedingExpressionAtPropertyLevelWithoutRoot()
    {
        $constraint = new Expression('value == "1"');

        $this->setRoot('1');
        $this->setPropertyPath('');
        $this->setProperty(null, 'property');

        $this->validator->validate('1', $constraint);

        $this->assertNoViolation();
    }

    /**
     * When validatePropertyValue() is called with a class name
     * https://github.com/symfony/symfony/pull/11498.
     */
    public function testFailingExpressionAtPropertyLevelWithoutRoot()
    {
        $constraint = new Expression([
            'expression' => 'value == "1"',
            'message' => 'myMessage',
        ]);

        $this->setRoot('2');
        $this->setPropertyPath('');
        $this->setProperty(null, 'property');

        $this->validator->validate('2', $constraint);

        $this->buildViolation('myMessage')
            ->atPath('')
            ->setParameter('{{ value }}', '"2"')
            ->setCode(Expression::EXPRESSION_FAILED_ERROR)
            ->assertRaised();
    }

    public function testExpressionLanguageUsage()
    {
        $constraint = new Expression([
            'expression' => 'false',
        ]);

        $expressionLanguage = $this->getMockBuilder(ExpressionLanguage::class)->getMock();

        $used = false;

        $expressionLanguage->method('evaluate')
            ->willReturnCallback(function () use (&$used) {
                $used = true;

                return true;
            });

        $validator = new ExpressionValidator($expressionLanguage);
        $validator->initialize($this->createContext());
        $validator->validate(null, $constraint);

        $this->assertTrue($used, 'Failed asserting that custom ExpressionLanguage instance is used.');
    }

    /**
     * @group legacy
     * @expectedDeprecation The "Symfony\Component\ExpressionLanguage\ExpressionLanguage" instance should be passed as "Symfony\Component\Validator\Constraints\ExpressionValidator::__construct" first argument instead of second argument since 4.4.
     */
    public function testLegacyExpressionLanguageUsage()
    {
        $constraint = new Expression([
            'expression' => 'false',
        ]);

        $expressionLanguage = $this->getMockBuilder('Symfony\Component\ExpressionLanguage\ExpressionLanguage')->getMock();

        $used = false;

        $expressionLanguage->method('evaluate')
            ->willReturnCallback(function () use (&$used) {
                $used = true;

                return true;
            });

        $validator = new ExpressionValidator(null, $expressionLanguage);
        $validator->initialize($this->createContext());
        $validator->validate(null, $constraint);

        $this->assertTrue($used, 'Failed asserting that custom ExpressionLanguage instance is used.');
    }

    /**
     * @group legacy
     * @expectedDeprecation The "Symfony\Component\Validator\Constraints\ExpressionValidator::__construct" first argument must be an instance of "Symfony\Component\ExpressionLanguage\ExpressionLanguage" or null since 4.4. "string" given
     */
    public function testConstructorInvalidType()
    {
        new ExpressionValidator('foo');
    }

    public function testPassingCustomValues()
    {
        $constraint = new Expression([
            'expression' => 'value + custom == 2',
            'values' => [
                'custom' => 1,
            ],
        ]);

        $this->validator->validate(1, $constraint);

        $this->assertNoViolation();
    }

    public function testExistingIsValidFunctionIsNotOverridden()
    {
        $used = false;

        $el = $el = new ExpressionLanguage();
        $el->register('is_valid', function () {}, function () use (&$used) {
            $used = true;
        });

        $validator = new ExpressionValidator($el);
        $validator->initialize($this->context);

        $validator->validate('foo', new Expression('is_valid()'));

        $this->assertTrue($used);
    }

    /**
     * @dataProvider isValidFunctionWithInvalidArgumentsProvider
     */
    public function testIsValidFunctionWithInvalidArguments(string $expectedMessage, $value, string $expression, array $values)
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->validator->validate($value, new Expression([
            'expression' => $expression,
            'values' => $values,
        ]));
    }

    public function isValidFunctionWithInvalidArgumentsProvider()
    {
        return [
            ['The "is_valid" function requires at least one argument.', null, 'is_valid()', []],
            ['The "is_valid" function only accepts instances of "Symfony\Component\Validator\Constraint", arrays of "Symfony\Component\Validator\Constraint", or strings that represent properties paths (when validating an object), "ArrayIterator" given.', null, 'is_valid(a)', ['a' => new \ArrayIterator()]],
            ['The "is_valid" function only accepts instances of "Symfony\Component\Validator\Constraint", arrays of "Symfony\Component\Validator\Constraint", or strings that represent properties paths (when validating an object), "NULL" given.', null, 'is_valid(a)', ['a' => null]],
            ['The "is_valid" function only accepts arrays that contain instances of "Symfony\Component\Validator\Constraint" exclusively, "ArrayIterator" given.', null, 'is_valid(a)', ['a' => [new \ArrayIterator()]]],
            ['The "is_valid" function only accepts arrays that contain instances of "Symfony\Component\Validator\Constraint" exclusively, "string" given.', null, 'is_valid(a)', ['a' => ['foo']]],
            ['The "is_valid" function only accepts strings that represent properties paths when validating an object.', 'foo', 'is_valid("bar")', []],
        ];
    }

    /**
     * @dataProvider isValidFunctionProvider
     */
    public function testIsValidFunction(bool $shouldBeValid, $value, string $expression, array $values = [], string $group = null, array $propertiesConstraints = [])
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $validatorBuilder = new ValidatorBuilder();

        $classMetadata = null;
        if ($valueIsObject = \is_object($value)) {
            $classMetadata = new ClassMetadata(\get_class($value));
            foreach ($propertiesConstraints as $property => $constraints) {
                $classMetadata->addPropertyConstraints($property, $constraints);
            }

            $validatorBuilder->setMetadataFactory((new FakeMetadataFactory())->addMetadata($classMetadata));
        }

        $this->validator->initialize($executionContext = new ExecutionContext(
            $validatorBuilder->getValidator(),
            $this->root,
            $translator
        ));

        $executionContext->setConstraint($constraint = new Expression([
            'expression' => $expression,
            'values' => $values,
        ]));
        $executionContext->setGroup($group);
        $executionContext->setNode($value, $valueIsObject ? $value : null, $classMetadata, '');

        $this->validator->validate($value, $constraint);

        $this->assertSame($shouldBeValid, !(bool) $executionContext->getViolations()->count());
    }

    public function isValidFunctionProvider()
    {
        return [
            [true, 'foo', 'is_valid(a) or is_valid(b)', ['a' => new NotIdenticalTo('foo'), 'b' => new IdenticalTo('foo')]],
            [false, 'foo', 'is_valid(a) and is_valid(b)', ['a' => new NotIdenticalTo('foo'), 'b' => new IdenticalTo('foo')]],
            [false, 'foo', 'is_valid(a, b)', ['a' => new NotIdenticalTo('foo'), 'b' => new IdenticalTo('foo')]],
            [false, 'foo', 'is_valid(a)', ['a' => new NotIdenticalTo('foo')]],
            [true, 'foo', 'is_valid(a)', ['a' => [new IdenticalTo('foo')]]],
            [true, 'foo', 'is_valid(a)', ['a' => new NotIdenticalTo(['value' => 'foo', 'groups' => 'g1'])], 'g2'],
            [false, new TestExpressionValidatorObject(), 'is_valid("foo")', [], null, ['foo' => [new NotNull()]]],
            [true, new TestExpressionValidatorObject(), 'is_valid("foo")', [], null, ['foo' => [new IsNull()]]],
            [true, new TestExpressionValidatorObject(), 'is_valid("foo")'],
            [true, new TestExpressionValidatorObject(), 'is_valid("any string")'],
            [false, new TestExpressionValidatorObject(), 'is_valid("foo", a)', ['a' => new IsNull()], null, ['foo' => [new IsNull()]]],
            [true, new TestExpressionValidatorObject(), 'is_valid(a, "foo")', ['a' => new Type(TestExpressionValidatorObject::class)], null, ['foo' => [new IsNull()]]],
        ];
    }
}

final class TestExpressionValidatorObject
{
    public $foo = null;
}
