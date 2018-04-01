<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Serializer\Tests\Mapping\Loader;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Serializer\Mapping\AttributeMetadata;
use Symphony\Component\Serializer\Mapping\ClassDiscriminatorMapping;
use Symphony\Component\Serializer\Mapping\Loader\YamlFileLoader;
use Symphony\Component\Serializer\Mapping\ClassMetadata;
use Symphony\Component\Serializer\Tests\Fixtures\AbstractDummy;
use Symphony\Component\Serializer\Tests\Fixtures\AbstractDummyFirstChild;
use Symphony\Component\Serializer\Tests\Fixtures\AbstractDummySecondChild;
use Symphony\Component\Serializer\Tests\Mapping\TestClassMetadataFactory;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class YamlFileLoaderTest extends TestCase
{
    /**
     * @var YamlFileLoader
     */
    private $loader;
    /**
     * @var ClassMetadata
     */
    private $metadata;

    protected function setUp()
    {
        $this->loader = new YamlFileLoader(__DIR__.'/../../Fixtures/serialization.yml');
        $this->metadata = new ClassMetadata('Symphony\Component\Serializer\Tests\Fixtures\GroupDummy');
    }

    public function testInterface()
    {
        $this->assertInstanceOf('Symphony\Component\Serializer\Mapping\Loader\LoaderInterface', $this->loader);
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

    /**
     * @expectedException \Symphony\Component\Serializer\Exception\MappingException
     */
    public function testLoadClassMetadataReturnsThrowsInvalidMapping()
    {
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
        $classMetadata = new ClassMetadata('Symphony\Component\Serializer\Tests\Fixtures\MaxDepthDummy');
        $this->loader->loadClassMetadata($classMetadata);

        $attributesMetadata = $classMetadata->getAttributesMetadata();
        $this->assertEquals(2, $attributesMetadata['foo']->getMaxDepth());
        $this->assertEquals(3, $attributesMetadata['bar']->getMaxDepth());
    }

    public function testLoadDiscriminatorMap()
    {
        $classMetadata = new ClassMetadata(AbstractDummy::class);
        $this->loader->loadClassMetadata($classMetadata);

        $expected = new ClassMetadata(AbstractDummy::class, new ClassDiscriminatorMapping('type', array(
            'first' => AbstractDummyFirstChild::class,
            'second' => AbstractDummySecondChild::class,
        )));

        $expected->addAttributeMetadata(new AttributeMetadata('foo'));

        $this->assertEquals($expected, $classMetadata);
    }
}
