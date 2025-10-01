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
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
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
    }

    public function testLoadSerializedPath()
    {
        $classMetadata = new ClassMetadata(SerializedPathDummy::class);
        $this->loader->loadClassMetadata($classMetadata);

        $attributesMetadata = $classMetadata->getAttributesMetadata();
        $this->assertEquals(new PropertyPath('[one][two]'), $attributesMetadata['three']->getSerializedPath());
        $this->assertEquals(new PropertyPath('[three][four]'), $attributesMetadata['seven']->getSerializedPath());
    }

    public function testLoadSerializedPathInConstructor()
    {
        $classMetadata = new ClassMetadata(SerializedPathInConstructorDummy::class);
        $this->loader->loadClassMetadata($classMetadata);

        $attributesMetadata = $classMetadata->getAttributesMetadata();
        $this->assertEquals(new PropertyPath('[one][two]'), $attributesMetadata['three']->getSerializedPath());
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

use Symfony\Component\Serializer\Attribute\ExtendsSerializationFor;
use Symfony\Component\Serializer\Attribute\Groups;

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
