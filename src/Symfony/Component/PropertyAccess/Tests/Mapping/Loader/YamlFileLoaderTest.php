<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests\Mapping\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\Mapping\Loader\YamlFileLoader;
use Symfony\Component\PropertyAccess\Mapping\ClassMetadata;
use Symfony\Component\PropertyAccess\Tests\Mapping\TestClassMetadataFactory;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Luis Ramón López <lrlopez@gmail.com>
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
        $this->loader = new YamlFileLoader(__DIR__.'/../../Fixtures/property-access.yml');
        $this->metadata = new ClassMetadata('Symfony\Component\PropertyAccess\Tests\Fixtures\Dummy');
    }

    public function testInterface()
    {
        $this->assertInstanceOf('Symfony\Component\PropertyAccess\Mapping\Loader\LoaderInterface', $this->loader);
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
     * @expectedException \Symfony\Component\PropertyAccess\Exception\MappingException
     */
    public function testLoadClassMetadataReturnsThrowsInvalidMapping()
    {
        $loader = new YamlFileLoader(__DIR__.'/../../Fixtures/invalid-mapping.yml');
        $loader->loadClassMetadata($this->metadata);
    }

    public function testLoadClassMetadata()
    {
        $this->loader->loadClassMetadata($this->metadata);

        $this->assertEquals(TestClassMetadataFactory::createXmlClassMetadata(), $this->metadata);
    }

}
