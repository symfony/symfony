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
use Symfony\Component\Serializer\Mapping\AttributeMetadata;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorMapping;
use Symfony\Component\Serializer\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Tests\Mapping\TestClassMetadataFactory;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
abstract class AnnotationLoaderTest extends TestCase
{
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
        $this->assertInstanceOf('Symfony\Component\Serializer\Mapping\Loader\LoaderInterface', $this->loader);
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

    abstract protected function createLoader(): AnnotationLoader;

    abstract protected function getNamespace(): string;
}
