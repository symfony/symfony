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

class PackageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getConfigs
     */
    public function testGetUrl($version, $format, $path, $expected)
    {
        $package = new Package($version, $format);
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

            array('', '', '/foo', '/foo'),
            array('', '', 'foo', 'foo'),

            array('v1', 'version-%2$s/%1$s', '/foo', '/version-v1/foo'),
            array('v1', 'version-%2$s/%1$s', 'foo', 'version-v1/foo'),
            array('v1', 'version-%2$s/%1$s', 'foo/', 'version-v1/foo/'),
            array('v1', 'version-%2$s/%1$s', '/foo/', '/version-v1/foo/'),
        );
    }

    public function testGetUrlWithSpecificVersion()
    {
        $package = new Package('v1');
        $this->assertEquals('/foo?v2', $package->getUrl('/foo', 'v2'));
    }

    public function testGetVersion()
    {
        $package = new Package('v1');
        $this->assertEquals('v1', $package->getVersion());
    }
}
