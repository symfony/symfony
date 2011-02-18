<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Tests;

use Symfony\Bundle\AsseticBundle\Tests\Kernel\TestKernel;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

class FunctionalTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Assetic\\AssetManager')) {
            $this->markTestSkipped('Assetic is not available.');
        }

        $cache = __DIR__.'/Kernel/cache';
        if (!is_dir($cache)) {
            mkdir($cache);
        } else {
            shell_exec('rm -rf '.escapeshellarg(__DIR__.'/Kernel/cache/*'));
        }
    }

    protected function tearDown()
    {
        shell_exec('rm -rf '.escapeshellarg(__DIR__.'/Kernel/cache'));
    }

    /**
     * @dataProvider provideDebugAndAssetCount
     */
    public function testKernel($debug, $count)
    {
        $kernel = new TestKernel('test', $debug);
        $kernel->boot();
        $container = $kernel->getContainer();
        $container->get('cache_warmer')->warmUp($container->getParameter('kernel.cache_dir'));

        $assets = $container->get('assetic.asset_manager')->all();

        $this->assertEquals($count, count($assets));
    }

    /**
     * @dataProvider provideDebugAndAssetCount
     */
    public function testRoutes($debug, $count)
    {
        $kernel = new TestKernel('test', $debug);
        $kernel->boot();
        $container = $kernel->getContainer();
        $container->get('cache_warmer')->warmUp($container->getParameter('kernel.cache_dir'));

        $routes = $container->get('router')->getRouteCollection()->all();

        $matches = 0;
        foreach (array_keys($routes) as $name) {
            if (0 === strpos($name, 'assetic_')) {
                ++$matches;
            }
        }

        $this->assertEquals($count, $matches);
    }

    public function testTwigRenderDebug()
    {
        $kernel = new TestKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer();
        $container->enterScope('request');
        $container->set('request', new Request());
        $container->get('cache_warmer')->warmUp($container->getParameter('kernel.cache_dir'));

        $content = $container->get('templating')->render('::layout.html.twig');
        $crawler = new Crawler($content);

        $this->assertEquals(3, count($crawler->filter('link[href$=".css"]')));
        $this->assertEquals(2, count($crawler->filter('script[src$=".js"]')));
    }

    public function testPhpRenderDebug()
    {
        $this->markTestIncomplete('PHP templating is not ready yet.');

        $kernel = new TestKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer();
        $container->enterScope('request');
        $container->set('request', new Request());
        $container->get('cache_warmer')->warmUp($container->getParameter('kernel.cache_dir'));

        $content = $container->get('templating')->render('::layout.html.php');
        $crawler = new Crawler($content);

        $this->assertEquals(3, count($crawler->filter('link[href$=".css"]')));
        $this->assertEquals(2, count($crawler->filter('script[src$=".js"]')));
    }

    public function provideDebugAndAssetCount()
    {
        return array(
            array(true, 5),
            array(false, 2),
        );
    }
}
