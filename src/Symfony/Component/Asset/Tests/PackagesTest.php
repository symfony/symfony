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
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\VersionStrategy\StaticVersionStrategy;

class PackagesTest extends TestCase
{
    public function testGetterSetters()
    {
        $packages = new Packages();
        $packages->setDefaultPackage($default = $this->getMockBuilder('Symfony\Component\Asset\PackageInterface')->getMock());
        $packages->addPackage('a', $a = $this->getMockBuilder('Symfony\Component\Asset\PackageInterface')->getMock());

        $this->assertEquals($default, $packages->getPackage());
        $this->assertEquals($a, $packages->getPackage('a'));

        $packages = new Packages($default, ['a' => $a]);

        $this->assertEquals($default, $packages->getPackage());
        $this->assertEquals($a, $packages->getPackage('a'));
    }

    public function testGetVersion()
    {
        $packages = new Packages(
            new Package(new StaticVersionStrategy('default')),
            ['a' => new Package(new StaticVersionStrategy('a'))]
        );

        $this->assertEquals('default', $packages->getVersion('/foo'));
        $this->assertEquals('a', $packages->getVersion('/foo', 'a'));
    }

    public function testGetUrl()
    {
        $packages = new Packages(
            new Package(new StaticVersionStrategy('default')),
            ['a' => new Package(new StaticVersionStrategy('a'))]
        );

        $this->assertEquals('/foo?default', $packages->getUrl('/foo'));
        $this->assertEquals('/foo?a', $packages->getUrl('/foo', 'a'));
    }

    public function testNoDefaultPackage()
    {
        $this->expectException('Symfony\Component\Asset\Exception\LogicException');
        $packages = new Packages();
        $packages->getPackage();
    }

    public function testUndefinedPackage()
    {
        $this->expectException('Symfony\Component\Asset\Exception\InvalidArgumentException');
        $packages = new Packages();
        $packages->getPackage('a');
    }
}
