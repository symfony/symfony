<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Asset\Tests;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Asset\Package;
use Symphony\Component\Asset\Packages;
use Symphony\Component\Asset\VersionStrategy\StaticVersionStrategy;

class PackagesTest extends TestCase
{
    public function testGetterSetters()
    {
        $packages = new Packages();
        $packages->setDefaultPackage($default = $this->getMockBuilder('Symphony\Component\Asset\PackageInterface')->getMock());
        $packages->addPackage('a', $a = $this->getMockBuilder('Symphony\Component\Asset\PackageInterface')->getMock());

        $this->assertEquals($default, $packages->getPackage());
        $this->assertEquals($a, $packages->getPackage('a'));

        $packages = new Packages($default, array('a' => $a));

        $this->assertEquals($default, $packages->getPackage());
        $this->assertEquals($a, $packages->getPackage('a'));
    }

    public function testGetVersion()
    {
        $packages = new Packages(
            new Package(new StaticVersionStrategy('default')),
            array('a' => new Package(new StaticVersionStrategy('a')))
        );

        $this->assertEquals('default', $packages->getVersion('/foo'));
        $this->assertEquals('a', $packages->getVersion('/foo', 'a'));
    }

    public function testGetUrl()
    {
        $packages = new Packages(
            new Package(new StaticVersionStrategy('default')),
            array('a' => new Package(new StaticVersionStrategy('a')))
        );

        $this->assertEquals('/foo?default', $packages->getUrl('/foo'));
        $this->assertEquals('/foo?a', $packages->getUrl('/foo', 'a'));
    }

    /**
     * @expectedException \Symphony\Component\Asset\Exception\LogicException
     */
    public function testNoDefaultPackage()
    {
        $packages = new Packages();
        $packages->getPackage();
    }

    /**
     * @expectedException \Symphony\Component\Asset\Exception\InvalidArgumentException
     */
    public function testUndefinedPackage()
    {
        $packages = new Packages();
        $packages->getPackage('a');
    }
}
