<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\Templating\Helper;

use PHPUnit\Framework\TestCase;
use Symphony\Bundle\FrameworkBundle\Templating\Helper\AssetsHelper;
use Symphony\Component\Asset\Package;
use Symphony\Component\Asset\Packages;
use Symphony\Component\Asset\VersionStrategy\StaticVersionStrategy;

class AssetsHelperTest extends TestCase
{
    private $helper;

    protected function setUp()
    {
        $fooPackage = new Package(new StaticVersionStrategy('42', '%s?v=%s'));
        $barPackage = new Package(new StaticVersionStrategy('22', '%s?%s'));

        $packages = new Packages($fooPackage, array('bar' => $barPackage));

        $this->helper = new AssetsHelper($packages);
    }

    public function testGetUrl()
    {
        $this->assertEquals('me.png?v=42', $this->helper->getUrl('me.png'));
        $this->assertEquals('me.png?22', $this->helper->getUrl('me.png', 'bar'));
    }

    public function testGetVersion()
    {
        $this->assertEquals('42', $this->helper->getVersion('/'));
        $this->assertEquals('22', $this->helper->getVersion('/', 'bar'));
    }
}
