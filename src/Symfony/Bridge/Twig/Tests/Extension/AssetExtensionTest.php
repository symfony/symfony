<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\VersionStrategy\StaticVersionStrategy;

class AssetExtensionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @group legacy
     */
    public function testLegacyGetAssetUrl()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $foundationExtension = $this->getMockBuilder('Symfony\Bridge\Twig\Extension\HttpFoundationExtension')->disableOriginalConstructor()->getMock();
        $foundationExtension
            ->expects($this->any())
            ->method('generateAbsoluteUrl')
            ->will($this->returnCallback(function ($arg) { return 'http://localhost/'.$arg; }))
        ;

        $package = new Package(new StaticVersionStrategy('22', '%s?version=%s'));
        $packages = new Packages($package);
        $extension = new AssetExtension($packages, $foundationExtension);

        $this->assertEquals('me.png?version=42', $extension->getAssetUrl('me.png', null, false, '42'));
        $this->assertEquals('http://localhost/me.png?version=22', $extension->getAssetUrl('me.png', null, true));
        $this->assertEquals('http://localhost/me.png?version=42', $extension->getAssetUrl('me.png', null, true, '42'));
    }
}
