<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset\Tests\VersionStrategy;

use Symfony\Component\Asset\VersionStrategy\ManifestVersionStrategy;

class ManifestVersionStrategyTest extends \PHPUnit_Framework_TestCase
{
    public function testGetVersion()
    {
        $path = 'foo';
        $versionized = 'foo-versionized';
        $manifestVersionStrategy = new ManifestVersionStrategy(array($path => $versionized));
        $this->assertEquals('', $manifestVersionStrategy->getVersion($path));
    }

    public function testApplyVersion()
    {
        $path = 'foo';
        $versionized = 'foo-versionized';
        $manifestVersionStrategy = new ManifestVersionStrategy(array($path => $versionized));
        $this->assertEquals($versionized, $manifestVersionStrategy->applyVersion($path));
    }

    public function testApplyVersionMissing()
    {
        $path = 'foo';
        $manifestVersionStrategy = new ManifestVersionStrategy(array());
        $this->assertEquals($path, $manifestVersionStrategy->applyVersion($path));
    }
}
