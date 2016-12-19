<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Tests\Helper;

use Symfony\Component\Templating\Helper\CoreAssetsHelper;

/**
 * @group legacy
 */
class LegacyCoreAssetsHelperTest extends \PHPUnit_Framework_TestCase
{
    protected $package;

    protected function setUp()
    {
        $this->package = $this->getMockBuilder('Symfony\Component\Templating\Asset\PackageInterface')->getMock();
    }

    protected function tearDown()
    {
        $this->package = null;
    }

    public function testAddGetPackage()
    {
        $helper = new CoreAssetsHelper($this->package);

        $helper->addPackage('foo', $this->package);

        $this->assertSame($this->package, $helper->getPackage('foo'));
    }

    public function testGetNonexistingPackage()
    {
        $helper = new CoreAssetsHelper($this->package);

        $this->setExpectedException('\InvalidArgumentException');

        $helper->getPackage('foo');
    }

    public function testGetHelperName()
    {
        $helper = new CoreAssetsHelper($this->package);

        $this->assertEquals('assets', $helper->getName());
    }
}
