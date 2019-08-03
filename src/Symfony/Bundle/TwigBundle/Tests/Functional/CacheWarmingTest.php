<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\Tests\TestCase;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;

class CacheWarmingTest extends TestCase
{
    public function testCacheIsProperlyWarmedWhenTemplatingIsAvailable()
    {
        $kernel = new CacheWarmingKernel(true);
        $kernel->boot();

        $warmer = $kernel->getContainer()->get('cache_warmer');
        $warmer->enableOptionalWarmers();
        $warmer->warmUp($kernel->getCacheDir());

        $this->assertFileExists($kernel->getCacheDir().'/twig');
    }

    public function testCacheIsProperlyWarmedWhenTemplatingIsDisabled()
    {
        $kernel = new CacheWarmingKernel(false);
        $kernel->boot();

        $warmer = $kernel->getContainer()->get('cache_warmer');
        $warmer->enableOptionalWarmers();
        $warmer->warmUp($kernel->getCacheDir());

        $this->assertFileExists($kernel->getCacheDir().'/twig');
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
        return [new FrameworkBundle(), new TwigBundle()];
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(function ($container) {
            $container->loadFromExtension('framework', [
                'secret' => '$ecret',
                'form' => ['enabled' => false],
            ]);
        });

        if ($this->withTemplating) {
            $loader->load(function ($container) {
                $container->loadFromExtension('framework', [
                    'secret' => '$ecret',
                    'templating' => ['engines' => ['twig']],
                    'router' => ['resource' => '%kernel.project_dir%/Resources/config/empty_routing.yml'],
                    'form' => ['enabled' => false],
                ]);
            });
        }
    }

    public function getProjectDir()
    {
        return __DIR__;
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
