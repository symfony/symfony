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

use Symfony\Component\Asset\PathPackage;

class PathPackageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getConfigs
     */
    public function testGetUrl($basePath, $format, $path, $expected)
    {
        $package = new PathPackage($basePath, 'v1', $format);
        $this->assertEquals($expected, $package->getUrl($path));
    }

    public function getConfigs()
    {
        return array(
            array('/foo', '', 'http://example.com/foo', 'http://example.com/foo'),
            array('/foo', '', 'https://example.com/foo', 'https://example.com/foo'),
            array('/foo', '', '//example.com/foo', '//example.com/foo'),

            array('', '', '/foo', '/foo?v1'),

            array('/foo', '', '/foo', '/foo?v1'),
            array('/foo', '', 'foo', '/foo/foo?v1'),
            array('foo', '', 'foo', '/foo/foo?v1'),
            array('foo/', '', 'foo', '/foo/foo?v1'),
            array('/foo/', '', 'foo', '/foo/foo?v1'),

            array('/foo', 'version-%2$s/%1$s', '/foo', '/version-v1/foo'),
            array('/foo', 'version-%2$s/%1$s', 'foo', '/foo/version-v1/foo'),
            array('/foo', 'version-%2$s/%1$s', 'foo/', '/foo/version-v1/foo/'),
            array('/foo', 'version-%2$s/%1$s', '/foo/', '/version-v1/foo/'),
        );
    }

    public function testGetUrlWithSpecificVersion()
    {
        $package = new PathPackage('v1');
        $this->assertEquals('/foo?v2', $package->getUrl('/foo', 'v2'));
    }
}
