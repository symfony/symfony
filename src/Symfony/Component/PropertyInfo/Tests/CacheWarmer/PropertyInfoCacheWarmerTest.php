<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\CacheWarmer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\PropertyInfo\CacheWarmer\PropertyInfoCacheWarmer;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoCacheExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Php80PromotedDummy;

class PropertyInfoCacheWarmerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/sf_property_info_cache_warmer';
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->dir);
    }

    public function testRuntimeReadsAreServedByTheWarmedFile()
    {
        $extractor = $this->createExtractor();
        $warmer = new PropertyInfoCacheWarmer($extractor, [Dummy::class, Php80PromotedDummy::class], $this->dir.'/property_info.php');
        $warmer->warmUp($this->dir, $this->dir);

        $fallbackPool = new ArrayAdapter();
        $cachedExtractor = new PropertyInfoCacheExtractor($extractor, new PhpArrayAdapter($this->dir.'/property_info.php', $fallbackPool));

        foreach ([Dummy::class, Php80PromotedDummy::class] as $class) {
            $properties = $extractor->getProperties($class);
            $this->assertNotEmpty($properties);
            $this->assertSame($properties, $cachedExtractor->getProperties($class));

            foreach ($properties as $property) {
                $this->assertSame($extractor->isReadable($class, $property), $cachedExtractor->isReadable($class, $property));
                $this->assertSame($extractor->isWritable($class, $property), $cachedExtractor->isWritable($class, $property));
                $this->assertEquals($extractor->getType($class, $property), $cachedExtractor->getType($class, $property));
            }

            foreach ((new \ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $this->assertSame($extractor->getPropertyName($class, $method->name), $cachedExtractor->getPropertyName($class, $method->name));
            }
        }

        $this->assertSame([], $fallbackPool->getValues());
    }

    public function testMissingClassesAreIgnored()
    {
        $warmer = new PropertyInfoCacheWarmer($this->createExtractor(), ['Symfony\Component\PropertyInfo\Tests\Fixtures\Missing', Php80PromotedDummy::class], $this->dir.'/property_info.php');
        $warmer->warmUp($this->dir, $this->dir);

        $cachedExtractor = new PropertyInfoCacheExtractor($this->createExtractor(), new PhpArrayAdapter($this->dir.'/property_info.php', $fallbackPool = new ArrayAdapter()));
        $cachedExtractor->getProperties(Php80PromotedDummy::class);

        $this->assertSame([], $fallbackPool->getValues());
    }

    public function testNothingIsWarmedWithoutBuildDir()
    {
        $warmer = new PropertyInfoCacheWarmer($this->createExtractor(), [Dummy::class], $this->dir.'/property_info.php');

        $this->assertSame([], $warmer->warmUp($this->dir));
        $this->assertFileDoesNotExist($this->dir.'/property_info.php');
    }

    private function createExtractor(): PropertyInfoExtractor
    {
        $reflectionExtractor = new ReflectionExtractor();
        $phpDocExtractor = new PhpDocExtractor();

        return new PropertyInfoExtractor([$reflectionExtractor], [$phpDocExtractor, $reflectionExtractor], [$phpDocExtractor], [$reflectionExtractor], [$reflectionExtractor], [$reflectionExtractor]);
    }
}
