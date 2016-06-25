<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use Symfony\Bridge\Twig\Extension\HttpFoundationExtension;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RequestContext;

class HttpFoundationExtensionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getGenerateAbsoluteUrlData()
     */
    public function testGenerateAbsoluteUrl($expected, $path, $pathinfo)
    {
        $stack = new RequestStack();
        $stack->push(Request::create($pathinfo));
        $extension = new HttpFoundationExtension($stack);

        $this->assertEquals($expected, $extension->generateAbsoluteUrl($path));
    }

    public function getGenerateAbsoluteUrlData()
    {
        return array(
            array('http://localhost/foo.png', '/foo.png', '/foo/bar.html'),
            array('http://localhost/foo/foo.png', 'foo.png', '/foo/bar.html'),
            array('http://localhost/foo/foo.png', 'foo.png', '/foo/bar'),
            array('http://localhost/foo/bar/foo.png', 'foo.png', '/foo/bar/'),

            array('http://example.com/baz', 'http://example.com/baz', '/'),
            array('https://example.com/baz', 'https://example.com/baz', '/'),
            array('//example.com/baz', '//example.com/baz', '/'),
        );
    }

    /**
     * @dataProvider getGenerateAbsoluteUrlRequestContextData
     */
    public function testGenerateAbsoluteUrlWithRequestContext($path, $baseUrl, $host, $scheme, $httpPort, $httpsPort, $expected)
    {
        if (!class_exists('Symfony\Component\Routing\RequestContext')) {
            $this->markTestSkipped('The Routing component is needed to run tests that depend on its request context.');
        }

        $requestContext = new RequestContext($baseUrl, 'GET', $host, $scheme, $httpPort, $httpsPort, $path);
        $extension = new HttpFoundationExtension(new RequestStack(), $requestContext);

        $this->assertEquals($expected, $extension->generateAbsoluteUrl($path));
    }

    /**
     * @dataProvider getGenerateAbsoluteUrlRequestContextData
     */
    public function testGenerateAbsoluteUrlWithoutRequestAndRequestContext($path)
    {
        if (!class_exists('Symfony\Component\Routing\RequestContext')) {
            $this->markTestSkipped('The Routing component is needed to run tests that depend on its request context.');
        }

        $extension = new HttpFoundationExtension(new RequestStack());

        $this->assertEquals($path, $extension->generateAbsoluteUrl($path));
    }

    public function getGenerateAbsoluteUrlRequestContextData()
    {
        return array(
            array('/foo.png', '/foo', 'localhost', 'http', 80, 443, 'http://localhost/foo.png'),
            array('foo.png', '/foo', 'localhost', 'http', 80, 443, 'http://localhost/foo/foo.png'),
            array('foo.png', '/foo/bar/', 'localhost', 'http', 80, 443, 'http://localhost/foo/bar/foo.png'),
            array('/foo.png', '/foo', 'localhost', 'https', 80, 443, 'https://localhost/foo.png'),
            array('foo.png', '/foo', 'localhost', 'https', 80, 443, 'https://localhost/foo/foo.png'),
            array('foo.png', '/foo/bar/', 'localhost', 'https', 80, 443, 'https://localhost/foo/bar/foo.png'),
            array('/foo.png', '/foo', 'localhost', 'http', 443, 80, 'http://localhost:443/foo.png'),
            array('/foo.png', '/foo', 'localhost', 'https', 443, 80, 'https://localhost:80/foo.png'),
        );
    }

    public function testGenerateAbsoluteUrlWithScriptFileName()
    {
        $request = Request::create('http://localhost/app/web/app_dev.php');
        $request->server->set('SCRIPT_FILENAME', '/var/www/app/web/app_dev.php');

        $stack = new RequestStack();
        $stack->push($request);
        $extension = new HttpFoundationExtension($stack);

        $this->assertEquals(
            'http://localhost/app/web/bundles/framework/css/structure.css',
            $extension->generateAbsoluteUrl('/app/web/bundles/framework/css/structure.css')
        );
    }

    /**
     * @dataProvider getGenerateRelativePathData()
     */
    public function testGenerateRelativePath($expected, $path, $pathinfo)
    {
        if (!method_exists('Symfony\Component\HttpFoundation\Request', 'getRelativeUriForPath')) {
            $this->markTestSkipped('Your version of Symfony HttpFoundation is too old.');
        }

        $stack = new RequestStack();
        $stack->push(Request::create($pathinfo));
        $extension = new HttpFoundationExtension($stack);

        $this->assertEquals($expected, $extension->generateRelativePath($path));
    }

    public function getGenerateRelativePathData()
    {
        return array(
            array('../foo.png', '/foo.png', '/foo/bar.html'),
            array('../baz/foo.png', '/baz/foo.png', '/foo/bar.html'),
            array('baz/foo.png', 'baz/foo.png', '/foo/bar.html'),

            array('http://example.com/baz', 'http://example.com/baz', '/'),
            array('https://example.com/baz', 'https://example.com/baz', '/'),
            array('//example.com/baz', '//example.com/baz', '/'),
        );
    }
}
