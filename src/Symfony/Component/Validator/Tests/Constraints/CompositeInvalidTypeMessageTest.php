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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Validation;

class CompositeInvalidTypeMessageTest extends TestCase
{
    #[DataProvider('provideInvalidValues')]
    public function testInvalidTypesUseTheCustomMessage(string $class, mixed $value)
    {
        $constraint = $this->createConstraint($class, 'Use a collection.');
        $violations = Validation::createValidator()->validate($value, $constraint);

        $this->assertCount(1, $violations);
        $this->assertSame('Use a collection.', $violations[0]->getMessage());
        $this->assertSame($class::INVALID_TYPE_ERROR, $violations[0]->getCode());
        $this->assertSame('INVALID_TYPE_ERROR', $class::getErrorName($violations[0]->getCode()));
        $this->assertSame($constraint, $violations[0]->getConstraint());
        $this->assertSame($value, $violations[0]->getInvalidValue());
    }

    #[DataProvider('provideInvalidValues')]
    public function testDefaultInvalidTypeMessageIsUnchanged(string $class, mixed $value)
    {
        $violations = Validation::createValidator()->validate($value, $this->createConstraint($class, null));

        $this->assertCount(1, $violations);
        $this->assertSame('This value should be of type {{ type }}.', $violations[0]->getMessageTemplate());
        $this->assertSame(['{{ type }}' => All::class === $class ? 'iterable' : 'array|(Traversable&ArrayAccess)'], $violations[0]->getParameters());
        $this->assertNull($violations[0]->getCode());
    }

    public static function provideInvalidValues(): iterable
    {
        foreach ([All::class, Collection::class] as $class) {
            yield $class.' string' => [$class, 'invalid'];
            yield $class.' integer' => [$class, 42];
            yield $class.' boolean' => [$class, false];
            yield $class.' object' => [$class, new \stdClass()];
        }
        yield 'Collection requires ArrayAccess too' => [Collection::class, new \EmptyIterator()];
    }

    #[DataProvider('provideConstraintClasses')]
    public function testAnEmptyCustomMessageDoesNotUseTheDefault(string $class)
    {
        $violations = Validation::createValidator()->validate('invalid', $this->createConstraint($class, ''));

        $this->assertCount(1, $violations);
        $this->assertSame('', $violations[0]->getMessage());
        $this->assertSame($class::INVALID_TYPE_ERROR, $violations[0]->getCode());
    }

    public function testInvalidTypeMessagesCanBeConfiguredWithAttributes()
    {
        $value = new class {
            #[All(new NotNull(), invalidTypeMessage: 'Use a list.')]
            public mixed $items = 'invalid';

            #[Collection(['foo' => new NotNull()], invalidTypeMessage: 'Use a map.')]
            public mixed $fields = 'invalid';
        };
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $violations = $validator->validate($value);

        $this->assertCount(2, $violations);
        $this->assertSame('items', $violations[0]->getPropertyPath());
        $this->assertSame('Use a list.', $violations[0]->getMessage());
        $this->assertSame('fields', $violations[1]->getPropertyPath());
        $this->assertSame('Use a map.', $violations[1]->getMessage());
    }

    #[DataProvider('provideConstraintClasses')]
    public function testNullRemainsValid(string $class)
    {
        $this->assertCount(0, Validation::createValidator()->validate(null, $this->createConstraint($class, 'Use a collection.')));
    }

    #[DataProvider('provideConstraintClasses')]
    public function testValidCollectionsKeepTheirNestedValidation(string $class)
    {
        $constraint = $this->createConstraint($class, 'Use a collection.');
        $validator = Validation::createValidator();

        foreach ([['foo' => 1], new \ArrayObject(['foo' => 1])] as $value) {
            $this->assertCount(0, $validator->validate($value, $constraint));
        }

        $violations = $validator->validate(['foo' => null], $constraint);
        $this->assertCount(1, $violations);
        $this->assertSame('[foo]', $violations[0]->getPropertyPath());
        $this->assertSame(NotNull::IS_NULL_ERROR, $violations[0]->getCode());
    }

    #[DataProvider('provideConstraintClasses')]
    public function testCustomMessagePreservesTheNestedPropertyPath(string $class)
    {
        $constraint = new Collection(['items' => $this->createConstraint($class, 'Use a collection.')]);
        $violations = Validation::createValidator()->validate(['items' => 'invalid'], $constraint);

        $this->assertCount(1, $violations);
        $this->assertSame('[items]', $violations[0]->getPropertyPath());
        $this->assertSame('Use a collection.', $violations[0]->getMessage());
        $this->assertSame($class::INVALID_TYPE_ERROR, $violations[0]->getCode());
    }

    public static function provideConstraintClasses(): iterable
    {
        yield 'All' => [All::class];
        yield 'Collection' => [Collection::class];
    }

    private function createConstraint(string $class, ?string $message): Constraint
    {
        if (null === $message) {
            return All::class === $class ? new All(new NotNull()) : new Collection(['foo' => new NotNull()]);
        }

        return All::class === $class
            ? new All(new NotNull(), invalidTypeMessage: $message)
            : new Collection(['foo' => new NotNull()], invalidTypeMessage: $message);
    }
}
