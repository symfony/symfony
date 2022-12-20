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
use Symfony\Component\Serializer\Mapping\Loader\LoaderInterface;
use Symfony\Component\Serializer\Mapping\Loader\XmlFileLoader;
use Symfony\Component\Serializer\Tests\Fixtures\Annotations\AbstractDummy;
use Symfony\Component\Serializer\Tests\Fixtures\Annotations\AbstractDummyFirstChild;
use Symfony\Component\Serializer\Tests\Fixtures\Annotations\AbstractDummySecondChild;
use Symfony\Component\Serializer\Tests\Fixtures\Annotations\IgnoreDummy;
use Symfony\Component\Serializer\Tests\Mapping\Loader\Features\ContextMappingTestTrait;
use Symfony\Component\Serializer\Tests\Mapping\TestClassMetadataFactory;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class XmlFileLoaderTest extends TestCase
{
    use ContextMappingTestTrait;

    /**
     * @var XmlFileLoader
     */
    private $loader;

    /**
     * @var ClassMetadata
     */
    private $metadata;

    protected function setUp(): void
    {
        $this->loader = new XmlFileLoader(__DIR__.'/../../Fixtures/serialization.xml');
        $this->metadata = new ClassMetadata('Symfony\Component\Serializer\Tests\Fixtures\Annotations\GroupDummy');
    }

    public function testInterface()
    {
        self::assertInstanceOf(LoaderInterface::class, $this->loader);
    }

    public function testLoadClassMetadataReturnsTrueIfSuccessful()
    {
        self::assertTrue($this->loader->loadClassMetadata($this->metadata));
    }

    public function testLoadClassMetadata()
    {
        $this->loader->loadClassMetadata($this->metadata);

        self::assertEquals(TestClassMetadataFactory::createXmlCLassMetadata(), $this->metadata);
    }

    public function testMaxDepth()
    {
        $classMetadata = new ClassMetadata('Symfony\Component\Serializer\Tests\Fixtures\Annotations\MaxDepthDummy');
        $this->loader->loadClassMetadata($classMetadata);

        $attributesMetadata = $classMetadata->getAttributesMetadata();
        self::assertEquals(2, $attributesMetadata['foo']->getMaxDepth());
        self::assertEquals(3, $attributesMetadata['bar']->getMaxDepth());
    }

    public function testSerializedName()
    {
        $classMetadata = new ClassMetadata('Symfony\Component\Serializer\Tests\Fixtures\Annotations\SerializedNameDummy');
        $this->loader->loadClassMetadata($classMetadata);

        $attributesMetadata = $classMetadata->getAttributesMetadata();
        self::assertEquals('baz', $attributesMetadata['foo']->getSerializedName());
        self::assertEquals('qux', $attributesMetadata['bar']->getSerializedName());
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

        self::assertEquals($expected, $classMetadata);
    }

    public function testLoadIgnore()
    {
        $classMetadata = new ClassMetadata(IgnoreDummy::class);
        $this->loader->loadClassMetadata($classMetadata);

        $attributesMetadata = $classMetadata->getAttributesMetadata();
        self::assertTrue($attributesMetadata['ignored1']->isIgnored());
        self::assertTrue($attributesMetadata['ignored2']->isIgnored());
        self::assertFalse($attributesMetadata['notIgnored']->isIgnored());
    }

    protected function getLoaderForContextMapping(): LoaderInterface
    {
        return $this->loader;
    }
}
