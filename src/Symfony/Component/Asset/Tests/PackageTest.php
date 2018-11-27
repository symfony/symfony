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
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\Asset\VersionStrategy\StaticVersionStrategy;

class PackageTest extends TestCase
{
    /**
     * @dataProvider getConfigs
     */
    public function testGetUrl($version, $format, $path, $expected)
    {
        $package = new Package($version ? new StaticVersionStrategy($version, $format) : new EmptyVersionStrategy());
        $this->assertEquals($expected, $package->getUrl($path));
    }

    public function getConfigs()
    {
        return array(
            array('v1', '', 'http://example.com/foo', 'http://example.com/foo'),
            array('v1', '', 'https://example.com/foo', 'https://example.com/foo'),
            array('v1', '', '//example.com/foo', '//example.com/foo'),

            array('v1', '', '/foo', '/foo?v1'),
            array('v1', '', 'foo', 'foo?v1'),

            array(null, '', '/foo', '/foo'),
            array(null, '', 'foo', 'foo'),

            array('v1', 'version-%2$s/%1$s', '/foo', '/version-v1/foo'),
            array('v1', 'version-%2$s/%1$s', 'foo', 'version-v1/foo'),
            array('v1', 'version-%2$s/%1$s', 'foo/', 'version-v1/foo/'),
            array('v1', 'version-%2$s/%1$s', '/foo/', '/version-v1/foo/'),
        );
    }

    public function testGetVersion()
    {
        $package = new Package(new StaticVersionStrategy('v1'));
        $this->assertEquals('v1', $package->getVersion('/foo'));
    }
}
