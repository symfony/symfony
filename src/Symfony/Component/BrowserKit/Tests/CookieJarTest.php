<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\BrowserKit\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\BrowserKit\Response;

class CookieJarTest extends TestCase
{
    public function testSetGet()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set($cookie = new Cookie('foo', 'bar'));

        self::assertEquals($cookie, $cookieJar->get('foo'), '->set() sets a cookie');

        self::assertNull($cookieJar->get('foobar'), '->get() returns null if the cookie does not exist');

        $cookieJar->set($cookie = new Cookie('foo', 'bar', time() - 86400));
        self::assertNull($cookieJar->get('foo'), '->get() returns null if the cookie is expired');
    }

    public function testExpire()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set($cookie = new Cookie('foo', 'bar'));
        $cookieJar->expire('foo');
        self::assertNull($cookieJar->get('foo'), '->get() returns null if the cookie is expired');
    }

    public function testAll()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set($cookie1 = new Cookie('foo', 'bar'));
        $cookieJar->set($cookie2 = new Cookie('bar', 'foo'));

        self::assertEquals([$cookie1, $cookie2], $cookieJar->all(), '->all() returns all cookies in the jar');
    }

    public function testClear()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set($cookie1 = new Cookie('foo', 'bar'));
        $cookieJar->set($cookie2 = new Cookie('bar', 'foo'));

        $cookieJar->clear();

        self::assertEquals([], $cookieJar->all(), '->clear() expires all cookies');
    }

    public function testUpdateFromResponse()
    {
        $response = new Response('', 200, ['Set-Cookie' => 'foo=foo']);

        $cookieJar = new CookieJar();
        $cookieJar->updateFromResponse($response);

        self::assertEquals('foo', $cookieJar->get('foo')->getValue(), '->updateFromResponse() updates cookies from a Response objects');
    }

    public function testUpdateFromSetCookie()
    {
        $setCookies = ['foo=foo'];

        $cookieJar = new CookieJar();
        $cookieJar->set(new Cookie('bar', 'bar'));
        $cookieJar->updateFromSetCookie($setCookies);

        self::assertInstanceOf(Cookie::class, $cookieJar->get('foo'));
        self::assertInstanceOf(Cookie::class, $cookieJar->get('bar'));
        self::assertEquals('foo', $cookieJar->get('foo')->getValue(), '->updateFromSetCookie() updates cookies from a Set-Cookie header');
        self::assertEquals('bar', $cookieJar->get('bar')->getValue(), '->updateFromSetCookie() keeps existing cookies');
    }

    public function testUpdateFromEmptySetCookie()
    {
        $cookieJar = new CookieJar();
        $cookieJar->updateFromSetCookie(['']);
        self::assertEquals([], $cookieJar->all());
    }

    public function testUpdateFromSetCookieWithMultipleCookies()
    {
        $timestamp = time() + 3600;
        $date = gmdate('D, d M Y H:i:s \G\M\T', $timestamp);
        $setCookies = [sprintf('foo=foo; expires=%s; domain=.symfony.com; path=/, bar=bar; domain=.blog.symfony.com, PHPSESSID=id; expires=%1$s', $date)];

        $cookieJar = new CookieJar();
        $cookieJar->updateFromSetCookie($setCookies);

        $fooCookie = $cookieJar->get('foo', '/', '.symfony.com');
        $barCookie = $cookieJar->get('bar', '/', '.blog.symfony.com');
        $phpCookie = $cookieJar->get('PHPSESSID');

        self::assertInstanceOf(Cookie::class, $fooCookie);
        self::assertInstanceOf(Cookie::class, $barCookie);
        self::assertInstanceOf(Cookie::class, $phpCookie);
        self::assertEquals('foo', $fooCookie->getValue());
        self::assertEquals('bar', $barCookie->getValue());
        self::assertEquals('id', $phpCookie->getValue());
        self::assertEquals($timestamp, $fooCookie->getExpiresTime());
        self::assertNull($barCookie->getExpiresTime());
        self::assertEquals($timestamp, $phpCookie->getExpiresTime());
    }

    /**
     * @dataProvider provideAllValuesValues
     */
    public function testAllValues($uri, $values)
    {
        $cookieJar = new CookieJar();
        $cookieJar->set($cookie1 = new Cookie('foo_nothing', 'foo'));
        $cookieJar->set($cookie2 = new Cookie('foo_expired', 'foo', time() - 86400));
        $cookieJar->set($cookie3 = new Cookie('foo_path', 'foo', null, '/foo'));
        $cookieJar->set($cookie4 = new Cookie('foo_domain', 'foo', null, '/', '.example.com'));
        $cookieJar->set($cookie4 = new Cookie('foo_strict_domain', 'foo', null, '/', '.www4.example.com'));
        $cookieJar->set($cookie5 = new Cookie('foo_secure', 'foo', null, '/', '', true));

        self::assertEquals($values, array_keys($cookieJar->allValues($uri)), '->allValues() returns the cookie for a given URI');
    }

    public function provideAllValuesValues()
    {
        return [
            ['http://www.example.com', ['foo_nothing', 'foo_domain']],
            ['http://www.example.com/', ['foo_nothing', 'foo_domain']],
            ['http://foo.example.com/', ['foo_nothing', 'foo_domain']],
            ['http://foo.example1.com/', ['foo_nothing']],
            ['https://foo.example.com/', ['foo_nothing', 'foo_secure', 'foo_domain']],
            ['http://www.example.com/foo/bar', ['foo_nothing', 'foo_path', 'foo_domain']],
            ['http://www4.example.com/', ['foo_nothing', 'foo_domain', 'foo_strict_domain']],
        ];
    }

    public function testEncodedValues()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set($cookie = new Cookie('foo', 'bar%3Dbaz', null, '/', '', false, true, true));

        self::assertEquals(['foo' => 'bar=baz'], $cookieJar->allValues('/'));
        self::assertEquals(['foo' => 'bar%3Dbaz'], $cookieJar->allRawValues('/'));
    }

    public function testCookieExpireWithSameNameButDifferentPaths()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set($cookie1 = new Cookie('foo', 'bar1', null, '/foo'));
        $cookieJar->set($cookie2 = new Cookie('foo', 'bar2', null, '/bar'));
        $cookieJar->expire('foo', '/foo');

        self::assertNull($cookieJar->get('foo'), '->get() returns null if the cookie is expired');
        self::assertEquals([], array_keys($cookieJar->allValues('http://example.com/')));
        self::assertEquals([], $cookieJar->allValues('http://example.com/foo'));
        self::assertEquals(['foo' => 'bar2'], $cookieJar->allValues('http://example.com/bar'));
    }

    public function testCookieExpireWithNullPaths()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set($cookie1 = new Cookie('foo', 'bar1', null, '/'));
        $cookieJar->expire('foo', null);

        self::assertNull($cookieJar->get('foo'), '->get() returns null if the cookie is expired');
        self::assertEquals([], array_keys($cookieJar->allValues('http://example.com/')));
    }

    public function testCookieExpireWithDomain()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set($cookie1 = new Cookie('foo', 'bar1', null, '/foo', 'http://example2.com/'));
        $cookieJar->expire('foo', '/foo', 'http://example2.com/');

        self::assertNull($cookieJar->get('foo'), '->get() returns null if the cookie is expired');
        self::assertEquals([], array_keys($cookieJar->allValues('http://example2.com/')));
    }

    public function testCookieWithSameNameButDifferentPaths()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set($cookie1 = new Cookie('foo', 'bar1', null, '/foo'));
        $cookieJar->set($cookie2 = new Cookie('foo', 'bar2', null, '/bar'));

        self::assertEquals([], array_keys($cookieJar->allValues('http://example.com/')));
        self::assertEquals(['foo' => 'bar1'], $cookieJar->allValues('http://example.com/foo'));
        self::assertEquals(['foo' => 'bar2'], $cookieJar->allValues('http://example.com/bar'));
    }

    public function testCookieWithSameNameButDifferentDomains()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set($cookie1 = new Cookie('foo', 'bar1', null, '/', 'foo.example.com'));
        $cookieJar->set($cookie2 = new Cookie('foo', 'bar2', null, '/', 'bar.example.com'));

        self::assertEquals([], array_keys($cookieJar->allValues('http://example.com/')));
        self::assertEquals(['foo' => 'bar1'], $cookieJar->allValues('http://foo.example.com/'));
        self::assertEquals(['foo' => 'bar2'], $cookieJar->allValues('http://bar.example.com/'));
    }

    public function testCookieGetWithSubdomain()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set($cookie1 = new Cookie('foo', 'bar', null, '/', '.example.com'));
        $cookieJar->set($cookie2 = new Cookie('foo1', 'bar', null, '/', 'test.example.com'));

        self::assertEquals($cookie1, $cookieJar->get('foo', '/', 'foo.example.com'));
        self::assertEquals($cookie1, $cookieJar->get('foo', '/', 'example.com'));
        self::assertEquals($cookie2, $cookieJar->get('foo1', '/', 'test.example.com'));
    }

    public function testCookieGetWithWrongSubdomain()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set($cookie1 = new Cookie('foo1', 'bar', null, '/', 'test.example.com'));

        self::assertNull($cookieJar->get('foo1', '/', 'foo.example.com'));
    }

    public function testCookieGetWithSubdirectory()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set($cookie1 = new Cookie('foo', 'bar', null, '/test', '.example.com'));
        $cookieJar->set($cookie2 = new Cookie('foo1', 'bar1', null, '/', '.example.com'));

        self::assertNull($cookieJar->get('foo', '/', '.example.com'));
        self::assertNull($cookieJar->get('foo', '/bar', '.example.com'));
        self::assertEquals($cookie1, $cookieJar->get('foo', '/test', 'example.com'));
        self::assertEquals($cookie2, $cookieJar->get('foo1', '/', 'example.com'));
        self::assertEquals($cookie2, $cookieJar->get('foo1', '/bar', 'example.com'));

        self::assertEquals($cookie2, $cookieJar->get('foo1', '/bar'));
    }

    public function testCookieWithWildcardDomain()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set(new Cookie('foo', 'bar', null, '/', '.example.com'));

        self::assertEquals(['foo' => 'bar'], $cookieJar->allValues('http://www.example.com'));
        self::assertEmpty($cookieJar->allValues('http://wwwexample.com'));
    }
}
