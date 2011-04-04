<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Tests\Factory;

use Symfony\Bundle\AsseticBundle\Factory\AssetFactory;

class AssetFactoryTest extends \PHPUnit_Framework_TestCase
{
    protected $kernel;
    protected $factory;

    protected function setUp()
    {
        if (!class_exists('Assetic\\AssetManager')) {
            $this->markTestSkipped('Assetic is not available.');
        }

        $this->kernel = $this->getMock('Symfony\\Component\\HttpKernel\\KernelInterface');
        $this->factory = new AssetFactory($this->kernel, '/path/to/web');
    }

    public function testBundleNotation()
    {
        $input = '@MyBundle/Resources/css/main.css';

        $this->kernel->expects($this->once())
            ->method('locateResource')
            ->with($input)
            ->will($this->returnValue('/path/to/bundle/Resources/css/main.css'));

        $this->factory->createAsset($input);
    }

    /**
     * @dataProvider getGlobs
     */
    public function testBundleGlobNotation($input)
    {
        $this->kernel->expects($this->once())
            ->method('locateResource')
            ->with('@MyBundle/Resources/css/')
            ->will($this->returnValue('/path/to/bundle/Resources/css/'));

        $this->factory->createAsset($input);
    }

    public function getGlobs()
    {
        return array(
            array('@MyBundle/Resources/css/*'),
            array('@MyBundle/Resources/css/*/*.css'),
        );
    }
}
