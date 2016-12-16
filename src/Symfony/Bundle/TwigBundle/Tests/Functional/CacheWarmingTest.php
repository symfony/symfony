<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CacheWarmingTest extends WebTestCase
{
    public function testCacheIsProperlyWarmedWhenTemplatingIsAvailable()
    {
        static::bootKernel(array('environment' => 'with_templating', 'root_config' => 'config_with_templating.yml'));
        $warmer = static::$kernel->getContainer()->get('cache_warmer');
        $warmer->enableOptionalWarmers();
        $warmer->warmUp(static::$kernel->getCacheDir());

        $this->assertFileExists(static::$kernel->getCacheDir() . '/twig');
    }

    public function testCacheIsProperlyWarmedWhenTemplatingIsDisabled()
    {
        static::bootKernel(array('environment' => 'without_templating'));
        $warmer = static::$kernel->getContainer()->get('cache_warmer');
        $warmer->enableOptionalWarmers();
        $warmer->warmUp(static::$kernel->getCacheDir());

        $this->assertFileExists(static::$kernel->getCacheDir() . '/twig');
    }

    protected static function createKernel(array $options = array())
    {
        return parent::createKernel(array('test_case' => 'CacheWarming', 'config_dir' => __DIR__ . '/app') + $options);
    }
}
