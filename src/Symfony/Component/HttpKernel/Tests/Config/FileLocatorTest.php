<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Config;

use Symfony\Component\HttpKernel\Config\FileLocator;
use Symfony\Component\HttpKernel\KernelInterface;

class FileLocatorTest extends \PHPUnit_Framework_TestCase
{

    /** @var KernelInterface */
    private $kernel;
    
    public function setUp()
    {
        $this->kernel = $this->getMock('Symfony\Component\HttpKernel\KernelInterface');
    }

    public function tearDown()
    {
        $this->kernel = null;
    }

    public function testLocate()
    {
        $this->kernel
            ->expects($this->atLeastOnce())
            ->method('locateResource')
            ->with('@BundleName/some/path', null, true)
            ->will($this->returnValue('/bundle-name/some/path'));
        $locator = new FileLocator($this->kernel);
        $this->assertEquals('/bundle-name/some/path', $locator->locate('@BundleName/some/path'));

        $this->kernel
            ->expects($this->never())
            ->method('locateResource');
        $this->setExpectedException('LogicException');
        $locator->locate('/some/path');
    }

    public function testLocateWithGlobalResourcePath()
    {
        $this->kernel
            ->expects($this->atLeastOnce())
            ->method('locateResource')
            ->with('@BundleName/some/path', '/global/resource/path', false);

        $locator = new FileLocator($this->kernel, '/global/resource/path');
        $locator->locate('@BundleName/some/path', null, false);
    }

}
