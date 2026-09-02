<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Mapping\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyPath;
use Symfony\Component\Serializer\Exception\MappingException;
use Symfony\Component\Serializer\Mapping\AttributeMetadata;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorMapping;
use Symfony\Component\Serializer\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Mapping\Loader\LoaderChain;
use Symfony\Component\Serializer\Mapping\Loader\LoaderChainAwareInterface;
use Symfony\Component\Serializer\Mapping\Loader\LoaderInterface;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\AbstractDummy;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\AbstractDummyFirstChild;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\AbstractDummySecondChild;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\AbstractDummyThirdChild;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\AccessorishGetters;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\BadAttributeDummy;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\BadMethodContextDummy;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\ContextDummyParent;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\ContextDummyPromotedProperties;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\Entity45016;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\GroupClassDummy;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\GroupDummy;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\GroupDummyParent;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\IgnoreDummy;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\IgnoreDummyAdditionalGetter;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\IgnoreDummyAdditionalGetterWithoutIgnoreAttributes;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\MaxDepthDummy;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\SerializedNameDummy;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\SerializedPathDummy;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\SerializedPathInConstructorDummy;
use Symfony\Component\Serializer\Tests\Mapping\Loader\Features\ContextMappingTestTrait;
use Symfony\Component\Serializer\Tests\Mapping\TestClassMetadataFactory;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class AttributeLoaderTest extends TestCase
{
    use ContextMappingTestTrait;

    protected AttributeLoader $loader;

    protected function setUp(): void
    {
        $this->loader = new AttributeLoader();
    }

    public function testInterface()
    {
        $this->assertInstanceOf(LoaderInterface::class, $this->loader);
    }

    public function testLoadClassMetadataReturnsTrueIfSuccessful()
    {
        $classMetadata = new ClassMetadata(GroupDummy::class);

        $this->assertTrue($this->loader->loadClassMetadata($classMetadata));
    }

    public function testLoadGroups()
    {
        $classMetadata = new ClassMetadata(GroupDummy::class);
        $this->loader->loadClassMetadata($classMetadata);

        $this->assertEquals(TestClassMetadataFactory::createClassMetadata('Symfony\Component\Serializer\Tests\Fixtures\Attributes'), $classMetadata);
    }

    public function testLoadDiscriminatorMap()
    {
        $classMetadata = new ClassMetadata(AbstractDummy::class);
        $this->loader->loadClassMetadata($classMetadata);

        $expected = new ClassMetadata(AbstractDummy::class, new ClassDiscriminatorMapping('type', [
            'first' => AbstractDummyFirstChild::class,
            'second' => AbstractDummySecondChild::class,
            'third' => AbstractDummyThirdChild::class,
        ], 'third'));

        $expected->addAttributeMetadata(new AttributeMetadata('foo'));
        $expected->getReflectionClass();

        $this->assertEquals($expected, $classMetadata);
    }

    public function testLoadDiscriminatorMapTypesFromMappedChildClasses()
    {
        $loader = new AttributeLoader(false, [], [
            _DiscriminatorMapParent::class => [
                'first' => _DiscriminatorMapFirstChild::class,
                'second' => _DiscriminatorMapSecondChild::class,
            ],
        ]);
        $classMetadata = new ClassMetadata(_DiscriminatorMapParent::class);

        $this->assertTrue($loader->loadClassMetadata($classMetadata));
        $this->assertEquals(new ClassDiscriminatorMapping('type', [
            'first' => _DiscriminatorMapFirstChild::class,
            'second' => _DiscriminatorMapSecondChild::class,
        ]), $classMetadata->getClassDiscriminatorMapping());
    }

    public function testDiscriminatorMapTypeCanBeReconciledAfterAnotherLoaderProvidesTheMap()
    {
        $loader = new LoaderChain([
            new AttributeLoader(false, [], [_DeferredDiscriminatorParent::class => ['child' => _DeferredDiscriminatorChild::class]]),
            new class implements LoaderInterface {
                public function loadClassMetadata(ClassMetadataInterface $metadata): bool
                {
                    $metadata->setClassDiscriminatorMapping(new ClassDiscriminatorMapping('type', ['parent' => _DeferredDiscriminatorParent::class]));

                    return true;
                }
            },
        ]);
        $metadata = new ClassMetadata(_DeferredDiscriminatorParent::class);

        $this->assertTrue($loader->loadClassMetadata($metadata));
        $this->assertSame(['child' => _DeferredDiscriminatorChild::class, 'parent' => _DeferredDiscriminatorParent::class], $metadata->getClassDiscriminatorMapping()->getTypesMapping());
    }

    public function testLoaderChainAwareLifecycle()
    {
        $events = [];
        $awareLoader = new class($events) implements LoaderInterface, LoaderChainAwareInterface {
            public function __construct(private array &$events)
            {
            }

            public function prepareLoading(ClassMetadataInterface $metadata): void
            {
                $this->events[] = 'prepare';
            }

            public function loadClassMetadata(ClassMetadataInterface $metadata): bool
            {
                $this->events[] = 'aware';

                return true;
            }

            public function finalizeLoading(ClassMetadataInterface $metadata): void
            {
                $this->events[] = 'finalize';
            }
        };
        $ordinaryLoader = new class($events) implements LoaderInterface {
            public function __construct(private array &$events)
            {
            }

            public function loadClassMetadata(ClassMetadataInterface $metadata): bool
            {
                $this->events[] = 'ordinary';

                return false;
            }
        };

        $this->assertTrue((new LoaderChain([$awareLoader, $ordinaryLoader]))->loadClassMetadata(new ClassMetadata(_DeferredDiscriminatorParent::class)));
        $this->assertSame(['prepare', 'aware', 'ordinary', 'finalize'], $events);
    }

    public function testDiscriminatorMapTypeSurvivesALaterLoaderReplacingTheMap()
    {
        $loader = new LoaderChain([
            new AttributeLoader(false, [], [_DiscriminatorMapParent::class => ['first' => _DiscriminatorMapFirstChild::class]]),
            new class implements LoaderInterface {
                public function loadClassMetadata(ClassMetadataInterface $metadata): bool
                {
                    $metadata->setClassDiscriminatorMapping(new ClassDiscriminatorMapping('kind', ['second' => _DiscriminatorMapSecondChild::class]));

                    return true;
                }
            },
        ]);
        $metadata = new ClassMetadata(_DiscriminatorMapParent::class);

        $loader->loadClassMetadata($metadata);

        $mapping = $metadata->getClassDiscriminatorMapping();
        $this->assertSame('kind', $mapping->getTypeProperty());
        $this->assertSame(['second' => _DiscriminatorMapSecondChild::class, 'first' => _DiscriminatorMapFirstChild::class], $mapping->getTypesMapping());
    }

    public function testLoadDiscriminatorMapTypeThroughChainRejectsTargetWithoutDiscriminatorMap()
    {
        $loader = new LoaderChain([
            new AttributeLoader(false, [], [_DeferredDiscriminatorParent::class => ['child' => _DeferredDiscriminatorChild::class]]),
        ]);

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(\sprintf('Class "%s" cannot add discriminator map type "child" for "%s" because the target does not declare a discriminator map.', _DeferredDiscriminatorChild::class, _DeferredDiscriminatorParent::class));

        $loader->loadClassMetadata(new ClassMetadata(_DeferredDiscriminatorParent::class));
    }

    public function testLoadDiscriminatorMapTypeThroughChainAfterAFailedLoad()
    {
        $loader = new LoaderChain([
            new AttributeLoader(false, [], [_DeferredDiscriminatorParent::class => ['child' => _DeferredDiscriminatorChild::class]]),
            new class implements LoaderInterface {
                private bool $failNext = true;

                public function loadClassMetadata(ClassMetadataInterface $metadata): bool
                {
                    if ($this->failNext) {
                        $this->failNext = false;

                        throw new MappingException('Transient failure.');
                    }
                    $metadata->setClassDiscriminatorMapping(new ClassDiscriminatorMapping('type', []));

                    return true;
                }
            },
        ]);

        try {
            $loader->loadClassMetadata(new ClassMetadata(_DeferredDiscriminatorParent::class));
            $this->fail('The first load should have failed.');
        } catch (MappingException $e) {
            $this->assertSame('Transient failure.', $e->getMessage());
        }

        $metadata = new ClassMetadata(_DeferredDiscriminatorParent::class);
        $loader->loadClassMetadata($metadata);

        $this->assertSame(['child' => _DeferredDiscriminatorChild::class], $metadata->getClassDiscriminatorMapping()->getTypesMapping());
    }

    public function testLoadDiscriminatorMapTypeRejectsContributorMissingFromTheLoaderRegistry()
    {
        $loader = new AttributeLoader(true, [], []);

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(\sprintf('Class "%s" cannot add discriminator map type "first" for "%s" because it is not registered with the loader.', _DiscriminatorMapFirstChild::class, _DiscriminatorMapParent::class));

        $loader->loadClassMetadata(new ClassMetadata(_DiscriminatorMapFirstChild::class));
    }

    public function testLoadDiscriminatorMapTypeIsIgnoredWithoutALoaderRegistry()
    {
        $loader = new AttributeLoader();

        $this->assertTrue($loader->loadClassMetadata(new ClassMetadata(_DiscriminatorMapFirstChild::class)));
    }

    public function testLoadDiscriminatorMapTypeForInterface()
    {
        $loader = new AttributeLoader(false, [], [
            _DiscriminatorMapInterface::class => ['implementation' => _DiscriminatorMapImplementation::class],
        ]);
        $classMetadata = new ClassMetadata(_DiscriminatorMapInterface::class);

        $loader->loadClassMetadata($classMetadata);

        $this->assertEquals(new ClassDiscriminatorMapping('type', [
            'implementation' => _DiscriminatorMapImplementation::class,
        ]), $classMetadata->getClassDiscriminatorMapping());
    }

    public function testMappedDiscriminatorMapTypeDoesNotLoadChildAttributeMetadata()
    {
        $loader = new AttributeLoader(false, [], [
            _DiscriminatorMapParent::class => ['with_metadata' => _DiscriminatorMapChildWithPropertyMetadata::class],
        ]);
        $classMetadata = new ClassMetadata(_DiscriminatorMapParent::class);

        $loader->loadClassMetadata($classMetadata);

        $this->assertSame([], $classMetadata->getAttributesMetadata());
    }

    public function testLoadMultipleDiscriminatorMapTypesFromOneMappedChildClass()
    {
        $loader = new AttributeLoader(false, [], [
            _DiscriminatorMapParent::class => [
                'first_alias' => _DiscriminatorMapMultipleTypesChild::class,
                'second_alias' => _DiscriminatorMapMultipleTypesChild::class,
            ],
        ]);
        $classMetadata = new ClassMetadata(_DiscriminatorMapParent::class);

        $loader->loadClassMetadata($classMetadata);

        $this->assertEquals(new ClassDiscriminatorMapping('type', [
            'first_alias' => _DiscriminatorMapMultipleTypesChild::class,
            'second_alias' => _DiscriminatorMapMultipleTypesChild::class,
        ]), $classMetadata->getClassDiscriminatorMapping());
    }

    public function testLoadEmptyDiscriminatorMapWithoutMappedChildClassesThrows()
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(\sprintf('Discriminator map for "%s" cannot be empty.', _DiscriminatorMapParent::class));

        (new AttributeLoader())->loadClassMetadata(new ClassMetadata(_DiscriminatorMapParent::class));
    }

    public function testLoadDiscriminatorMapRequiresContributedDefaultTypeToExist()
    {
        $loader = new AttributeLoader(false, [], [
            _DiscriminatorMapWithContributedDefault::class => ['first' => _DiscriminatorMapFirstDefaultChild::class],
        ]);

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Default type "second"');
        $loader->loadClassMetadata(new ClassMetadata(_DiscriminatorMapWithContributedDefault::class));
    }

    public function testLoadDiscriminatorMapAllowsContributedDefaultTypeWithInlineMapping()
    {
        $loader = new AttributeLoader(false, [], [
            _DiscriminatorMapWithInlineMappingAndContributedDefault::class => ['second' => _DiscriminatorMapContributedDefaultChild::class],
        ]);
        $classMetadata = new ClassMetadata(_DiscriminatorMapWithInlineMappingAndContributedDefault::class);

        $loader->loadClassMetadata($classMetadata);

        $mapping = $classMetadata->getClassDiscriminatorMapping();
        $this->assertSame('second', $mapping->getDefaultType());
        $this->assertSame(_DiscriminatorMapContributedDefaultChild::class, $mapping->getClassForType('second'));
    }

    public function testChildDiscriminatorMapDoesNotReplaceTheMapItContributesTo()
    {
        $loader = new AttributeLoader(false, [], [
            _DiscriminatorMapParent::class => ['nested' => _NestedDiscriminatorMapChild::class],
        ]);
        $classMetadata = new ClassMetadata(_DiscriminatorMapParent::class);

        $loader->loadClassMetadata($classMetadata);

        $mapping = $classMetadata->getClassDiscriminatorMapping();
        $this->assertSame('type', $mapping->getTypeProperty());
        $this->assertSame(_NestedDiscriminatorMapChild::class, $mapping->getClassForType('nested'));
    }

    public function testLoadDiscriminatorMapTypeRejectsConflictingDuplicateTypes()
    {
        $loader = new AttributeLoader(false, [], [
            AbstractDummy::class => ['first' => AbstractDummySecondChild::class],
        ]);

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(\sprintf('Discriminator map type "first" for "%s" is already mapped to "%s".', AbstractDummy::class, AbstractDummyFirstChild::class));
        $loader->loadClassMetadata(new ClassMetadata(AbstractDummy::class));
    }

    public function testLoadDiscriminatorMapTypeToleratesAContributionMatchingTheInlineMapping()
    {
        $loader = new AttributeLoader(false, [], [
            AbstractDummy::class => ['first' => AbstractDummyFirstChild::class],
        ]);
        $classMetadata = new ClassMetadata(AbstractDummy::class);

        $loader->loadClassMetadata($classMetadata);

        $this->assertSame(AbstractDummyFirstChild::class, $classMetadata->getClassDiscriminatorMapping()->getClassForType('first'));
    }

    public function testLoadDiscriminatorMapTypeRejectsClassThatIsNotASubtype()
    {
        $loader = new AttributeLoader(false, [], [
            _DiscriminatorMapParent::class => ['unrelated' => _UnrelatedDiscriminatorMapType::class],
        ]);

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(\sprintf('Class "%s" cannot add discriminator map type "unrelated" for "%s" because it is not a subtype of it.', _UnrelatedDiscriminatorMapType::class, _DiscriminatorMapParent::class));
        $loader->loadClassMetadata(new ClassMetadata(_DiscriminatorMapParent::class));
    }

    public function testLoadDiscriminatorMapTypeRejectsTargetWithoutDiscriminatorMap()
    {
        $loader = new AttributeLoader(false, [], [
            _ParentWithoutDiscriminatorMap::class => ['child' => _ChildOfParentWithoutDiscriminatorMap::class],
        ]);

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(\sprintf('Class "%s" cannot add discriminator map type "child" for "%s" because the target does not declare a discriminator map.', _ChildOfParentWithoutDiscriminatorMap::class, _ParentWithoutDiscriminatorMap::class));
        $loader->loadClassMetadata(new ClassMetadata(_ParentWithoutDiscriminatorMap::class));
    }

    public function testLoadMaxDepth()
    {
        $classMetadata = new ClassMetadata(MaxDepthDummy::class);
        $this->loader->loadClassMetadata($classMetadata);

        $attributesMetadata = $classMetadata->getAttributesMetadata();
        $this->assertEquals(2, $attributesMetadata['foo']->getMaxDepth());
        $this->assertEquals(3, $attributesMetadata['bar']->getMaxDepth());
    }

    public function testLoadSerializedName()
    {
        $classMetadata = new ClassMetadata(SerializedNameDummy::class);
        $this->loader->loadClassMetadata($classMetadata);

        $attributesMetadata = $classMetadata->getAttributesMetadata();
        $this->assertEquals('baz', $attributesMetadata['foo']->getSerializedName());
        $this->assertEquals('qux', $attributesMetadata['bar']->getSerializedName());
        $this->assertEquals('duxi', $attributesMetadata['duux']->getSerializedName());
        $this->assertEquals('duxa', $attributesMetadata['duux']->getSerializedName(['a']));
    }

    public function testLoadSerializedPath()
    {
        $classMetadata = new ClassMetadata(SerializedPathDummy::class);
        $this->loader->loadClassMetadata($classMetadata);

        $attributesMetadata = $classMetadata->getAttributesMetadata();
        $this->assertEquals(new PropertyPath('[one][two]'), $attributesMetadata['three']->getSerializedPath());
        $this->assertEquals(new PropertyPath('[three][four]'), $attributesMetadata['seven']->getSerializedPath());
        $this->assertEquals(new PropertyPath('[five][six]'), $attributesMetadata['eleven']->getSerializedPath());
        $this->assertEquals(new PropertyPath('[six][five]'), $attributesMetadata['eleven']->getSerializedPath(['a']));
    }

    public function testLoadSerializedPathInConstructor()
    {
        $classMetadata = new ClassMetadata(SerializedPathInConstructorDummy::class);
        $this->loader->loadClassMetadata($classMetadata);

        $attributesMetadata = $classMetadata->getAttributesMetadata();
        $this->assertEquals(new PropertyPath('[one][two]'), $attributesMetadata['three']->getSerializedPath());
        $this->assertEquals(new PropertyPath('[five][six]'), $attributesMetadata['eleven']->getSerializedPath());
        $this->assertEquals(new PropertyPath('[six][five]'), $attributesMetadata['eleven']->getSerializedPath(['a']));
    }

    public function testLoadClassMetadataAndMerge()
    {
        $classMetadata = new ClassMetadata(GroupDummy::class);
        $parentClassMetadata = new ClassMetadata(GroupDummyParent::class);

        $this->loader->loadClassMetadata($parentClassMetadata);
        $classMetadata->merge($parentClassMetadata);

        $this->loader->loadClassMetadata($classMetadata);

        $this->assertEquals(TestClassMetadataFactory::createClassMetadata('Symfony\Component\Serializer\Tests\Fixtures\Attributes', true), $classMetadata);
    }

    public function testLoadIgnore()
    {
        $classMetadata = new ClassMetadata(IgnoreDummy::class);
        $this->loader->loadClassMetadata($classMetadata);

        $attributesMetadata = $classMetadata->getAttributesMetadata();
        $this->assertTrue($attributesMetadata['ignored1']->isIgnored());
        $this->assertTrue($attributesMetadata['ignored2']->isIgnored());
        $this->assertTrue($attributesMetadata['beIgnored']->isIgnored());
    }

    public function testLoadContextsPropertiesPromoted()
    {
        $this->assertLoadedContexts(ContextDummyPromotedProperties::class, ContextDummyParent::class);
    }

    public function testThrowsOnContextOnInvalidMethod()
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(\sprintf('Context on "%s::badMethod()" cannot be added', BadMethodContextDummy::class));

        $loader = $this->getLoaderForContextMapping();

        $classMetadata = new ClassMetadata(BadMethodContextDummy::class);

        $loader->loadClassMetadata($classMetadata);
    }

    public function testCanHandleUnrelatedIgnoredMethods()
    {
        $metadata = new ClassMetadata(Entity45016::class);
        $loader = $this->getLoaderForContextMapping();

        $loader->loadClassMetadata($metadata);

        $this->assertSame(['id'], array_keys($metadata->getAttributesMetadata()));
    }

    public function testIgnoreGetterWithRequiredParameterIfIgnoreAttributeIsUsed()
    {
        $classMetadata = new ClassMetadata(IgnoreDummyAdditionalGetter::class);
        $this->getLoaderForContextMapping()->loadClassMetadata($classMetadata);

        $attributes = $classMetadata->getAttributesMetadata();
        self::assertArrayNotHasKey('extraValue', $attributes);
        self::assertArrayHasKey('extraValue2', $attributes);
    }

    public function testIgnoreGetterWithRequiredParameterIfIgnoreAttributeIsNotUsed()
    {
        $classMetadata = new ClassMetadata(IgnoreDummyAdditionalGetterWithoutIgnoreAttributes::class);
        $this->getLoaderForContextMapping()->loadClassMetadata($classMetadata);

        $attributes = $classMetadata->getAttributesMetadata();
        self::assertArrayNotHasKey('extraValue', $attributes);
        self::assertArrayHasKey('extraValue2', $attributes);
    }

    public function testLoadGroupsOnClass()
    {
        $classMetadata = new ClassMetadata(GroupClassDummy::class);
        $this->loader->loadClassMetadata($classMetadata);

        $attributesMetadata = $classMetadata->getAttributesMetadata();

        self::assertCount(3, $classMetadata->getAttributesMetadata());

        self::assertArrayHasKey('foo', $attributesMetadata);
        self::assertArrayHasKey('bar', $attributesMetadata);
        self::assertArrayHasKey('baz', $attributesMetadata);

        self::assertSame(['a', 'b'], $attributesMetadata['foo']->getGroups());
        self::assertSame(['a', 'c', 'd'], $attributesMetadata['bar']->getGroups());
        self::assertSame(['a'], $attributesMetadata['baz']->getGroups());
    }

    public function testLoadWithInvalidAttribute()
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Could not instantiate attribute "Symfony\Component\Serializer\Attribute\Groups" on "Symfony\Component\Serializer\Tests\Fixtures\Attributes\BadAttributeDummy::myMethod()".');

        $classMetadata = new ClassMetadata(BadAttributeDummy::class);

        $this->loader->loadClassMetadata($classMetadata);
    }

    public function testIgnoresAccessorishGetters()
    {
        $classMetadata = new ClassMetadata(AccessorishGetters::class);
        $this->loader->loadClassMetadata($classMetadata);

        $attributesMetadata = $classMetadata->getAttributesMetadata();

        self::assertCount(4, $classMetadata->getAttributesMetadata());

        self::assertArrayHasKey('field1', $attributesMetadata);
        self::assertArrayHasKey('field2', $attributesMetadata);
        self::assertArrayHasKey('field3', $attributesMetadata);
        self::assertArrayHasKey('field4', $attributesMetadata);
        self::assertArrayNotHasKey('h', $attributesMetadata);
    }

    public function testGetMappedClasses()
    {
        $mappedClasses = [
            'App\Entity\User' => ['App\Entity\User'],
            'App\Entity\Product' => ['App\Entity\Product'],
        ];
        $loader = new AttributeLoader(false, $mappedClasses);

        $this->assertSame(['App\Entity\User', 'App\Entity\Product'], $loader->getMappedClasses());
    }

    public function testGetMappedClassesIncludesDiscriminatorMapTargets()
    {
        $loader = new AttributeLoader(false, [], [
            _DiscriminatorMapParent::class => ['first' => _DiscriminatorMapFirstChild::class],
        ]);

        $this->assertSame([_DiscriminatorMapParent::class], $loader->getMappedClasses());
    }

    public function testLoadClassMetadataReturnsFalseForUnmappedClass()
    {
        $loader = new AttributeLoader(false, ['App\Entity\User' => ['App\Entity\User']]);
        $classMetadata = new ClassMetadata('App\Entity\Product');

        $this->assertFalse($loader->loadClassMetadata($classMetadata));
    }

    public function testLoadClassMetadataForMappedClassWithAttributes()
    {
        $loader = new AttributeLoader(false, [GroupDummy::class => [GroupDummy::class]]);
        $classMetadata = new ClassMetadata(GroupDummy::class);

        $this->assertTrue($loader->loadClassMetadata($classMetadata));
        $this->assertNotEmpty($classMetadata->getAttributesMetadata());
    }

    public function testLoadClassMetadataFromExplicitAttributeMappings()
    {
        $targetClass = _AttrMap_Target::class;
        $sourceClass = _AttrMap_Source::class;

        $loader = new AttributeLoader(false, [$targetClass => [$sourceClass]]);
        $classMetadata = new ClassMetadata($targetClass);

        $this->assertTrue($loader->loadClassMetadata($classMetadata));
        $this->assertContains('default', $classMetadata->getAttributesMetadata()['name']->getGroups());
    }

    public function testLoadClassMetadataWithClassLevelAttributes()
    {
        $targetClass = _AttrMap_Target::class;
        $sourceClass = _AttrMap_ClassLevelSource::class;

        $loader = new AttributeLoader(false, [$targetClass => [$sourceClass]]);
        $classMetadata = new ClassMetadata($targetClass);

        $this->assertTrue($loader->loadClassMetadata($classMetadata));

        // Check that property attributes are added to the target
        $this->assertContains('default', $classMetadata->getAttributesMetadata()['name']->getGroups());
    }

    public function testLoadClassMetadataMergesTargetOwnAttributes()
    {
        $targetClass = _AttrMap_TargetWithOwnAttributes::class;
        $sourceClass = _AttrMap_ExtensionForTargetWithOwnAttributes::class;

        $loader = new AttributeLoader(false, [$targetClass => [$sourceClass]]);
        $classMetadata = new ClassMetadata($targetClass);

        $this->assertTrue($loader->loadClassMetadata($classMetadata));

        $attributeMetadata = $classMetadata->getAttributesMetadata()['value'];
        $this->assertSame(['own'], $attributeMetadata->getGroups(), "The target class' own attributes are dropped.");
        $this->assertSame('extended_value', $attributeMetadata->getSerializedName(), 'The extension class attributes are dropped.');
    }

    protected function getLoaderForContextMapping(): AttributeLoader
    {
        return $this->loader;
    }
}

