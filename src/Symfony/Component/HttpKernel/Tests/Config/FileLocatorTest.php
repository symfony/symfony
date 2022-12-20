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
use Symfony\Component\HttpKernel\KernelInterface;

class FileLocatorTest extends TestCase
{
    public function testLocate()
    {
        $kernel = self::createMock(KernelInterface::class);
        $kernel
            ->expects(self::atLeastOnce())
            ->method('locateResource')
            ->with('@BundleName/some/path')
            ->willReturn('/bundle-name/some/path');
        $locator = new FileLocator($kernel);
        self::assertEquals('/bundle-name/some/path', $locator->locate('@BundleName/some/path'));

        $kernel
            ->expects(self::never())
            ->method('locateResource');
        self::expectException(\LogicException::class);
        $locator->locate('/some/path');
    }
}
