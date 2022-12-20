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
use Symfony\Component\Serializer\Exception\MappingException;
use Symfony\Component\Serializer\Mapping\AttributeMetadata;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorMapping;
use Symfony\Component\Serializer\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Mapping\Loader\LoaderInterface;
use Symfony\Component\Serializer\Tests\Mapping\Loader\Features\ContextMappingTestTrait;
use Symfony\Component\Serializer\Tests\Mapping\TestClassMetadataFactory;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
abstract class AnnotationLoaderTest extends TestCase
{
    use ContextMappingTestTrait;

    /**
     * @var AnnotationLoader
     */
    private $loader;

    protected function setUp(): void
    {
        $this->loader = $this->createLoader();
    }

    public function testInterface()
    {
        self::assertInstanceOf(LoaderInterface::class, $this->loader);
    }

    public function testLoadClassMetadataReturnsTrueIfSuccessful()
    {
        $classMetadata = new ClassMetadata($this->getNamespace().'\GroupDummy');

        self::assertTrue($this->loader->loadClassMetadata($classMetadata));
    }

    public function testLoadGroups()
    {
        $classMetadata = new ClassMetadata($this->getNamespace().'\GroupDummy');
        $this->loader->loadClassMetadata($classMetadata);

        self::assertEquals(TestClassMetadataFactory::createClassMetadata($this->getNamespace()), $classMetadata);
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

        self::assertEquals($expected, $classMetadata);
    }

    public function testLoadMaxDepth()
    {
        $classMetadata = new ClassMetadata($this->getNamespace().'\MaxDepthDummy');
        $this->loader->loadClassMetadata($classMetadata);

        $attributesMetadata = $classMetadata->getAttributesMetadata();
        self::assertEquals(2, $attributesMetadata['foo']->getMaxDepth());
        self::assertEquals(3, $attributesMetadata['bar']->getMaxDepth());
    }

    public function testLoadSerializedName()
    {
        $classMetadata = new ClassMetadata($this->getNamespace().'\SerializedNameDummy');
        $this->loader->loadClassMetadata($classMetadata);

        $attributesMetadata = $classMetadata->getAttributesMetadata();
        self::assertEquals('baz', $attributesMetadata['foo']->getSerializedName());
        self::assertEquals('qux', $attributesMetadata['bar']->getSerializedName());
    }

    public function testLoadClassMetadataAndMerge()
    {
        $classMetadata = new ClassMetadata($this->getNamespace().'\GroupDummy');
        $parentClassMetadata = new ClassMetadata($this->getNamespace().'\GroupDummyParent');

        $this->loader->loadClassMetadata($parentClassMetadata);
        $classMetadata->merge($parentClassMetadata);

        $this->loader->loadClassMetadata($classMetadata);

        self::assertEquals(TestClassMetadataFactory::createClassMetadata($this->getNamespace(), true), $classMetadata);
    }

    public function testLoadIgnore()
    {
        $classMetadata = new ClassMetadata($this->getNamespace().'\IgnoreDummy');
        $this->loader->loadClassMetadata($classMetadata);

        $attributesMetadata = $classMetadata->getAttributesMetadata();
        self::assertTrue($attributesMetadata['ignored1']->isIgnored());
        self::assertTrue($attributesMetadata['ignored2']->isIgnored());
    }

    public function testLoadContexts()
    {
        $this->assertLoadedContexts($this->getNamespace().'\ContextDummy', $this->getNamespace().'\ContextDummyParent');
    }

    public function testThrowsOnContextOnInvalidMethod()
    {
        $class = $this->getNamespace().'\BadMethodContextDummy';

        self::expectException(MappingException::class);
        self::expectExceptionMessage(sprintf('Context on "%s::badMethod()" cannot be added', $class));

        $loader = $this->getLoaderForContextMapping();

        $classMetadata = new ClassMetadata($class);

        $loader->loadClassMetadata($classMetadata);
    }

    public function testCanHandleUnrelatedIgnoredMethods()
    {
        $class = $this->getNamespace().'\Entity45016';

        self::expectException(MappingException::class);
        self::expectExceptionMessage(sprintf('Ignore on "%s::badIgnore()" cannot be added', $class));

        $metadata = new ClassMetadata($class);
        $loader = $this->getLoaderForContextMapping();

        $loader->loadClassMetadata($metadata);
    }

    public function testIgnoreGetterWirhRequiredParameterIfIgnoreAnnotationIsUsed()
    {
        $classMetadata = new ClassMetadata($this->getNamespace().'\IgnoreDummyAdditionalGetter');
        $this->getLoaderForContextMapping()->loadClassMetadata($classMetadata);

        $attributes = $classMetadata->getAttributesMetadata();
        self::assertArrayNotHasKey('extraValue', $attributes);
        self::assertArrayHasKey('extraValue2', $attributes);
    }

    public function testIgnoreGetterWirhRequiredParameterIfIgnoreAnnotationIsNotUsed()
    {
        $classMetadata = new ClassMetadata($this->getNamespace().'\IgnoreDummyAdditionalGetterWithoutIgnoreAnnotations');
        $this->getLoaderForContextMapping()->loadClassMetadata($classMetadata);

        $attributes = $classMetadata->getAttributesMetadata();
        self::assertArrayNotHasKey('extraValue', $attributes);
        self::assertArrayHasKey('extraValue2', $attributes);
    }

    abstract protected function createLoader(): AnnotationLoader;

    abstract protected function getNamespace(): string;

    protected function getLoaderForContextMapping(): LoaderInterface
    {
        return $this->loader;
    }
}
