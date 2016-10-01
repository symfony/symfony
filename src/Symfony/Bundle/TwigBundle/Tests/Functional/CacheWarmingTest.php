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

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;

class NewCacheWamingTest extends \PHPUnit_Framework_TestCase
{
    public function testCacheIsProperlyWarmedWhenTemplatingIsAvailable()
    {
        $kernel = new CacheWarmingKernel(true);
        $kernel->boot();

        $warmer = $kernel->getContainer()->get('cache_warmer');
        $warmer->enableOptionalWarmers();
        $warmer->warmUp($kernel->getCacheDir());

        $this->assertTrue(file_exists($kernel->getCacheDir().'/twig'));
    }

    public function testCacheIsProperlyWarmedWhenTemplatingIsDisabled()
    {
        $kernel = new CacheWarmingKernel(false);
        $kernel->boot();

        $warmer = $kernel->getContainer()->get('cache_warmer');
        $warmer->enableOptionalWarmers();
        $warmer->warmUp($kernel->getCacheDir());

        $this->assertTrue(file_exists($kernel->getCacheDir().'/twig'));
    }

    protected function setUp()
    {
        $this->deleteTempDir();
    }

    protected function tearDown()
    {
        $this->deleteTempDir();
    }

    private function deleteTempDir()
    {
        if (!file_exists($dir = sys_get_temp_dir().'/'.Kernel::VERSION.'/CacheWarmingKernel')) {
            return;
        }

        $fs = new Filesystem();
        $fs->remove($dir);
    }
}

class CacheWarmingKernel extends Kernel
{
    private $withTemplating;

    public function __construct($withTemplating)
    {
        $this->withTemplating = $withTemplating;

        parent::__construct(($withTemplating ? 'with' : 'without').'_templating', true);
    }

    public function getName()
    {
        return 'CacheWarming';
    }

    public function registerBundles()
    {
        return array(new FrameworkBundle(), new TwigBundle());
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(function ($container) {
            $container->loadFromExtension('framework', array(
                'secret' => '$ecret',
            ));
        });

        if ($this->withTemplating) {
            $loader->load(function ($container) {
                $container->loadFromExtension('framework', array(
                    'secret' => '$ecret',
                    'templating' => array('engines' => array('twig')),
                    'router' => array('resource' => '%kernel.root_dir%/Resources/config/empty_routing.yml'),
                ));
            });
        }
    }

    public function getCacheDir()
    {
        return sys_get_temp_dir().'/'.Kernel::VERSION.'/CacheWarmingKernel/cache/'.$this->environment;
    }

    public function getLogDir()
    {
        return sys_get_temp_dir().'/'.Kernel::VERSION.'/CacheWarmingKernel/logs';
    }
}
