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

use Symfony\Bundle\FrameworkBundle\CacheWarmer\ValidatorCacheWarmer;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\ValidatorBuilder;

class ValidatorCacheWarmerTest extends TestCase
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

    public function testWarmUp()
    {
        $validatorBuilder = new ValidatorBuilder();
        $validatorBuilder->addXmlMapping(__DIR__.'/../Fixtures/Validation/Resources/person.xml');
        $validatorBuilder->addYamlMapping(__DIR__.'/../Fixtures/Validation/Resources/author.yml');
        $validatorBuilder->addMethodMapping('loadValidatorMetadata');
        $validatorBuilder->enableAttributeMapping();

        $file = sys_get_temp_dir().'/cache-validator.php';
        @unlink($file);

        $warmer = new ValidatorCacheWarmer($validatorBuilder, $file);
        $warmer->warmUp(\dirname($file), \dirname($file));

        $this->assertFileExists($file);

        $arrayPool = $this->getArrayPool($file);

        $this->assertTrue($arrayPool->getItem('Symfony.Bundle.FrameworkBundle.Tests.Fixtures.Validation.Person')->isHit());
        $this->assertTrue($arrayPool->getItem('Symfony.Bundle.FrameworkBundle.Tests.Fixtures.Validation.Author')->isHit());
    }

    public function testWarmUpAbsoluteFilePath()
    {
        $validatorBuilder = new ValidatorBuilder();
        $validatorBuilder->addXmlMapping(__DIR__.'/../Fixtures/Validation/Resources/person.xml');
        $validatorBuilder->addYamlMapping(__DIR__.'/../Fixtures/Validation/Resources/author.yml');
        $validatorBuilder->addMethodMapping('loadValidatorMetadata');
        $validatorBuilder->enableAttributeMapping();

        $file = sys_get_temp_dir().'/0/cache-validator.php';
        @unlink($file);

        $cacheDir = sys_get_temp_dir().'/1';

        $warmer = new ValidatorCacheWarmer($validatorBuilder, $file);
        $warmer->warmUp($cacheDir, $cacheDir);

        $this->assertFileExists($file);
        $this->assertFileDoesNotExist($cacheDir.'/cache-validator.php');

        $arrayPool = $this->getArrayPool($file);

        $this->assertTrue($arrayPool->getItem('Symfony.Bundle.FrameworkBundle.Tests.Fixtures.Validation.Person')->isHit());
        $this->assertTrue($arrayPool->getItem('Symfony.Bundle.FrameworkBundle.Tests.Fixtures.Validation.Author')->isHit());
    }

    public function testWarmUpWithoutBuilDir()
    {
        $validatorBuilder = new ValidatorBuilder();
        $validatorBuilder->addXmlMapping(__DIR__.'/../Fixtures/Validation/Resources/person.xml');
        $validatorBuilder->addYamlMapping(__DIR__.'/../Fixtures/Validation/Resources/author.yml');
        $validatorBuilder->addMethodMapping('loadValidatorMetadata');
        $validatorBuilder->enableAttributeMapping();

        $file = sys_get_temp_dir().'/cache-validator.php';
        @unlink($file);

        $warmer = new ValidatorCacheWarmer($validatorBuilder, $file);
        $warmer->warmUp(\dirname($file));

        $this->assertFileDoesNotExist($file);

        $arrayPool = $this->getArrayPool($file);

        $this->assertFalse($arrayPool->getItem('Symfony.Bundle.FrameworkBundle.Tests.Fixtures.Validation.Person')->isHit());
        $this->assertFalse($arrayPool->getItem('Symfony.Bundle.FrameworkBundle.Tests.Fixtures.Validation.Author')->isHit());
    }

    public function testWarmUpWithAnnotations()
    {
        $validatorBuilder = new ValidatorBuilder();
        $validatorBuilder->addYamlMapping(__DIR__.'/../Fixtures/Validation/Resources/categories.yml');
        $validatorBuilder->enableAttributeMapping();

        $file = sys_get_temp_dir().'/cache-validator-with-annotations.php';
        @unlink($file);

        $warmer = new ValidatorCacheWarmer($validatorBuilder, $file);
        $warmer->warmUp(\dirname($file), \dirname($file));

        $this->assertFileExists($file);

        $arrayPool = $this->getArrayPool($file);

        $item = $arrayPool->getItem('Symfony.Bundle.FrameworkBundle.Tests.Fixtures.Validation.Category');
        $this->assertTrue($item->isHit());

        $this->assertInstanceOf(ClassMetadata::class, $item->get());
    }

    public function testWarmUpWithoutLoader()
    {
        $validatorBuilder = new ValidatorBuilder();

        $file = sys_get_temp_dir().'/cache-validator-without-loaders.php';
        @unlink($file);

        $warmer = new ValidatorCacheWarmer($validatorBuilder, $file);
        $warmer->warmUp(\dirname($file), \dirname($file));

        $this->assertFileExists($file);
    }

    /**
     * Test that the cache warming process is not broken if a class loader
     * throws an exception (on class / file not found for example).
     */
    public function testClassAutoloadException()
    {
        $this->assertFalse(class_exists($mappedClass = 'AClassThatDoesNotExist_FWB_CacheWarmer_ValidatorCacheWarmerTest', false));

        $file = tempnam(sys_get_temp_dir(), __FUNCTION__);
        @unlink($file);

        $validatorBuilder = new ValidatorBuilder();
        $validatorBuilder->addYamlMapping(__DIR__.'/../Fixtures/Validation/Resources/does_not_exist.yaml');
        $warmer = new ValidatorCacheWarmer($validatorBuilder, $file);

        spl_autoload_register($classloader = function ($class) use ($mappedClass) {
            if ($class === $mappedClass) {
                throw new \DomainException('This exception should be caught by the warmer.');
            }
        }, true, true);

        $warmer->warmUp(\dirname($file), \dirname($file));

        $this->assertFileExists($file);

        spl_autoload_unregister($classloader);
    }

    /**
     * Test that the cache warming process is broken if a class loader throws an
     * exception but that is unrelated to the class load.
     */
    public function testClassAutoloadExceptionWithUnrelatedException()
    {
        $file = tempnam(sys_get_temp_dir(), __FUNCTION__);
        @unlink($file);

        $this->assertFalse(class_exists($mappedClass = 'AClassThatDoesNotExist_FWB_CacheWarmer_ValidatorCacheWarmerTest', false));

        $validatorBuilder = new ValidatorBuilder();
        $validatorBuilder->addYamlMapping(__DIR__.'/../Fixtures/Validation/Resources/does_not_exist.yaml');
        $warmer = new ValidatorCacheWarmer($validatorBuilder, basename($file));

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
