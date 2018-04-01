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

use Symphony\Bundle\FrameworkBundle\CacheWarmer\ValidatorCacheWarmer;
use Symphony\Bundle\FrameworkBundle\Tests\TestCase;
use Symphony\Component\Cache\Adapter\ArrayAdapter;
use Symphony\Component\Validator\ValidatorBuilder;

class ValidatorCacheWarmerTest extends TestCase
{
    public function testWarmUp()
    {
        $validatorBuilder = new ValidatorBuilder();
        $validatorBuilder->addXmlMapping(__DIR__.'/../Fixtures/Validation/Resources/person.xml');
        $validatorBuilder->addYamlMapping(__DIR__.'/../Fixtures/Validation/Resources/author.yml');
        $validatorBuilder->addMethodMapping('loadValidatorMetadata');
        $validatorBuilder->enableAnnotationMapping();

        $file = sys_get_temp_dir().'/cache-validator.php';
        @unlink($file);

        $fallbackPool = new ArrayAdapter();

        $warmer = new ValidatorCacheWarmer($validatorBuilder, $file, $fallbackPool);
        $warmer->warmUp(dirname($file));

        $this->assertFileExists($file);

        $values = require $file;

        $this->assertInternalType('array', $values);
        $this->assertCount(2, $values);
        $this->assertArrayHasKey('Symphony.Bundle.FrameworkBundle.Tests.Fixtures.Validation.Person', $values);
        $this->assertArrayHasKey('Symphony.Bundle.FrameworkBundle.Tests.Fixtures.Validation.Author', $values);

        $values = $fallbackPool->getValues();

        $this->assertInternalType('array', $values);
        $this->assertCount(2, $values);
        $this->assertArrayHasKey('Symphony.Bundle.FrameworkBundle.Tests.Fixtures.Validation.Person', $values);
        $this->assertArrayHasKey('Symphony.Bundle.FrameworkBundle.Tests.Fixtures.Validation.Author', $values);
    }

    public function testWarmUpWithAnnotations()
    {
        $validatorBuilder = new ValidatorBuilder();
        $validatorBuilder->addYamlMapping(__DIR__.'/../Fixtures/Validation/Resources/categories.yml');
        $validatorBuilder->enableAnnotationMapping();

        $file = sys_get_temp_dir().'/cache-validator-with-annotations.php';
        @unlink($file);

        $fallbackPool = new ArrayAdapter();

        $warmer = new ValidatorCacheWarmer($validatorBuilder, $file, $fallbackPool);
        $warmer->warmUp(dirname($file));

        $this->assertFileExists($file);

        $values = require $file;

        $this->assertInternalType('array', $values);
        $this->assertCount(1, $values);
        $this->assertArrayHasKey('Symphony.Bundle.FrameworkBundle.Tests.Fixtures.Validation.Category', $values);

        // Simple check to make sure that at least one constraint is actually cached, in this case the "id" property Type.
        $this->assertContains('"int"', $values['Symphony.Bundle.FrameworkBundle.Tests.Fixtures.Validation.Category']);

        $values = $fallbackPool->getValues();

        $this->assertInternalType('array', $values);
        $this->assertCount(2, $values);
        $this->assertArrayHasKey('Symphony.Bundle.FrameworkBundle.Tests.Fixtures.Validation.Category', $values);
        $this->assertArrayHasKey('Symphony.Bundle.FrameworkBundle.Tests.Fixtures.Validation.SubCategory', $values);
    }

    public function testWarmUpWithoutLoader()
    {
        $validatorBuilder = new ValidatorBuilder();

        $file = sys_get_temp_dir().'/cache-validator-without-loaders.php';
        @unlink($file);

        $fallbackPool = new ArrayAdapter();

        $warmer = new ValidatorCacheWarmer($validatorBuilder, $file, $fallbackPool);
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
