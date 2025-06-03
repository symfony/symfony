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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\PropertyAccess\PropertyPath;
use Symfony\Component\Serializer\Exception\MappingException;
use Symfony\Component\Serializer\Mapping\AttributeMetadata;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorMapping;
use Symfony\Component\Serializer\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Mapping\Loader\LoaderInterface;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\AccessorishGetters;
use Symfony\Component\Serializer\Tests\Mapping\Loader\Features\ContextMappingTestTrait;
use Symfony\Component\Serializer\Tests\Mapping\TestClassMetadataFactory;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
abstract class AttributeLoaderTestCase extends TestCase
{
    use ContextMappingTestTrait;
    use ExpectDeprecationTrait;

    protected AnnotationLoader $loader;

    protected function setUp(): void
    {
        $this->loader = $this->createLoader();
    }

    public function testInterface()
    {
        $this->assertInstanceOf(LoaderInterface::class, $this->loader);
    }

    public function testLoadClassMetadataReturnsTrueIfSuccessful()
    {
        $classMetadata = new ClassMetadata($this->getNamespace().'\GroupDummy');

        $this->assertTrue($this->loader->loadClassMetadata($classMetadata));
    }

    public function testLoadGroups()
    {
        $classMetadata = new ClassMetadata($this->getNamespace().'\GroupDummy');
        $this->loader->loadClassMetadata($classMetadata);

        $this->assertEquals(TestClassMetadataFactory::createClassMetadata($this->getNamespace()), $classMetadata);
    }

    public function testLoadDiscriminatorMap()
    {
        $classMetadata = new ClassMetadata($this->getNamespace().'\AbstractDummy');
        $this->loader->loadClassMetadata($classMetadata);

        $expected = new ClassMetadata($this->getNamespace().'\AbstractDummy', new ClassDiscriminatorMapping('type', [
            'first' => $this->getNamespace().'\AbstractDummyFirstChild',
            'second' => $this->getNamespace().'\AbstractDummySecondChild',
            'third' => $this->getNamespace().'\AbstractDummyThirdChild',
        ]));

        $expected->addAttributeMetadata(new AttributeMetadata('foo'));
        $expected->getReflectionClass();

        $this->assertEquals($expected, $classMetadata);
    }

    public function testLoadMaxDepth()
    {
        $classMetadata = new ClassMetadata($this->getNamespace().'\MaxDepthDummy');
        $this->loader->loadClassMetadata($classMetadata);

        $attributesMetadata = $classMetadata->getAttributesMetadata();
        $this->assertEquals(2, $attributesMetadata['foo']->getMaxDepth());
        $this->assertEquals(3, $attributesMetadata['bar']->getMaxDepth());
    }

    public function testLoadSerializedName()
    {
        $classMetadata = new ClassMetadata($this->getNamespace().'\SerializedNameDummy');
        $this->loader->loadClassMetadata($classMetadata);

        $attributesMetadata = $classMetadata->getAttributesMetadata();
        $this->assertEquals('baz', $attributesMetadata['foo']->getSerializedName());
        $this->assertEquals('qux', $attributesMetadata['bar']->getSerializedName());
    }

    public function testLoadSerializedPath()
    {
        $classMetadata = new ClassMetadata($this->getNamespace().'\SerializedPathDummy');
        $this->loader->loadClassMetadata($classMetadata);

        $attributesMetadata = $classMetadata->getAttributesMetadata();
        $this->assertEquals(new PropertyPath('[one][two]'), $attributesMetadata['three']->getSerializedPath());
        $this->assertEquals(new PropertyPath('[three][four]'), $attributesMetadata['seven']->getSerializedPath());
    }

    public function testLoadSerializedPathInConstructor()
    {
        $classMetadata = new ClassMetadata($this->getNamespace().'\SerializedPathInConstructorDummy');
        $this->loader->loadClassMetadata($classMetadata);

        $attributesMetadata = $classMetadata->getAttributesMetadata();
        $this->assertEquals(new PropertyPath('[one][two]'), $attributesMetadata['three']->getSerializedPath());
    }

