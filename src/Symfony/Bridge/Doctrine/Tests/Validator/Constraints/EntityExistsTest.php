<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Validator\Constraints;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Validator\Constraints\EntityExists;
use Symfony\Bridge\Doctrine\Validator\Constraints\EntityExistsValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;

class EntityExistsTest extends TestCase
{
    public function testAttributeWithDefaultOptions()
    {
        $metadata = new ClassMetadata(EntityExistsDummyOne::class);
        $loader = new AttributeLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$constraint] = $metadata->getPropertyMetadata('entityId')[0]->getConstraints();
        self::assertInstanceOf(EntityExists::class, $constraint);
        self::assertSame('Symfony\\Bridge\\Doctrine\\Tests\\Fixtures\\SingleIntIdEntity', $constraint->entityClass);
        self::assertSame(EntityExistsValidator::class, $constraint->validatedBy());
        self::assertNull($constraint->em);
        self::assertNull($constraint->identifierField);
        self::assertNull($constraint->repositoryMethod);
        self::assertSame(['Default', 'EntityExistsDummyOne'], $constraint->groups);
        self::assertSame('This value is not valid.', $constraint->invalidMessage);
    }

    public function testAttributeWithIdentifierField()
    {
        $metadata = new ClassMetadata(EntityExistsDummyTwo::class);
        $loader = new AttributeLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$constraint] = $metadata->getPropertyMetadata('identifier')[0]->getConstraints();
        self::assertInstanceOf(EntityExists::class, $constraint);
        self::assertSame('App\\Entity\\MyEntity', $constraint->entityClass);
        self::assertSame('myMessage', $constraint->message);
        self::assertSame('my_own_entity_manager', $constraint->em);
        self::assertSame('uuid', $constraint->identifierField);
        self::assertNull($constraint->repositoryMethod);
        self::assertSame(['custom_group'], $constraint->groups);
        self::assertSame('payload', $constraint->payload);
    }

    public function testAttributeWithRepositoryMethod()
    {
        $metadata = new ClassMetadata(EntityExistsDummyThree::class);
        $loader = new AttributeLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$constraint] = $metadata->getPropertyMetadata('identifier')[0]->getConstraints();
        self::assertInstanceOf(EntityExists::class, $constraint);
        self::assertSame('App\\Entity\\MyEntity', $constraint->entityClass);
        self::assertSame('findByCustom', $constraint->repositoryMethod);
        self::assertNull($constraint->identifierField);
    }

    public function testAttributeWithInvalidMessage()
    {
        $metadata = new ClassMetadata(EntityExistsDummyFour::class);
        $loader = new AttributeLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$constraint] = $metadata->getPropertyMetadata('entityId')[0]->getConstraints();
        self::assertInstanceOf(EntityExists::class, $constraint);
        self::assertSame('myInvalidMessage', $constraint->invalidMessage);
    }

    public function testThrowsWhenIdentifierFieldAndRepositoryMethodAreSetTogether()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The "identifierField" and "repositoryMethod" options cannot be used together.');

        new EntityExists(
            entityClass: 'App\\Entity\\MyEntity',
            identifierField: 'id',
            repositoryMethod: 'findByCustom',
        );
    }
}

class EntityExistsDummyOne
{
    #[EntityExists(entityClass: 'Symfony\\Bridge\\Doctrine\\Tests\\Fixtures\\SingleIntIdEntity')]
    public int $entityId;
}

class EntityExistsDummyTwo
{
    #[EntityExists(entityClass: 'App\\Entity\\MyEntity', message: 'myMessage', em: 'my_own_entity_manager', identifierField: 'uuid', groups: ['custom_group'], payload: 'payload')]
    public string $identifier;
}

class EntityExistsDummyThree
{
    #[EntityExists(entityClass: 'App\\Entity\\MyEntity', repositoryMethod: 'findByCustom')]
    public string $identifier;
}

class EntityExistsDummyFour
{
    #[EntityExists(entityClass: 'Symfony\\Bridge\\Doctrine\\Tests\\Fixtures\\SingleIntIdEntity', invalidMessage: 'myInvalidMessage')]
    public int $entityId;
}
