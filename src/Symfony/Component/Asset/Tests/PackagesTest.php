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

use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\VersionStrategy\StaticVersionStrategy;
use Symfony\Component\Asset\Exception\InvalidArgumentException;
use Symfony\Component\Asset\Exception\LogicException;

class PackagesTest extends \PHPUnit_Framework_TestCase
{
    public function testGetterSetters()
    {
        $packages = new Packages();
        $packages->setDefaultPackage($default = $this->getMock('Symfony\Component\Asset\PackageInterface'));
        $packages->addPackage('a', $a = $this->getMock('Symfony\Component\Asset\PackageInterface'));

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
     * @expectedException LogicException
     */
    public function testNoDefaultPackage()
    {
        $packages = new Packages();
        $packages->getPackage();
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testUndefinedPackage()
    {
        $packages = new Packages();
        $packages->getPackage('a');
    }
}