    public function testLoadClassMetadataAndMerge()
    {
        $classMetadata = new ClassMetadata($this->getNamespace().'\GroupDummy');
        $parentClassMetadata = new ClassMetadata($this->getNamespace().'\GroupDummyParent');

        $this->loader->loadClassMetadata($parentClassMetadata);
        $classMetadata->merge($parentClassMetadata);

        $this->loader->loadClassMetadata($classMetadata);

        $this->assertEquals(TestClassMetadataFactory::createClassMetadata($this->getNamespace(), true), $classMetadata);
    }

    public function testLoadIgnore()
    {
        $classMetadata = new ClassMetadata($this->getNamespace().'\IgnoreDummy');
        $this->loader->loadClassMetadata($classMetadata);

        $attributesMetadata = $classMetadata->getAttributesMetadata();
        $this->assertTrue($attributesMetadata['ignored1']->isIgnored());
        $this->assertTrue($attributesMetadata['ignored2']->isIgnored());
    }

    public function testLoadContexts()
    {
        $this->assertLoadedContexts($this->getNamespace().'\ContextDummy', $this->getNamespace().'\ContextDummyParent');
    }

    public function testLoadContextsPropertiesPromoted()
    {
        $this->assertLoadedContexts($this->getNamespace().'\ContextDummyPromotedProperties', $this->getNamespace().'\ContextDummyParent');
    }

    public function testThrowsOnContextOnInvalidMethod()
    {
        $class = $this->getNamespace().'\BadMethodContextDummy';

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(sprintf('Context on "%s::badMethod()" cannot be added', $class));

        $loader = $this->getLoaderForContextMapping();

        $classMetadata = new ClassMetadata($class);

        $loader->loadClassMetadata($classMetadata);
    }

    public function testCanHandleUnrelatedIgnoredMethods()
    {
        $class = $this->getNamespace().'\Entity45016';

        $metadata = new ClassMetadata($class);
        $loader = $this->getLoaderForContextMapping();

        $loader->loadClassMetadata($metadata);

        $this->assertSame(['id'], array_keys($metadata->getAttributesMetadata()));
    }

    public function testIgnoreGetterWithRequiredParameterIfIgnoreAnnotationIsUsed()
    {
        $classMetadata = new ClassMetadata($this->getNamespace().'\IgnoreDummyAdditionalGetter');
        $this->getLoaderForContextMapping()->loadClassMetadata($classMetadata);

        $attributes = $classMetadata->getAttributesMetadata();
        self::assertArrayNotHasKey('extraValue', $attributes);
        self::assertArrayHasKey('extraValue2', $attributes);
    }

    public function testIgnoreGetterWithRequiredParameterIfIgnoreAnnotationIsNotUsed()
    {
        $classMetadata = new ClassMetadata($this->getNamespace().'\IgnoreDummyAdditionalGetterWithoutIgnoreAnnotations');
        $this->getLoaderForContextMapping()->loadClassMetadata($classMetadata);

        $attributes = $classMetadata->getAttributesMetadata();
        self::assertArrayNotHasKey('extraValue', $attributes);
        self::assertArrayHasKey('extraValue2', $attributes);
    }

    public function testLoadGroupsOnClass()
    {
        $classMetadata = new ClassMetadata($this->getNamespace().'\GroupClassDummy');
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

    /**
     * @group legacy
     */
    public function testExpectedDeprecationOnLoadAnnotationsCall()
    {
        $this->expectDeprecation('Since symfony/serializer 6.4: Method "Symfony\Component\Serializer\Mapping\Loader\AttributeLoader::loadAnnotations()" is deprecated without replacement.');
        $this->loader->loadAnnotations(new \ReflectionClass(\stdClass::class));
    }

    abstract protected function createLoader(): AttributeLoader;

    abstract protected function getNamespace(): string;

    protected function getLoaderForContextMapping(): LoaderInterface
    {
        return $this->loader;
    }
}