class _AttrMap_Target
{
    public string $name;

    public function getName()
    {
        return $this->name;
    }
}

use Symfony\Component\Serializer\Attribute\DiscriminatorMap;
use Symfony\Component\Serializer\Attribute\DiscriminatorMapType;
use Symfony\Component\Serializer\Attribute\ExtendsSerializationFor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ExtendsSerializationFor(_AttrMap_Target::class)]
class _AttrMap_Source
{
    #[Groups(['default'])]
    public string $name;
}

#[ExtendsSerializationFor(_AttrMap_Target::class)]
#[Groups(['class'])]
class _AttrMap_ClassLevelSource
{
    #[Groups(['default'])]
    public string $name = '';
}

class _AttrMap_TargetWithOwnAttributes
{
    #[Groups(['own'])]
    public string $value = '';
}

#[ExtendsSerializationFor(_AttrMap_TargetWithOwnAttributes::class)]
class _AttrMap_ExtensionForTargetWithOwnAttributes
{
    #[SerializedName('extended_value')]
    public string $value = '';
}

#[DiscriminatorMap('type')]
abstract class _DiscriminatorMapParent
{
}

#[DiscriminatorMap('type')]
interface _DiscriminatorMapInterface
{
}

#[DiscriminatorMapType('implementation', class: _DiscriminatorMapInterface::class)]
class _DiscriminatorMapImplementation implements _DiscriminatorMapInterface
{
}

