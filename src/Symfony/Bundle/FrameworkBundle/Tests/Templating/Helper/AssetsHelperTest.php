<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Templating\Helper;

use Symfony\Bundle\FrameworkBundle\Templating\Helper\AssetsHelper;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\VersionStrategy\StaticVersionStrategy;

class AssetsHelperTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @group legacy
     */
    public function testLegacyGetUrl()
    {
        $package = new Package(new StaticVersionStrategy('22', '%s?version=%s'));
        $packages = new Packages($package);
        $helper = new AssetsHelper($packages);

        $this->assertEquals('me.png?version=42', $helper->getUrl('me.png', null, '42'));
    }

    /**
     * @group legacy
     */
    public function testLegacyGetVersion()
    {
        $package = new Package(new StaticVersionStrategy('22'));
        $imagePackage = new Package(new StaticVersionStrategy('42'));
        $packages = new Packages($package, array('images' => $imagePackage));
        $helper = new AssetsHelper($packages);

        $this->assertEquals('22', $helper->getVersion());
        $this->assertEquals('22', $helper->getVersion('/foo'));
        $this->assertEquals('42', $helper->getVersion('images'));
        $this->assertEquals('42', $helper->getVersion('/foo', 'images'));
    }
}
