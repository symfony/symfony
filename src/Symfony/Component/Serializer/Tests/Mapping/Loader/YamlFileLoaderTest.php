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
use Symfony\Component\Serializer\Mapping\Loader\LoaderInterface;
use Symfony\Component\Serializer\Mapping\Loader\YamlFileLoader;
use Symfony\Component\Serializer\Tests\Fixtures\Annotations\AbstractDummy;
use Symfony\Component\Serializer\Tests\Fixtures\Annotations\AbstractDummyFirstChild;
use Symfony\Component\Serializer\Tests\Fixtures\Annotations\AbstractDummySecondChild;
use Symfony\Component\Serializer\Tests\Fixtures\Annotations\IgnoreDummy;
use Symfony\Component\Serializer\Tests\Mapping\Loader\Features\ContextMappingTestTrait;
use Symfony\Component\Serializer\Tests\Mapping\TestClassMetadataFactory;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class YamlFileLoaderTest extends TestCase
{
    use ContextMappingTestTrait;

    /**
     * @var YamlFileLoader
     */
    private $loader;
    /**
     * @var ClassMetadata
     */
    private $metadata;

    protected function setUp(): void
    {
        $this->loader = new YamlFileLoader(__DIR__.'/../../Fixtures/serialization.yml');
        $this->metadata = new ClassMetadata('Symfony\Component\Serializer\Tests\Fixtures\Annotations\GroupDummy');
    }

    public function testInterface()
    {
        $this->assertInstanceOf(LoaderInterface::class, $this->loader);
    }

    public function testLoadClassMetadataReturnsTrueIfSuccessful()
    {
        $this->assertTrue($this->loader->loadClassMetadata($this->metadata));
    }

    public function testLoadClassMetadataReturnsFalseWhenEmpty()
    {
        $loader = new YamlFileLoader(__DIR__.'/../../Fixtures/empty-mapping.yml');
        $this->assertFalse($loader->loadClassMetadata($this->metadata));
    }

    public function testLoadClassMetadataReturnsThrowsInvalidMapping()
    {
        $this->expectException(MappingException::class);
        $loader = new YamlFileLoader(__DIR__.'/../../Fixtures/invalid-mapping.yml');
        $loader->loadClassMetadata($this->metadata);
    }

    public function testLoadClassMetadata()
    {
        $this->loader->loadClassMetadata($this->metadata);

        $this->assertEquals(TestClassMetadataFactory::createXmlCLassMetadata(), $this->metadata);
    }

    public function testMaxDepth()
    {
        $classMetadata = new ClassMetadata('Symfony\Component\Serializer\Tests\Fixtures\Annotations\MaxDepthDummy');
        $this->loader->loadClassMetadata($classMetadata);

        $attributesMetadata = $classMetadata->getAttributesMetadata();
        $this->assertEquals(2, $attributesMetadata['foo']->getMaxDepth());
        $this->assertEquals(3, $attributesMetadata['bar']->getMaxDepth());
    }

    public function testSerializedName()
    {
        $classMetadata = new ClassMetadata('Symfony\Component\Serializer\Tests\Fixtures\Annotations\SerializedNameDummy');
        $this->loader->loadClassMetadata($classMetadata);

        $attributesMetadata = $classMetadata->getAttributesMetadata();
        $this->assertEquals('baz', $attributesMetadata['foo']->getSerializedName());
        $this->assertEquals('qux', $attributesMetadata['bar']->getSerializedName());
    }

    public function testSerializedPath()
    {
        $classMetadata = new ClassMetadata('Symfony\Component\Serializer\Tests\Fixtures\Annotations\SerializedPathDummy');
        $this->loader->loadClassMetadata($classMetadata);

        $attributesMetadata = $classMetadata->getAttributesMetadata();
        $this->assertEquals(new PropertyPath('[one][two]'), $attributesMetadata['three']->getSerializedPath());
        $this->assertEquals(new PropertyPath('[three][four]'), $attributesMetadata['seven']->getSerializedPath());
    }

    public function testLoadDiscriminatorMap()
    {
        $classMetadata = new ClassMetadata(AbstractDummy::class);
        $this->loader->loadClassMetadata($classMetadata);

        $expected = new ClassMetadata(AbstractDummy::class, new ClassDiscriminatorMapping('type', [
            'first' => AbstractDummyFirstChild::class,
            'second' => AbstractDummySecondChild::class,
        ]));

        $expected->addAttributeMetadata(new AttributeMetadata('foo'));

        $this->assertEquals($expected, $classMetadata);
    }

    public function testLoadIgnore()
    {
        $classMetadata = new ClassMetadata(IgnoreDummy::class);
        $this->loader->loadClassMetadata($classMetadata);

        $attributesMetadata = $classMetadata->getAttributesMetadata();
        $this->assertTrue($attributesMetadata['ignored1']->isIgnored());
        $this->assertTrue($attributesMetadata['ignored2']->isIgnored());
    }

    public function testLoadInvalidIgnore()
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('The "ignore" value must be a boolean');

        (new YamlFileLoader(__DIR__.'/../../Fixtures/invalid-ignore.yml'))->loadClassMetadata(new ClassMetadata(IgnoreDummy::class));
    }

    protected function getLoaderForContextMapping(): LoaderInterface
    {
        return $this->loader;
    }
}
