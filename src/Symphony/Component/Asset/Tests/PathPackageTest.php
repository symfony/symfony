<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Asset\Tests;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Asset\PathPackage;
use Symphony\Component\Asset\VersionStrategy\StaticVersionStrategy;

class PathPackageTest extends TestCase
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

            array('/foo', '', '/bar', '/bar?v1'),
            array('/foo', '', 'bar', '/foo/bar?v1'),
            array('foo', '', 'bar', '/foo/bar?v1'),
            array('foo/', '', 'bar', '/foo/bar?v1'),
            array('/foo/', '', 'bar', '/foo/bar?v1'),

            array('/foo', 'version-%2$s/%1$s', '/bar', '/version-v1/bar'),
            array('/foo', 'version-%2$s/%1$s', 'bar', '/foo/version-v1/bar'),
            array('/foo', 'version-%2$s/%1$s', 'bar/', '/foo/version-v1/bar/'),
            array('/foo', 'version-%2$s/%1$s', '/bar/', '/version-v1/bar/'),
        );
    }

    /**
     * @dataProvider getContextConfigs
     */
    public function testGetUrlWithContext($basePathRequest, $basePath, $format, $path, $expected)
    {
        $package = new PathPackage($basePath, new StaticVersionStrategy('v1', $format), $this->getContext($basePathRequest));

        $this->assertEquals($expected, $package->getUrl($path));
    }

    public function getContextConfigs()
    {
        return array(
            array('', '/foo', '', '/baz', '/baz?v1'),
            array('', '/foo', '', 'baz', '/foo/baz?v1'),
            array('', 'foo', '', 'baz', '/foo/baz?v1'),
            array('', 'foo/', '', 'baz', '/foo/baz?v1'),
            array('', '/foo/', '', 'baz', '/foo/baz?v1'),

            array('/bar', '/foo', '', '/baz', '/baz?v1'),
            array('/bar', '/foo', '', 'baz', '/bar/foo/baz?v1'),
            array('/bar', 'foo', '', 'baz', '/bar/foo/baz?v1'),
            array('/bar', 'foo/', '', 'baz', '/bar/foo/baz?v1'),
            array('/bar', '/foo/', '', 'baz', '/bar/foo/baz?v1'),
        );
    }

    public function testVersionStrategyGivesAbsoluteURL()
    {
        $versionStrategy = $this->getMockBuilder('Symphony\Component\Asset\VersionStrategy\VersionStrategyInterface')->getMock();
        $versionStrategy->expects($this->any())
            ->method('applyVersion')
            ->willReturn('https://cdn.com/bar/main.css');
        $package = new PathPackage('/subdirectory', $versionStrategy, $this->getContext('/bar'));

        $this->assertEquals('https://cdn.com/bar/main.css', $package->getUrl('main.css'));
    }

    private function getContext($basePath)
    {
        $context = $this->getMockBuilder('Symphony\Component\Asset\Context\ContextInterface')->getMock();
        $context->expects($this->any())->method('getBasePath')->will($this->returnValue($basePath));

        return $context;
    }
}
