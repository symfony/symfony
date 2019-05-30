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
use Symfony\Component\Asset\PathPackage;
use Symfony\Component\Asset\VersionStrategy\StaticVersionStrategy;

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
        return [
            ['/foo', '', 'http://example.com/foo', 'http://example.com/foo'],
            ['/foo', '', 'https://example.com/foo', 'https://example.com/foo'],
            ['/foo', '', '//example.com/foo', '//example.com/foo'],

            ['', '', '/foo', '/foo?v1'],

            ['/foo', '', '/bar', '/bar?v1'],
            ['/foo', '', 'bar', '/foo/bar?v1'],
            ['foo', '', 'bar', '/foo/bar?v1'],
            ['foo/', '', 'bar', '/foo/bar?v1'],
            ['/foo/', '', 'bar', '/foo/bar?v1'],

            ['/foo', 'version-%2$s/%1$s', '/bar', '/version-v1/bar'],
            ['/foo', 'version-%2$s/%1$s', 'bar', '/foo/version-v1/bar'],
            ['/foo', 'version-%2$s/%1$s', 'bar/', '/foo/version-v1/bar/'],
            ['/foo', 'version-%2$s/%1$s', '/bar/', '/version-v1/bar/'],
        ];
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
        return [
            ['', '/foo', '', '/baz', '/baz?v1'],
            ['', '/foo', '', 'baz', '/foo/baz?v1'],
            ['', 'foo', '', 'baz', '/foo/baz?v1'],
            ['', 'foo/', '', 'baz', '/foo/baz?v1'],
            ['', '/foo/', '', 'baz', '/foo/baz?v1'],

            ['/bar', '/foo', '', '/baz', '/baz?v1'],
            ['/bar', '/foo', '', 'baz', '/bar/foo/baz?v1'],
            ['/bar', 'foo', '', 'baz', '/bar/foo/baz?v1'],
            ['/bar', 'foo/', '', 'baz', '/bar/foo/baz?v1'],
            ['/bar', '/foo/', '', 'baz', '/bar/foo/baz?v1'],
        ];
    }

    public function testVersionStrategyGivesAbsoluteURL()
    {
        $versionStrategy = $this->getMockBuilder('Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface')->getMock();
        $versionStrategy->expects($this->any())
            ->method('applyVersion')
            ->willReturn('https://cdn.com/bar/main.css');
        $package = new PathPackage('/subdirectory', $versionStrategy, $this->getContext('/bar'));

        $this->assertEquals('https://cdn.com/bar/main.css', $package->getUrl('main.css'));
    }

    private function getContext($basePath)
    {
        $context = $this->getMockBuilder('Symfony\Component\Asset\Context\ContextInterface')->getMock();
        $context->expects($this->any())->method('getBasePath')->willReturn($basePath);

        return $context;
    }
}