#[DiscriminatorMapType('first', class: _DiscriminatorMapParent::class)]
class _DiscriminatorMapFirstChild extends _DiscriminatorMapParent
{
}

#[DiscriminatorMapType('second', class: _DiscriminatorMapParent::class)]
class _DiscriminatorMapSecondChild extends _DiscriminatorMapParent
{
}

#[DiscriminatorMapType('with_metadata', class: _DiscriminatorMapParent::class)]
class _DiscriminatorMapChildWithPropertyMetadata extends _DiscriminatorMapParent
{
    #[SerializedName('subject_line')]
    public string $subject;
}

#[DiscriminatorMapType('first_alias', class: _DiscriminatorMapParent::class)]
#[DiscriminatorMapType('second_alias', class: _DiscriminatorMapParent::class)]
class _DiscriminatorMapMultipleTypesChild extends _DiscriminatorMapParent
{
}

class _DeferredDiscriminatorParent
{
}

class _DeferredDiscriminatorChild extends _DeferredDiscriminatorParent
{
}

#[DiscriminatorMapType('unrelated', class: _DiscriminatorMapParent::class)]
class _UnrelatedDiscriminatorMapType
{
}

abstract class _ParentWithoutDiscriminatorMap
{
}

#[DiscriminatorMapType('child', class: _ParentWithoutDiscriminatorMap::class)]
class _ChildOfParentWithoutDiscriminatorMap extends _ParentWithoutDiscriminatorMap
{
}

#[DiscriminatorMap('nested_type', ['leaf' => _DiscriminatorMapFirstChild::class])]
#[DiscriminatorMapType('nested', class: _DiscriminatorMapParent::class)]
class _NestedDiscriminatorMapChild extends _DiscriminatorMapParent
{
}

#[DiscriminatorMap('type', defaultType: 'second')]
abstract class _DiscriminatorMapWithContributedDefault
{
}

#[DiscriminatorMapType('first', class: _DiscriminatorMapWithContributedDefault::class)]
class _DiscriminatorMapFirstDefaultChild extends _DiscriminatorMapWithContributedDefault
{
}

#[DiscriminatorMap('type', ['first' => _DiscriminatorMapInlineDefaultChild::class], defaultType: 'second')]
abstract class _DiscriminatorMapWithInlineMappingAndContributedDefault
{
}

class _DiscriminatorMapInlineDefaultChild extends _DiscriminatorMapWithInlineMappingAndContributedDefault
{
}

#[DiscriminatorMapType('second', class: _DiscriminatorMapWithInlineMappingAndContributedDefault::class)]
class _DiscriminatorMapContributedDefaultChild extends _DiscriminatorMapWithInlineMappingAndContributedDefault
{
}
