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
use Symfony\Component\Asset\PackageInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\Preload\PreloadManagerInterface;
use Symfony\Component\Asset\VersionStrategy\StaticVersionStrategy;

class PackagesTest extends \PHPUnit_Framework_TestCase
{
    public function testGetterSetters()
    {
        $packages = new Packages();
        $packages->setDefaultPackage($default = $this->getMockBuilder('Symfony\Component\Asset\PackageInterface')->getMock());
        $packages->addPackage('a', $a = $this->getMockBuilder('Symfony\Component\Asset\PackageInterface')->getMock());

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

    public function testGetAndPreloadUrl()
    {
        $preloadManager = $this->createMock(PreloadManagerInterface::class);
        $preloadManager
            ->expects($this->exactly(2))
            ->method('addResource')
            ->withConsecutive(
                array($this->equalTo('/foo?default'), $this->equalTo(''), $this->equalTo(false)),
                array($this->equalTo('/foo?a'), $this->equalTo('script'), $this->equalTo(true))
            )
        ;

        $packages = new Packages(
            new Package(new StaticVersionStrategy('default'), null, $preloadManager),
            array('a' => new Package(new StaticVersionStrategy('a'), null, $preloadManager))
        );

        $this->assertEquals('/foo?default', $packages->getAndPreloadUrl('/foo'));
        $this->assertEquals('/foo?a', $packages->getAndPreloadUrl('/foo', 'script', true, 'a'));
    }

    /**
     * @expectedException \Symfony\Component\Asset\Exception\LogicException
     */
    public function testNoDefaultPackage()
    {
        $packages = new Packages();
        $packages->getPackage();
    }

    /**
     * @expectedException \Symfony\Component\Asset\Exception\InvalidArgumentException
     */
    public function testUndefinedPackage()
    {
        $packages = new Packages();
        $packages->getPackage('a');
    }

    /**
     * @expectedException \Symfony\Component\Asset\Exception\InvalidArgumentException
     */
    public function testDoesNotSupportPreloading()
    {
        $packages = new Packages($this->createMock(PackageInterface::class));
        $packages->getAndPreloadUrl('/foo');
    }
}
