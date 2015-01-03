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

use Symfony\Component\Asset\RequestUrlPackage;

class RequestUrlPackageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getConfigs
     */
    public function testGetUrl($secure, $basePath, $format, $path, $expected)
    {
        $package = new RequestUrlPackage($this->getRequestStack($secure), $basePath, 'v1', $format);
        $this->assertEquals($expected, $package->getUrl($path));
    }

    public function getConfigs()
    {
        return array(
            array(false, 'http://example.com', '', 'foo', 'http://example.com/foo?v1'),
            array(false, array('http://example.com'), '', 'foo', 'http://example.com/foo?v1'),
            array(false, array('http://example.com', 'https://example.com'), '', 'foo', 'http://example.com/foo?v1'),
            array(false, array('http://example.com', 'https://example.com'), '', 'fooa', 'https://example.com/fooa?v1'),
            array(false, array('http://example.com/bar'), '', 'foo', 'http://example.com/bar/foo?v1'),
            array(false, array('http://example.com/bar/'), '', 'foo', 'http://example.com/bar/foo?v1'),
            array(false, array('//example.com/bar/'), '', 'foo', '//example.com/bar/foo?v1'),

            array(true, array('http://example.com'), '', 'foo', 'http://example.com/foo?v1'),
            array(true, array('http://example.com', 'https://example.com'), '', 'foo', 'https://example.com/foo?v1'),
        );
    }

    /**
     * @expectedException \LogicException
     */
    public function testNoBaseUrls()
    {
        new RequestUrlPackage($this->getRequestStack(false), array());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testWrongBaseUrl()
    {
        new RequestUrlPackage($this->getRequestStack(false), array('not-a-url'));
    }

    private function getRequestStack($secure)
    {
        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $request->expects($this->any())->method('isSecure')->will($this->returnValue($secure));

        $stack = $this->getMock('Symfony\Component\HttpFoundation\RequestStack');
        $stack->expects($this->any())->method('getCurrentRequest')->will($this->returnValue($request));

        return $stack;
    }
}
