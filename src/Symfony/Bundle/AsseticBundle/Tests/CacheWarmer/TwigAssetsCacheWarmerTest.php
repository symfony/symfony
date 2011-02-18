<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Tests\CacheWarmer;

use Symfony\Bundle\AsseticBundle\CacheWarmer\TwigAssetsCacheWarmer;

class TwigAssetsCacheWarmerTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Assetic\\AssetManager')) {
            $this->markTestSkipped('Assetic is not available.');
        }

        $this->kernel = $this->getMockBuilder('Symfony\\Component\\HttpKernel\\Kernel')
            ->disableOriginalConstructor()
            ->setMethods(array('registerRootDir', 'registerBundles', 'registerContainerConfiguration', 'getBundles'))
            ->getMock();
        $this->loader = $this->getMockBuilder('Assetic\\Extension\\Twig\\FormulaLoader')
            ->disableOriginalConstructor()
            ->getMock();

        $this->cacheWarmer = new TwigAssetsCacheWarmer($this->kernel, $this->loader);

        // cache dir
        $this->cacheDir = sys_get_temp_dir();
        @unlink($this->cacheDir.'/twig_assets.php');
    }

    protected function tearDown()
    {
        @unlink($this->cacheDir.'/twig_assets.php');
    }

    public function testCacheWarmer()
    {
        $bundle = $this->getMock('Symfony\\Component\\HttpKernel\\Bundle\\BundleInterface');

        $this->kernel->expects($this->once())
            ->method('getBundles')
            ->will($this->returnValue(array('MyBundle' => $bundle)));
        $bundle->expects($this->once())
            ->method('getPath')
            ->will($this->returnValue(strtr(__DIR__.'/bundle', '\\', '/')));
        $this->loader->expects($this->at(0))
            ->method('load')
            ->with('MyBundle:Parents/Children:child.html.twig')
            ->will($this->returnValue(array('child' => array())));
        $this->loader->expects($this->at(1))
            ->method('load')
            ->with('MyBundle:Parents:parent.html.twig')
            ->will($this->returnValue(array('parent' => array())));
        $this->loader->expects($this->at(2))
            ->method('load')
            ->with('MyBundle::grandparent.html.twig')
            ->will($this->returnValue(array('grandparent' => array())));

        $this->cacheWarmer->warmUp($this->cacheDir);
    }
}
