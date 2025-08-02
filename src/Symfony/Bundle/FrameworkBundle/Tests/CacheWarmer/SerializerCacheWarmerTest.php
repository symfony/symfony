<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\CacheWarmer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Symfony\Bundle\FrameworkBundle\CacheWarmer\SerializerCacheWarmer;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Serializer\Mapping\Loader\LoaderChain;
use Symfony\Component\Serializer\Mapping\Loader\XmlFileLoader;
use Symfony\Component\Serializer\Mapping\Loader\YamlFileLoader;

class SerializerCacheWarmerTest extends TestCase
{
    private PhpArrayAdapter $arrayPool;

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->arrayPool)) {
            $this->arrayPool->clear();
            unset($this->arrayPool);
        }
    }

    private function getArrayPool(string $file): PhpArrayAdapter
    {
        return $this->arrayPool = new PhpArrayAdapter($file, new NullAdapter());
    }

    #[DataProvider('yamlLoaderProvider')]
    public function testYamlWarmUp(array $loaders)
    {
        $file = sys_get_temp_dir().'/cache-serializer.php';
        @unlink($file);

        $warmer = new SerializerCacheWarmer($loaders, $file);
        $warmer->warmUp(\dirname($file), \dirname($file));

        $this->assertFileExists($file);

        $arrayPool = new PhpArrayAdapter($file, new NullAdapter());

        $this->assertTrue($arrayPool->getItem('Symfony_Bundle_FrameworkBundle_Tests_Fixtures_Serialization_Author')->isHit());
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    #[DataProvider('xmlLoaderProvider')]
    public function testXmlWarmUp(array $loaders)
    {
        $file = sys_get_temp_dir().'/cache-serializer.php';
        @unlink($file);

        $warmer = new SerializerCacheWarmer($loaders, $file);
        $warmer->warmUp(\dirname($file), \dirname($file));

        $this->assertFileExists($file);

        $arrayPool = $this->getArrayPool($file);

        $this->assertTrue($arrayPool->getItem('Symfony_Bundle_FrameworkBundle_Tests_Fixtures_Serialization_Person')->isHit());
    }

    #[DataProvider('yamlLoaderProvider')]
    public function testYamlWarmUpAbsoluteFilePath(array $loaders)
    {
        $file = sys_get_temp_dir().'/0/cache-serializer.php';
        @unlink($file);

        $cacheDir = sys_get_temp_dir().'/1';

        $warmer = new SerializerCacheWarmer($loaders, $file);
        $warmer->warmUp($cacheDir, $cacheDir);

        $this->assertFileExists($file);
        $this->assertFileDoesNotExist($cacheDir.'/cache-serializer.php');

        $arrayPool = new PhpArrayAdapter($file, new NullAdapter());

        $this->assertTrue($arrayPool->getItem('Symfony_Bundle_FrameworkBundle_Tests_Fixtures_Serialization_Author')->isHit());
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    #[DataProvider('xmlLoaderProvider')]
    public function testXmlWarmUpAbsoluteFilePath(array $loaders)
    {
        $file = sys_get_temp_dir().'/0/cache-serializer.php';
        @unlink($file);

        $cacheDir = sys_get_temp_dir().'/1';

        $warmer = new SerializerCacheWarmer($loaders, $file);
        $warmer->warmUp($cacheDir, $cacheDir);

        $this->assertFileExists($file);
        $this->assertFileDoesNotExist($cacheDir.'/cache-serializer.php');

        $arrayPool = $this->getArrayPool($file);

        $this->assertTrue($arrayPool->getItem('Symfony_Bundle_FrameworkBundle_Tests_Fixtures_Serialization_Person')->isHit());
    }

    #[DataProvider('yamlLoaderProvider')]
    public function testYamlWarmUpWithoutBuildDir(array $loaders)
    {
        $file = sys_get_temp_dir().'/cache-serializer.php';
        @unlink($file);

        $warmer = new SerializerCacheWarmer($loaders, $file);
        $warmer->warmUp(\dirname($file));

        $this->assertFileDoesNotExist($file);

        $arrayPool = $this->getArrayPool($file);

        $this->assertFalse($arrayPool->getItem('Symfony_Bundle_FrameworkBundle_Tests_Fixtures_Serialization_Author')->isHit());
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    #[DataProvider('xmlLoaderProvider')]
    public function testXmlWarmUpWithoutBuildDir(array $loaders)
    {
        $file = sys_get_temp_dir().'/cache-serializer.php';
        @unlink($file);

        $warmer = new SerializerCacheWarmer($loaders, $file);
        $warmer->warmUp(\dirname($file));

        $this->assertFileDoesNotExist($file);

        $arrayPool = new PhpArrayAdapter($file, new NullAdapter());

        $this->assertFalse($arrayPool->getItem('Symfony_Bundle_FrameworkBundle_Tests_Fixtures_Serialization_Person')->isHit());
    }

    public static function yamlLoaderProvider(): array
    {
        return [
            [
                [
                    new LoaderChain([
                        new YamlFileLoader(__DIR__.'/../Fixtures/Serialization/Resources/author.yml'),
                    ]),
                ],
            ],
            [
                [
                    new YamlFileLoader(__DIR__.'/../Fixtures/Serialization/Resources/author.yml'),
                ],
            ],
        ];
    }

    public static function xmlLoaderProvider(): array
    {
        return [
            [
                [
                    new LoaderChain([
                        new XmlFileLoader(__DIR__.'/../Fixtures/Serialization/Resources/person.xml'),
                    ]),
                ],
            ],
            [
                [
                    new XmlFileLoader(__DIR__.'/../Fixtures/Serialization/Resources/person.xml'),
                ],
            ],
        ];
    }

    public function testWarmUpWithoutLoader()
    {
        $file = sys_get_temp_dir().'/cache-serializer-without-loader.php';
        @unlink($file);

        $warmer = new SerializerCacheWarmer([], $file);
        $warmer->warmUp(\dirname($file), \dirname($file));

        $this->assertFileExists($file);
    }

    /**
     * Test that the cache warming process is not broken if a class loader
     * throws an exception (on class / file not found for example).
     */
    public function testClassAutoloadException()
    {
        $this->assertFalse(class_exists($mappedClass = 'AClassThatDoesNotExist_FWB_CacheWarmer_SerializerCacheWarmerTest', false));

        $file = tempnam(sys_get_temp_dir(), __FUNCTION__);
        @unlink($file);

        $warmer = new SerializerCacheWarmer([new YamlFileLoader(__DIR__.'/../Fixtures/Serialization/Resources/does_not_exist.yaml')], $file);

        spl_autoload_register($classLoader = function ($class) use ($mappedClass) {
            if ($class === $mappedClass) {
                throw new \DomainException('This exception should be caught by the warmer.');
            }
        }, true, true);

        $warmer->warmUp(\dirname($file), \dirname($file));
        $this->assertFileExists($file);

        spl_autoload_unregister($classLoader);
    }

    /**
     * Test that the cache warming process is broken if a class loader throws an
     * exception but that is unrelated to the class load.
     */
    public function testClassAutoloadExceptionWithUnrelatedException()
    {
        $this->assertFalse(class_exists($mappedClass = 'AClassThatDoesNotExist_FWB_CacheWarmer_SerializerCacheWarmerTest', false));

        $file = tempnam(sys_get_temp_dir(), __FUNCTION__);
        @unlink($file);

        $warmer = new SerializerCacheWarmer([new YamlFileLoader(__DIR__.'/../Fixtures/Serialization/Resources/does_not_exist.yaml')], basename($file));

        spl_autoload_register($classLoader = function ($class) use ($mappedClass) {
            if ($class === $mappedClass) {
                eval('class '.$mappedClass.'{}');
                throw new \DomainException('This exception should not be caught by the warmer.');
            }
        }, true, true);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('This exception should not be caught by the warmer.');

        try {
            $warmer->warmUp(\dirname($file), \dirname($file));
        } catch (\DomainException $e) {
            $this->assertFileDoesNotExist($file);

            throw $e;
        } finally {
            spl_autoload_unregister($classLoader);
        }
    }
}
