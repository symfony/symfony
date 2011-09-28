<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpFoundation;

use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Cookie;

class ResponseHeaderBagTest extends \PHPUnit_Framework_TestCase
{
    public function testCacheControlHeader()
    {
        $bag = new ResponseHeaderBag(array());
        $this->assertEquals('no-cache', $bag->get('Cache-Control'));
        $this->assertTrue($bag->hasCacheControlDirective('no-cache'));

        $bag = new ResponseHeaderBag(array('Cache-Control' => 'public'));
        $this->assertEquals('public', $bag->get('Cache-Control'));
        $this->assertTrue($bag->hasCacheControlDirective('public'));

        $bag = new ResponseHeaderBag(array('ETag' => 'abcde'));
        $this->assertEquals('private, must-revalidate', $bag->get('Cache-Control'));
        $this->assertTrue($bag->hasCacheControlDirective('private'));
        $this->assertTrue($bag->hasCacheControlDirective('must-revalidate'));
        $this->assertFalse($bag->hasCacheControlDirective('max-age'));

        $bag = new ResponseHeaderBag(array('Expires' => 'Wed, 16 Feb 2011 14:17:43 GMT'));
        $this->assertEquals('private, must-revalidate', $bag->get('Cache-Control'));

        $bag = new ResponseHeaderBag(array(
            'Expires' => 'Wed, 16 Feb 2011 14:17:43 GMT',
            'Cache-Control' => 'max-age=3600'
        ));
        $this->assertEquals('max-age=3600, private', $bag->get('Cache-Control'));

        $bag = new ResponseHeaderBag(array('Last-Modified' => 'abcde'));
        $this->assertEquals('private, must-revalidate', $bag->get('Cache-Control'));

        $bag = new ResponseHeaderBag(array('Etag' => 'abcde', 'Last-Modified' => 'abcde'));
        $this->assertEquals('private, must-revalidate', $bag->get('Cache-Control'));

        $bag = new ResponseHeaderBag(array('cache-control' => 'max-age=100'));
        $this->assertEquals('max-age=100, private', $bag->get('Cache-Control'));

        $bag = new ResponseHeaderBag(array('cache-control' => 's-maxage=100'));
        $this->assertEquals('s-maxage=100', $bag->get('Cache-Control'));

        $bag = new ResponseHeaderBag(array('cache-control' => 'private, max-age=100'));
        $this->assertEquals('max-age=100, private', $bag->get('Cache-Control'));

        $bag = new ResponseHeaderBag(array('cache-control' => 'public, max-age=100'));
        $this->assertEquals('max-age=100, public', $bag->get('Cache-Control'));

        $bag = new ResponseHeaderBag();
        $bag->set('Last-Modified', 'abcde');
        $this->assertEquals('private, must-revalidate', $bag->get('Cache-Control'));
    }

    public function testToStringIncludesCookieHeaders()
    {
        $bag = new ResponseHeaderBag(array());
        $bag->setCookie(new Cookie('foo', 'bar'));

        $this->assertContains("Set-Cookie: foo=bar; httponly", explode("\r\n", $bag->__toString()));

        $bag->clearCookie('foo');

        $this->assertContains("Set-Cookie: foo=deleted; expires=".gmdate("D, d-M-Y H:i:s T", time() - 31536001)."; httponly", explode("\r\n", $bag->__toString()));
    }

    public function testCookiesWithSameNames()
    {
        $bag = new ResponseHeaderBag();
        $bag->setCookie(new Cookie('foo', 'bar', 0, '/path/foo', 'foo.bar'));
        $bag->setCookie(new Cookie('foo', 'bar', 0, '/path/bar', 'foo.bar'));
        $bag->setCookie(new Cookie('foo', 'bar', 0, '/path/bar', 'bar.foo'));
        $bag->setCookie(new Cookie('foo', 'bar'));

        $this->assertEquals(4, count($bag->getCookies()));

        $headers = explode("\r\n", $bag->__toString());
        $this->assertContains("Set-Cookie: foo=bar; path=/path/foo; domain=foo.bar; httponly", $headers);
        $this->assertContains("Set-Cookie: foo=bar; path=/path/foo; domain=foo.bar; httponly", $headers);
        $this->assertContains("Set-Cookie: foo=bar; path=/path/bar; domain=bar.foo; httponly", $headers);
        $this->assertContains("Set-Cookie: foo=bar; httponly", $headers);

        $cookies = $bag->getCookies(ResponseHeaderBag::COOKIES_ARRAY);
        $this->assertTrue(isset($cookies['foo.bar']['/path/foo']['foo']));
        $this->assertTrue(isset($cookies['foo.bar']['/path/bar']['foo']));
        $this->assertTrue(isset($cookies['bar.foo']['/path/bar']['foo']));
        $this->assertTrue(isset($cookies['']['/']['foo']));
    }

    public function testRemoveCookie()
    {
        $bag = new ResponseHeaderBag();
        $bag->setCookie(new Cookie('foo', 'bar', 0, '/path/foo', 'foo.bar'));
        $bag->setCookie(new Cookie('bar', 'foo', 0, '/path/bar', 'foo.bar'));

        $cookies = $bag->getCookies(ResponseHeaderBag::COOKIES_ARRAY);
        $this->assertTrue(isset($cookies['foo.bar']['/path/foo']));

        $bag->removeCookie('foo', '/path/foo', 'foo.bar');

        $cookies = $bag->getCookies(ResponseHeaderBag::COOKIES_ARRAY);
        $this->assertFalse(isset($cookies['foo.bar']['/path/foo']));

        $bag->removeCookie('bar', '/path/bar', 'foo.bar');

        $cookies = $bag->getCookies(ResponseHeaderBag::COOKIES_ARRAY);
        $this->assertFalse(isset($cookies['foo.bar']));
    }
}
