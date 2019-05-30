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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Config\FileLocator;

class FileLocatorTest extends TestCase
{
    public function testLocate()
    {
        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\KernelInterface')->getMock();
        $kernel
            ->expects($this->atLeastOnce())
            ->method('locateResource')
            ->with('@BundleName/some/path', null, true)
            ->willReturn('/bundle-name/some/path');
        $locator = new FileLocator($kernel);
        $this->assertEquals('/bundle-name/some/path', $locator->locate('@BundleName/some/path'));

        $kernel
            ->expects($this->never())
            ->method('locateResource');
        $this->expectException('LogicException');
        $locator->locate('/some/path');
    }

    public function testLocateWithGlobalResourcePath()
    {
        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\KernelInterface')->getMock();
        $kernel
            ->expects($this->atLeastOnce())
            ->method('locateResource')
            ->with('@BundleName/some/path', '/global/resource/path', false);

        $locator = new FileLocator($kernel, '/global/resource/path');
        $locator->locate('@BundleName/some/path', null, false);
    }
}
