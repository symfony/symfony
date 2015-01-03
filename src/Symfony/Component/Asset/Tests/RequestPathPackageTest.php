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

use Symfony\Component\Asset\RequestPathPackage;

class RequestPathPackageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getConfigs
     */
    public function testGetUrl($basePathRequest, $basePath, $format, $path, $expected)
    {
        $package = new RequestPathPackage($this->getRequestStack($basePathRequest), $basePath, 'v1', $format);
        $this->assertEquals($expected, $package->getUrl($path));
    }

    public function getConfigs()
    {
        return array(
            array('', '/foo', '', '/foo', '/foo?v1'),
            array('', '/foo', '', 'foo', '/foo/foo?v1'),
            array('', 'foo', '', 'foo', '/foo/foo?v1'),
            array('', 'foo/', '', 'foo', '/foo/foo?v1'),
            array('', '/foo/', '', 'foo', '/foo/foo?v1'),

            array('/bar', '/foo', '', '/foo', '/foo?v1'),
            array('/bar', '/foo', '', 'foo', '/bar/foo/foo?v1'),
            array('/bar', 'foo', '', 'foo', '/bar/foo/foo?v1'),
            array('/bar', 'foo/', '', 'foo', '/bar/foo/foo?v1'),
            array('/bar', '/foo/', '', 'foo', '/bar/foo/foo?v1'),

            array(false, '/foo/', '', 'foo', '/foo/foo?v1'),
        );
    }

    private function getRequestStack($basePath)
    {
        $stack = $this->getMock('Symfony\Component\HttpFoundation\RequestStack');

        if (false === $basePath) {
            $stack->expects($this->any())->method('getCurrentRequest')->will($this->returnValue(null));

            return $stack;
        }

        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $request->expects($this->any())->method('getBasePath')->will($this->returnValue($basePath));

        $stack->expects($this->any())->method('getCurrentRequest')->will($this->returnValue($request));

        return $stack;
    }
}
