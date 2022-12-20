<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Mapping\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Iban;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type as TypeConstraint;
use Symfony\Component\Validator\Mapping\AutoMappingStrategy;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\PropertyInfoLoader;
use Symfony\Component\Validator\Mapping\PropertyMetadata;
use Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity;
use Symfony\Component\Validator\Tests\Fixtures\PropertyInfoLoaderEntity;
use Symfony\Component\Validator\Tests\Fixtures\PropertyInfoLoaderNoAutoMappingEntity;
use Symfony\Component\Validator\Validation;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PropertyInfoLoaderTest extends TestCase
{
    public function testLoadClassMetadata()
    {
        $propertyInfoStub = self::createMock(PropertyInfoExtractorInterface::class);
        $propertyInfoStub
            ->method('getProperties')
            ->willReturn([
                'nullableString',
                'string',
                'scalar',
                'object',
                'collection',
                'collectionOfUnknown',
                'alreadyMappedType',
                'alreadyMappedNotNull',
                'alreadyMappedNotBlank',
                'alreadyPartiallyMappedCollection',
                'readOnly',
                'nonExistentField',
                'noAutoMapping',
            ])
        ;
        $propertyInfoStub
            ->method('getTypes')
            ->will(self::onConsecutiveCalls(
                [new Type(Type::BUILTIN_TYPE_STRING, true)],
                [new Type(Type::BUILTIN_TYPE_STRING)],
                [new Type(Type::BUILTIN_TYPE_STRING, true), new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_BOOL)],
                [new Type(Type::BUILTIN_TYPE_OBJECT, true, Entity::class)],
                [new Type(Type::BUILTIN_TYPE_ARRAY, true, null, true, null, new Type(Type::BUILTIN_TYPE_OBJECT, false, Entity::class))],
                [new Type(Type::BUILTIN_TYPE_ARRAY, true, null, true)],
                [new Type(Type::BUILTIN_TYPE_FLOAT, true)],
                // The existing constraint is float
                [new Type(Type::BUILTIN_TYPE_STRING, true)],
                [new Type(Type::BUILTIN_TYPE_STRING, true)],
                [new Type(Type::BUILTIN_TYPE_ARRAY, true, null, true, null, new Type(Type::BUILTIN_TYPE_FLOAT))],
                [new Type(Type::BUILTIN_TYPE_STRING)],
                [new Type(Type::BUILTIN_TYPE_STRING)]
            ))
        ;
        $propertyInfoStub
            ->method('isWritable')
            ->will(self::onConsecutiveCalls(true, true, true, true, true, true, true, true, true, true, false, true))
        ;

        $propertyInfoLoader = new PropertyInfoLoader($propertyInfoStub, $propertyInfoStub, $propertyInfoStub, '{.*}');

        $validator = Validation::createValidatorBuilder()
            ->enableAnnotationMapping(true)
            ->addDefaultDoctrineAnnotationReader()
            ->addLoader($propertyInfoLoader)
            ->getValidator()
        ;

        $classMetadata = $validator->getMetadataFor(new PropertyInfoLoaderEntity());

        $nullableStringMetadata = $classMetadata->getPropertyMetadata('nullableString');
        self::assertCount(1, $nullableStringMetadata);
        $nullableStringConstraints = $nullableStringMetadata[0]->getConstraints();
        self::assertCount(1, $nullableStringConstraints);
        self::assertInstanceOf(TypeConstraint::class, $nullableStringConstraints[0]);
        self::assertSame('string', $nullableStringConstraints[0]->type);

        $stringMetadata = $classMetadata->getPropertyMetadata('string');
        self::assertCount(1, $stringMetadata);
        $stringConstraints = $stringMetadata[0]->getConstraints();
        self::assertCount(2, $stringConstraints);
        self::assertInstanceOf(TypeConstraint::class, $stringConstraints[0]);
        self::assertSame('string', $stringConstraints[0]->type);
        self::assertInstanceOf(NotNull::class, $stringConstraints[1]);

        $scalarMetadata = $classMetadata->getPropertyMetadata('scalar');
        self::assertCount(1, $scalarMetadata);
        $scalarConstraints = $scalarMetadata[0]->getConstraints();
        self::assertCount(1, $scalarConstraints);
        self::assertInstanceOf(TypeConstraint::class, $scalarConstraints[0]);
        self::assertSame('scalar', $scalarConstraints[0]->type);

        $objectMetadata = $classMetadata->getPropertyMetadata('object');
        self::assertCount(1, $objectMetadata);
        $objectConstraints = $objectMetadata[0]->getConstraints();
        self::assertCount(1, $objectConstraints);
        self::assertInstanceOf(TypeConstraint::class, $objectConstraints[0]);
        self::assertSame(Entity::class, $objectConstraints[0]->type);

        $collectionMetadata = $classMetadata->getPropertyMetadata('collection');
        self::assertCount(1, $collectionMetadata);
        $collectionConstraints = $collectionMetadata[0]->getConstraints();
        self::assertCount(2, $collectionConstraints);
        self::assertInstanceOf(All::class, $collectionConstraints[0]);
        self::assertInstanceOf(NotNull::class, $collectionConstraints[0]->constraints[0]);
        self::assertInstanceOf(TypeConstraint::class, $collectionConstraints[0]->constraints[1]);
        self::assertSame(Entity::class, $collectionConstraints[0]->constraints[1]->type);

        $collectionOfUnknownMetadata = $classMetadata->getPropertyMetadata('collectionOfUnknown');
        self::assertCount(1, $collectionOfUnknownMetadata);
        $collectionOfUnknownConstraints = $collectionOfUnknownMetadata[0]->getConstraints();
        self::assertCount(1, $collectionOfUnknownConstraints);
        self::assertInstanceOf(TypeConstraint::class, $collectionOfUnknownConstraints[0]);
        self::assertSame('array', $collectionOfUnknownConstraints[0]->type);

        $alreadyMappedTypeMetadata = $classMetadata->getPropertyMetadata('alreadyMappedType');
        self::assertCount(1, $alreadyMappedTypeMetadata);
        $alreadyMappedTypeConstraints = $alreadyMappedTypeMetadata[0]->getConstraints();
        self::assertCount(1, $alreadyMappedTypeMetadata);
        self::assertInstanceOf(TypeConstraint::class, $alreadyMappedTypeConstraints[0]);

        $alreadyMappedNotNullMetadata = $classMetadata->getPropertyMetadata('alreadyMappedNotNull');
        self::assertCount(1, $alreadyMappedNotNullMetadata);
        $alreadyMappedNotNullConstraints = $alreadyMappedNotNullMetadata[0]->getConstraints();
        self::assertCount(1, $alreadyMappedNotNullMetadata);
        self::assertInstanceOf(NotNull::class, $alreadyMappedNotNullConstraints[0]);

        $alreadyMappedNotBlankMetadata = $classMetadata->getPropertyMetadata('alreadyMappedNotBlank');
        self::assertCount(1, $alreadyMappedNotBlankMetadata);
        $alreadyMappedNotBlankConstraints = $alreadyMappedNotBlankMetadata[0]->getConstraints();
        self::assertCount(1, $alreadyMappedNotBlankMetadata);
        self::assertInstanceOf(NotBlank::class, $alreadyMappedNotBlankConstraints[0]);

        $alreadyPartiallyMappedCollectionMetadata = $classMetadata->getPropertyMetadata('alreadyPartiallyMappedCollection');
        self::assertCount(1, $alreadyPartiallyMappedCollectionMetadata);
        $alreadyPartiallyMappedCollectionConstraints = $alreadyPartiallyMappedCollectionMetadata[0]->getConstraints();
        self::assertCount(2, $alreadyPartiallyMappedCollectionConstraints);
        self::assertInstanceOf(All::class, $alreadyPartiallyMappedCollectionConstraints[0]);
        self::assertInstanceOf(TypeConstraint::class, $alreadyPartiallyMappedCollectionConstraints[0]->constraints[0]);
        self::assertSame('string', $alreadyPartiallyMappedCollectionConstraints[0]->constraints[0]->type);
        self::assertInstanceOf(Iban::class, $alreadyPartiallyMappedCollectionConstraints[0]->constraints[1]);
        self::assertInstanceOf(NotNull::class, $alreadyPartiallyMappedCollectionConstraints[0]->constraints[2]);

        $readOnlyMetadata = $classMetadata->getPropertyMetadata('readOnly');
        self::assertEmpty($readOnlyMetadata);

        /** @var PropertyMetadata[] $noAutoMappingMetadata */
        $noAutoMappingMetadata = $classMetadata->getPropertyMetadata('noAutoMapping');
        self::assertCount(1, $noAutoMappingMetadata);
        self::assertSame(AutoMappingStrategy::DISABLED, $noAutoMappingMetadata[0]->getAutoMappingStrategy());
        $noAutoMappingConstraints = $noAutoMappingMetadata[0]->getConstraints();
        self::assertCount(0, $noAutoMappingConstraints, 'DisableAutoMapping constraint is not added in the list');
    }

    /**
     * @dataProvider regexpProvider
     */
    public function testClassValidator(bool $expected, string $classValidatorRegexp = null)
    {
        $propertyInfoStub = self::createMock(PropertyInfoExtractorInterface::class);
        $propertyInfoStub
            ->method('getProperties')
            ->willReturn(['string'])
        ;
        $propertyInfoStub
            ->method('getTypes')
            ->willReturn([new Type(Type::BUILTIN_TYPE_STRING)])
        ;

        $propertyInfoLoader = new PropertyInfoLoader($propertyInfoStub, $propertyInfoStub, $propertyInfoStub, $classValidatorRegexp);

        $classMetadata = new ClassMetadata(PropertyInfoLoaderEntity::class);
        self::assertSame($expected, $propertyInfoLoader->loadClassMetadata($classMetadata));
    }

    public function regexpProvider()
    {
        return [
            [false, null],
            [true, '{.*}'],
            [true, '{^'.preg_quote(PropertyInfoLoaderEntity::class).'$|^'.preg_quote(Entity::class).'$}'],
            [false, '{^'.preg_quote(Entity::class).'$}'],
        ];
    }

    public function testClassNoAutoMapping()
    {
        $propertyInfoStub = self::createMock(PropertyInfoExtractorInterface::class);
        $propertyInfoStub
            ->method('getProperties')
            ->willReturn(['string', 'autoMappingExplicitlyEnabled'])
        ;
        $propertyInfoStub
            ->method('getTypes')
            ->willReturnOnConsecutiveCalls(
                [new Type(Type::BUILTIN_TYPE_STRING)],
                [new Type(Type::BUILTIN_TYPE_BOOL)]
            );

        $propertyInfoLoader = new PropertyInfoLoader($propertyInfoStub, $propertyInfoStub, $propertyInfoStub, '{.*}');
        $validator = Validation::createValidatorBuilder()
            ->enableAnnotationMapping(true)
            ->addDefaultDoctrineAnnotationReader()
            ->addLoader($propertyInfoLoader)
            ->getValidator()
        ;

        /** @var ClassMetadata $classMetadata */
        $classMetadata = $validator->getMetadataFor(new PropertyInfoLoaderNoAutoMappingEntity());
        self::assertEmpty($classMetadata->getPropertyMetadata('string'));
        self::assertCount(2, $classMetadata->getPropertyMetadata('autoMappingExplicitlyEnabled')[0]->constraints);
        self::assertSame(AutoMappingStrategy::ENABLED, $classMetadata->getPropertyMetadata('autoMappingExplicitlyEnabled')[0]->getAutoMappingStrategy());
    }
}
