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

use Symfony\Component\Asset\UrlPackage;

class UrlPackageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getConfigs
     */
    public function testGetUrl($baseUrls, $format, $path, $expected)
    {
        $package = new UrlPackage($baseUrls, 'v1', $format);
        $this->assertEquals($expected, $package->getUrl($path));
    }

    public function getConfigs()
    {
        return array(
            array('http://example.net', '', 'http://example.com/foo', 'http://example.com/foo'),
            array('http://example.net', '', 'https://example.com/foo', 'https://example.com/foo'),
            array('http://example.net', '', '//example.com/foo', '//example.com/foo'),

            array('http://example.com', '', '/foo', 'http://example.com/foo?v1'),
            array('http://example.com', '', 'foo', 'http://example.com/foo?v1'),
            array('http://example.com/', '', 'foo', 'http://example.com/foo?v1'),
            array('http://example.com/foo', '', 'foo', 'http://example.com/foo/foo?v1'),
            array('http://example.com/foo/', '', 'foo', 'http://example.com/foo/foo?v1'),

            array(array('http://example.com'), '', '/foo', 'http://example.com/foo?v1'),
            array(array('http://example.com', 'http://example.net'), '', '/foo', 'http://example.com/foo?v1'),
            array(array('http://example.com', 'http://example.net'), '', '/fooa', 'http://example.net/fooa?v1'),

            array('http://example.com', 'version-%2$s/%1$s', '/foo', 'http://example.com/version-v1/foo'),
            array('http://example.com', 'version-%2$s/%1$s', 'foo', 'http://example.com/version-v1/foo'),
            array('http://example.com', 'version-%2$s/%1$s', 'foo/', 'http://example.com/version-v1/foo/'),
            array('http://example.com', 'version-%2$s/%1$s', '/foo/', 'http://example.com/version-v1/foo/'),
        );
    }

    public function testGetUrlWithSpecificVersion()
    {
        $package = new UrlPackage('http://example.com');
        $this->assertEquals('http://example.com/foo?v2', $package->getUrl('/foo', 'v2'));
    }

    /**
     * @expectedException \LogicException
     */
    public function testNoBaseUrls()
    {
        new UrlPackage(array(), 'v1');
    }
}
