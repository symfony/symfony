<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Exception\InvalidArgumentException;
use Symfony\Component\Asset\Exception\LogicException;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\PackageInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\VersionStrategy\StaticVersionStrategy;

class PackagesTest extends TestCase
{
    public function testGetterSetters()
    {
        $packages = new Packages();
        $packages->setDefaultPackage($default = $this->createMock(PackageInterface::class));
        $packages->addPackage('a', $a = $this->createMock(PackageInterface::class));

        $this->assertSame($default, $packages->getPackage());
        $this->assertSame($a, $packages->getPackage('a'));

        $packages = new Packages($default, ['a' => $a]);

        $this->assertSame($default, $packages->getPackage());
        $this->assertSame($a, $packages->getPackage('a'));
    }

    public function testGetVersion()
    {
        $packages = new Packages(
            new Package(new StaticVersionStrategy('default')),
            ['a' => new Package(new StaticVersionStrategy('a'))]
        );

        $this->assertSame('default', $packages->getVersion('/foo'));
        $this->assertSame('a', $packages->getVersion('/foo', 'a'));
    }

    public function testGetUrl()
    {
        $packages = new Packages(
            new Package(new StaticVersionStrategy('default')),
            new \ArrayIterator(['a' => new Package(new StaticVersionStrategy('a'))])
        );

        $this->assertSame('/foo?default', $packages->getUrl('/foo'));
        $this->assertSame('/foo?a', $packages->getUrl('/foo', 'a'));
    }

    public function testNoDefaultPackage()
    {
        $this->expectException(LogicException::class);
        $packages = new Packages();
        $packages->getPackage();
    }

    public function testUndefinedPackage()
    {
        $this->expectException(InvalidArgumentException::class);
        $packages = new Packages();
        $packages->getPackage('a');
    }
}
