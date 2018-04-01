<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\CacheWarmer;

use Symphony\Bundle\FrameworkBundle\CacheWarmer\SerializerCacheWarmer;
use Symphony\Bundle\FrameworkBundle\Tests\TestCase;
use Symphony\Component\Cache\Adapter\ArrayAdapter;
use Symphony\Component\Serializer\Mapping\Factory\CacheClassMetadataFactory;
use Symphony\Component\Serializer\Mapping\Loader\XmlFileLoader;
use Symphony\Component\Serializer\Mapping\Loader\YamlFileLoader;

class SerializerCacheWarmerTest extends TestCase
{
    public function testWarmUp()
    {
        if (!class_exists(CacheClassMetadataFactory::class) || !method_exists(XmlFileLoader::class, 'getMappedClasses') || !method_exists(YamlFileLoader::class, 'getMappedClasses')) {
            $this->markTestSkipped('The Serializer default cache warmer has been introduced in the Serializer Component version 3.2.');
        }

        $loaders = array(
            new XmlFileLoader(__DIR__.'/../Fixtures/Serialization/Resources/person.xml'),
            new YamlFileLoader(__DIR__.'/../Fixtures/Serialization/Resources/author.yml'),
        );

        $file = sys_get_temp_dir().'/cache-serializer.php';
        @unlink($file);

        $fallbackPool = new ArrayAdapter();

        $warmer = new SerializerCacheWarmer($loaders, $file, $fallbackPool);
        $warmer->warmUp(dirname($file));

        $this->assertFileExists($file);

        $values = require $file;

        $this->assertInternalType('array', $values);
        $this->assertCount(2, $values);
        $this->assertArrayHasKey('Symphony_Bundle_FrameworkBundle_Tests_Fixtures_Serialization_Person', $values);
        $this->assertArrayHasKey('Symphony_Bundle_FrameworkBundle_Tests_Fixtures_Serialization_Author', $values);

        $values = $fallbackPool->getValues();

        $this->assertInternalType('array', $values);
        $this->assertCount(2, $values);
        $this->assertArrayHasKey('Symphony_Bundle_FrameworkBundle_Tests_Fixtures_Serialization_Person', $values);
        $this->assertArrayHasKey('Symphony_Bundle_FrameworkBundle_Tests_Fixtures_Serialization_Author', $values);
    }

    public function testWarmUpWithoutLoader()
    {
        if (!class_exists(CacheClassMetadataFactory::class) || !method_exists(XmlFileLoader::class, 'getMappedClasses') || !method_exists(YamlFileLoader::class, 'getMappedClasses')) {
            $this->markTestSkipped('The Serializer default cache warmer has been introduced in the Serializer Component version 3.2.');
        }

        $file = sys_get_temp_dir().'/cache-serializer-without-loader.php';
        @unlink($file);

        $fallbackPool = new ArrayAdapter();

        $warmer = new SerializerCacheWarmer(array(), $file, $fallbackPool);
        $warmer->warmUp(dirname($file));

        $this->assertFileExists($file);

        $values = require $file;

        $this->assertInternalType('array', $values);
        $this->assertCount(0, $values);

        $values = $fallbackPool->getValues();

        $this->assertInternalType('array', $values);
        $this->assertCount(0, $values);
    }
}
