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
