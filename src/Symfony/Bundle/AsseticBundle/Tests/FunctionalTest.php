<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Tests;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Util\Filesystem;

/**
 * @group functional
 */
class FunctionalTest extends \PHPUnit_Framework_TestCase
{
    protected $cacheDir;

    protected function setUp()
    {
        if (!class_exists('Assetic\\AssetManager')) {
            $this->markTestSkipped('Assetic is not available.');
        }

        $this->cacheDir = __DIR__.'/Resources/cache';
        if (file_exists($this->cacheDir)) {
            $filesystem = new Filesystem();
            $filesystem->remove($this->cacheDir);
        }

        mkdir($this->cacheDir, 0777, true);
    }

    protected function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->cacheDir);
    }

    public function testRoutes()
    {
        $countRoutes = function($router)
        {
            $count = 0;
            foreach ($router->getRouteCollection()->all() as $name => $route) {
                if (0 === strpos($name, '_assetic_')) {
                    ++$count;
                }
            }

            return $count;
        };

        $kernel = new TestKernel('test', false);
        $kernel->boot();

        $am = $kernel->getContainer()->get('assetic.asset_manager');
        $names = $am->getNames();
        $baseline = $expected = count($names);

        foreach ($names as $name) {
            $asset = $am->get($name);
            foreach ($asset as $leaf) {
                ++$expected;
            }
        }

        $this->assertEquals($baseline, $countRoutes($kernel->getContainer()->get('router')));

        $kernel = new TestKernel('test', true);
        $kernel->boot();
        $this->assertEquals($expected, $countRoutes($kernel->getContainer()->get('router')));
    }

    public function testTwigRenderDebug()
    {
        $kernel = new TestKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer();
        $container->enterScope('request');
        $container->set('request', new Request());

        $content = $container->get('templating')->render('::layout.html.twig');
        $crawler = new Crawler($content);

        $this->assertEquals(3, count($crawler->filter('link[href$=".css"]')));
        $this->assertEquals(2, count($crawler->filter('script[src$=".js"]')));
    }

    public function testPhpRenderDebug()
    {
        $kernel = new TestKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer();
        $container->enterScope('request');
        $container->set('request', new Request());

        $content = $container->get('templating')->render('::layout.html.php');
        $crawler = new Crawler($content);

        $this->assertEquals(3, count($crawler->filter('link[href$=".css"]')));
        $this->assertEquals(2, count($crawler->filter('script[src$=".js"]')));
    }
}
