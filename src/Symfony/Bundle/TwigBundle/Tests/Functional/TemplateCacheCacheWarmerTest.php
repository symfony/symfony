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

class TemplateCacheCacheWarmerTest extends \PHPUnit_Framework_TestCase
{
    public function test()
    {
        $kernel = new TemplateCacheCacheWarmerKernel('TemplateCacheCacheWarmer', true);
        $kernel->boot();

        $container = $kernel->getContainer();
        $cacheWarmer = $container->get('cache_warmer');

        $cacheWarmer->enableOptionalWarmers();
        $cacheWarmer->warmUp($kernel->getCacheDir());

        $cacheDirectory = $kernel->getCacheDir().'/twig';
        $this->assertTrue(file_exists($cacheDirectory), 'Cache directory does not exist.');

        $template = 'TwigBundle::layout.html.twig';
        $twig = $container->get('twig');
        $twig->loadTemplate($template);

        $cacheFileName = $twig->getCacheFilename($template);
        $this->assertTrue(file_exists($cacheFileName), 'Cache file does not exist.');
    }

    protected function setUp()
    {
        $this->deleteTempDir();
    }

    protected function tearDown()
    {
        $this->deleteTempDir();
    }

    protected function deleteTempDir()
    {
        if (!file_exists($dir = sys_get_temp_dir().'/'.Kernel::VERSION.'/TemplateCacheCacheWarmerKernel')) {
            return;
        }

        $fs = new Filesystem();
        $fs->remove($dir);
    }
}

class TemplateCacheCacheWarmerKernel extends Kernel
{
    public function registerBundles()
    {
        return array(new FrameworkBundle(), new TwigBundle());
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(function ($container) {
            $container->loadFromExtension('framework', array(
                'secret' => '$ecret',
                'router' => array(
                    'resource' => __DIR__.'/Resources/config/routing.yml',
                ),
                'templating' => array(
                    'engines' => array('twig'),
                ),
            ));
        });
    }

    public function getCacheDir()
    {
        return sys_get_temp_dir().'/'.Kernel::VERSION.'/TemplateCacheCacheWarmerKernel/cache/'.$this->environment;
    }

    public function getLogDir()
    {
        return sys_get_temp_dir().'/'.Kernel::VERSION.'/TemplateCacheCacheWarmerKernel/logs';
    }
}
