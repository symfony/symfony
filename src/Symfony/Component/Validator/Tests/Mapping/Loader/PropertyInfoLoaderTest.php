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
use Symfony\Component\PropertyInfo\PropertyAccessExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\Type as LegacyType;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Iban;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type as TypeConstraint;
use Symfony\Component\Validator\Mapping\AutoMappingStrategy;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\PropertyInfoLoader;
use Symfony\Component\Validator\Mapping\PropertyMetadata;
use Symfony\Component\Validator\Tests\Fixtures\NestedAttribute\Entity;
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
        $propertyListExtractor = $this->createMock(PropertyListExtractorInterface::class);
        $propertyListExtractor
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

        $propertyTypeExtractor = new class implements PropertyTypeExtractorInterface {
            private int $i = 0;
            private int $j = 0;
            private array $types;
            private array $legacyTypes;

            public function getType(string $class, string $property, array $context = []): ?Type
            {
                $this->types ??= [
                    Type::nullable(Type::string()),
                    Type::string(),
                    Type::union(Type::string(), Type::int(), Type::bool(), Type::null()),
                    Type::nullable(Type::object(Entity::class)),
                    Type::nullable(Type::array(Type::object(Entity::class))),
                    Type::nullable(Type::array()),
                    Type::nullable(Type::float()), // The existing constraint is float
                    Type::nullable(Type::string()),
                    Type::nullable(Type::string()),
                    Type::nullable(Type::array(Type::float())),
                    Type::string(),
                    Type::string(),
                ];

                $type = $this->types[$this->i];
                ++$this->i;

                return $type;
            }

            public function getTypes(string $class, string $property, array $context = []): ?array
            {
                $this->legacyTypes ??= [
                    [new LegacyType('string', true)],
                    [new LegacyType('string')],
                    [new LegacyType('string', true), new LegacyType('int'), new LegacyType('bool')],
                    [new LegacyType('object', true, Entity::class)],
                    [new LegacyType('array', true, null, true, null, new LegacyType('object', false, Entity::class))],
                    [new LegacyType('array', true, null, true)],
                    [new LegacyType('float', true)], // The existing constraint is float
                    [new LegacyType('string', true)],
                    [new LegacyType('string', true)],
                    [new LegacyType('array', true, null, true, null, new LegacyType('float'))],
                    [new LegacyType('string')],
                    [new LegacyType('string')],
                ];

                $legacyType = $this->legacyTypes[$this->j];
                ++$this->j;

                return $legacyType;
            }
        };

        $propertyAccessExtractor = $this->createMock(PropertyAccessExtractorInterface::class);
        $propertyAccessExtractor
            ->method('isWritable')
            ->willReturn(
                true,
                true,
                true,
                true,
                true,
                true,
                true,
                true,
                true,
                true,
                false,
                true
            )
        ;

        $propertyInfoLoader = new PropertyInfoLoader($propertyListExtractor, $propertyTypeExtractor, $propertyAccessExtractor, '{.*}');

        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->addLoader($propertyInfoLoader)
            ->getValidator()
        ;

        $classMetadata = $validator->getMetadataFor(new PropertyInfoLoaderEntity());

        $nullableStringMetadata = $classMetadata->getPropertyMetadata('nullableString');
        $this->assertCount(1, $nullableStringMetadata);
        $nullableStringConstraints = $nullableStringMetadata[0]->getConstraints();
        $this->assertCount(1, $nullableStringConstraints);
        $this->assertInstanceOf(TypeConstraint::class, $nullableStringConstraints[0]);
        $this->assertSame('string', $nullableStringConstraints[0]->type);

        $stringMetadata = $classMetadata->getPropertyMetadata('string');
        $this->assertCount(1, $stringMetadata);
        $stringConstraints = $stringMetadata[0]->getConstraints();
        $this->assertCount(2, $stringConstraints);
        $this->assertInstanceOf(TypeConstraint::class, $stringConstraints[0]);
        $this->assertSame('string', $stringConstraints[0]->type);
        $this->assertInstanceOf(NotNull::class, $stringConstraints[1]);

        $scalarMetadata = $classMetadata->getPropertyMetadata('scalar');
        $this->assertCount(1, $scalarMetadata);
        $scalarConstraints = $scalarMetadata[0]->getConstraints();
        $this->assertCount(1, $scalarConstraints);
        $this->assertInstanceOf(TypeConstraint::class, $scalarConstraints[0]);
        $this->assertSame('scalar', $scalarConstraints[0]->type);

        $objectMetadata = $classMetadata->getPropertyMetadata('object');
        $this->assertCount(1, $objectMetadata);
        $objectConstraints = $objectMetadata[0]->getConstraints();
        $this->assertCount(1, $objectConstraints);
        $this->assertInstanceOf(TypeConstraint::class, $objectConstraints[0]);
        $this->assertSame(Entity::class, $objectConstraints[0]->type);

        $collectionMetadata = $classMetadata->getPropertyMetadata('collection');
        $this->assertCount(1, $collectionMetadata);
        $collectionConstraints = $collectionMetadata[0]->getConstraints();
        $this->assertCount(2, $collectionConstraints);
        $this->assertInstanceOf(All::class, $collectionConstraints[0]);
        $this->assertInstanceOf(NotNull::class, $collectionConstraints[0]->constraints[0]);
        $this->assertInstanceOf(TypeConstraint::class, $collectionConstraints[0]->constraints[1]);
        $this->assertSame(Entity::class, $collectionConstraints[0]->constraints[1]->type);

        $collectionOfUnknownMetadata = $classMetadata->getPropertyMetadata('collectionOfUnknown');
        $this->assertCount(1, $collectionOfUnknownMetadata);
        $collectionOfUnknownConstraints = $collectionOfUnknownMetadata[0]->getConstraints();
        $this->assertCount(1, $collectionOfUnknownConstraints);
        $this->assertInstanceOf(TypeConstraint::class, $collectionOfUnknownConstraints[0]);
        $this->assertSame('array', $collectionOfUnknownConstraints[0]->type);

        $alreadyMappedTypeMetadata = $classMetadata->getPropertyMetadata('alreadyMappedType');
        $this->assertCount(1, $alreadyMappedTypeMetadata);
        $alreadyMappedTypeConstraints = $alreadyMappedTypeMetadata[0]->getConstraints();
        $this->assertCount(1, $alreadyMappedTypeMetadata);
        $this->assertInstanceOf(TypeConstraint::class, $alreadyMappedTypeConstraints[0]);

        $alreadyMappedNotNullMetadata = $classMetadata->getPropertyMetadata('alreadyMappedNotNull');
        $this->assertCount(1, $alreadyMappedNotNullMetadata);
        $alreadyMappedNotNullConstraints = $alreadyMappedNotNullMetadata[0]->getConstraints();
        $this->assertCount(1, $alreadyMappedNotNullMetadata);
        $this->assertInstanceOf(NotNull::class, $alreadyMappedNotNullConstraints[0]);

        $alreadyMappedNotBlankMetadata = $classMetadata->getPropertyMetadata('alreadyMappedNotBlank');
        $this->assertCount(1, $alreadyMappedNotBlankMetadata);
        $alreadyMappedNotBlankConstraints = $alreadyMappedNotBlankMetadata[0]->getConstraints();
        $this->assertCount(1, $alreadyMappedNotBlankMetadata);
        $this->assertInstanceOf(NotBlank::class, $alreadyMappedNotBlankConstraints[0]);

        $alreadyPartiallyMappedCollectionMetadata = $classMetadata->getPropertyMetadata('alreadyPartiallyMappedCollection');
        $this->assertCount(1, $alreadyPartiallyMappedCollectionMetadata);
        $alreadyPartiallyMappedCollectionConstraints = $alreadyPartiallyMappedCollectionMetadata[0]->getConstraints();
        $this->assertCount(2, $alreadyPartiallyMappedCollectionConstraints);
        $this->assertInstanceOf(All::class, $alreadyPartiallyMappedCollectionConstraints[0]);
        $this->assertInstanceOf(TypeConstraint::class, $alreadyPartiallyMappedCollectionConstraints[0]->constraints[0]);
        $this->assertSame('string', $alreadyPartiallyMappedCollectionConstraints[0]->constraints[0]->type);
        $this->assertInstanceOf(Iban::class, $alreadyPartiallyMappedCollectionConstraints[0]->constraints[1]);

        $readOnlyMetadata = $classMetadata->getPropertyMetadata('readOnly');
        $this->assertEmpty($readOnlyMetadata);

        /** @var PropertyMetadata[] $noAutoMappingMetadata */
        $noAutoMappingMetadata = $classMetadata->getPropertyMetadata('noAutoMapping');
        $this->assertCount(1, $noAutoMappingMetadata);
        $this->assertSame(AutoMappingStrategy::DISABLED, $noAutoMappingMetadata[0]->getAutoMappingStrategy());
        $noAutoMappingConstraints = $noAutoMappingMetadata[0]->getConstraints();
        $this->assertCount(0, $noAutoMappingConstraints, 'DisableAutoMapping constraint is not added in the list');
    }

    /**
     * @dataProvider regexpProvider
     */
    public function testClassValidator(bool $expected, ?string $classValidatorRegexp = null)
    {
        $propertyListExtractor = $this->createMock(PropertyListExtractorInterface::class);
        $propertyListExtractor
            ->method('getProperties')
            ->willReturn(['string'])
        ;

        $propertyTypeExtractor = new class implements PropertyTypeExtractorInterface {
            public function getType(string $class, string $property, array $context = []): ?Type
            {
                return Type::string();
            }

            public function getTypes(string $class, string $property, array $context = []): ?array
            {
                return [new LegacyType('string')];
            }
        };

        $propertyAccessExtractor = $this->createMock(PropertyAccessExtractorInterface::class);

        $propertyInfoLoader = new PropertyInfoLoader($propertyListExtractor, $propertyTypeExtractor, $propertyAccessExtractor, $classValidatorRegexp);

        $classMetadata = new ClassMetadata(PropertyInfoLoaderEntity::class);
        $this->assertSame($expected, $propertyInfoLoader->loadClassMetadata($classMetadata));
    }

    public static function regexpProvider(): array
    {
        return [
            [false, null],
            [true, '{.*}'],
            [true, '{^'.preg_quote(PropertyInfoLoaderEntity::class).'$|^'.preg_quote(Entity::class).'$}'],
            [false, '{^'.preg_quote(Entity::class).'$}'],
        ];
    }

    public function testClassNoAutoMapping(?PropertyTypeExtractorInterface $propertyListExtractor = null)
    {
        if (null === $propertyListExtractor) {
            $propertyListExtractor = $this->createMock(PropertyListExtractorInterface::class);
            $propertyListExtractor
                ->method('getProperties')
                ->willReturn(['string', 'autoMappingExplicitlyEnabled'])
            ;

            $propertyTypeExtractor = new class implements PropertyTypeExtractorInterface {
                public function getType(string $class, string $property, array $context = []): ?Type
                {
                    return Type::string();
                }

                public function getTypes(string $class, string $property, array $context = []): ?array
                {
                    return [new LegacyType('string')];
                }
            };
        }

        $propertyAccessExtractor = $this->createMock(PropertyAccessExtractorInterface::class);

        $propertyInfoLoader = new PropertyInfoLoader($propertyListExtractor, $propertyTypeExtractor, $propertyAccessExtractor, '{.*}');
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->addLoader($propertyInfoLoader)
            ->getValidator()
        ;

        /** @var ClassMetadata $classMetadata */
        $classMetadata = $validator->getMetadataFor(new PropertyInfoLoaderNoAutoMappingEntity());
        $this->assertEmpty($classMetadata->getPropertyMetadata('string'));
        $this->assertCount(2, $classMetadata->getPropertyMetadata('autoMappingExplicitlyEnabled')[0]->constraints);
        $this->assertSame(AutoMappingStrategy::ENABLED, $classMetadata->getPropertyMetadata('autoMappingExplicitlyEnabled')[0]->getAutoMappingStrategy());
    }
}
