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
use Symfony\Component\Asset\VersionStrategy\StaticVersionStrategy;

class PathPackageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getConfigs
     */
    public function testGetUrl($basePath, $format, $path, $expected)
    {
        $package = new PathPackage($basePath, new StaticVersionStrategy('v1', $format));
        $this->assertEquals($expected, $package->getUrl($path));
    }

    public function getConfigs()
    {
        return array(
            array('/foo', '', 'http://example.com/foo', 'http://example.com/foo'),
            array('/foo', '', 'https://example.com/foo', 'https://example.com/foo'),
            array('/foo', '', '//example.com/foo', '//example.com/foo'),

            array('', '', '/foo', '/foo?v1'),

            array('/foo', '', '/foo', '/foo/foo?v1'),
            array('/foo', '', 'foo', '/foo/foo?v1'),
            array('foo', '', 'foo', '/foo/foo?v1'),
            array('foo/', '', 'foo', '/foo/foo?v1'),
            array('/foo/', '', 'foo', '/foo/foo?v1'),

            array('/foo', 'version-%2$s/%1$s', '/foo', '/foo/version-v1/foo'),
            array('/foo', 'version-%2$s/%1$s', 'foo', '/foo/version-v1/foo'),
            array('/foo', 'version-%2$s/%1$s', 'foo/', '/foo/version-v1/foo/'),
            array('/foo', 'version-%2$s/%1$s', '/foo/', '/foo/version-v1/foo/'),
        );
    }

    /**
     * @dataProvider getContextConfigs
     */
    public function testGetUrlWithContext($basePathRequest, $basePath, $format, $path, $expected)
    {
        $package = new PathPackage($basePath, new StaticVersionStrategy('v1', $format));
        $package->setContext($this->getContext($basePathRequest));
        $this->assertEquals($expected, $package->getUrl($path));
    }

    public function getContextConfigs()
    {
        return array(
            array('', '/foo', '', '/foo', '/foo/foo?v1'),
            array('', '/foo', '', 'foo', '/foo/foo?v1'),
            array('', 'foo', '', 'foo', '/foo/foo?v1'),
            array('', 'foo/', '', 'foo', '/foo/foo?v1'),
            array('', '/foo/', '', 'foo', '/foo/foo?v1'),

            array('/bar', '/foo', '', '/foo', '/bar/foo/foo?v1'),
            array('/bar', '/foo', '', 'foo', '/bar/foo/foo?v1'),
            array('/bar', 'foo', '', 'foo', '/bar/foo/foo?v1'),
            array('/bar', 'foo/', '', 'foo', '/bar/foo/foo?v1'),
            array('/bar', '/foo/', '', 'foo', '/bar/foo/foo?v1'),
        );
    }

    private function getContext($basePath)
    {
        $context = $this->getMock('Symfony\Component\Asset\Context\ContextInterface');
        $context->expects($this->any())->method('getBasePath')->will($this->returnValue($basePath));

        return $context;
    }
}
