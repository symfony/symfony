<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * @group time-sensitive
 */
class ResponseHeaderBagTest extends TestCase
{
    public function testAllPreserveCase()
    {
        $headers = [
            'fOo' => 'BAR',
            'ETag' => 'xyzzy',
            'Content-MD5' => 'Q2hlY2sgSW50ZWdyaXR5IQ==',
            'P3P' => 'CP="CAO PSA OUR"',
            'WWW-Authenticate' => 'Basic realm="WallyWorld"',
            'X-UA-Compatible' => 'IE=edge,chrome=1',
            'X-XSS-Protection' => '1; mode=block',
        ];

        $bag = new ResponseHeaderBag($headers);
        $allPreservedCase = $bag->allPreserveCase();

        foreach (array_keys($headers) as $headerName) {
            $this->assertArrayHasKey($headerName, $allPreservedCase, '->allPreserveCase() gets all input keys in original case');
        }
    }

    public function testCacheControlHeader()
    {
        $bag = new ResponseHeaderBag([]);
        $this->assertEquals('no-cache, private', $bag->get('Cache-Control'));
        $this->assertTrue($bag->hasCacheControlDirective('no-cache'));

        $bag = new ResponseHeaderBag(['Cache-Control' => 'public']);
        $this->assertEquals('public', $bag->get('Cache-Control'));
        $this->assertTrue($bag->hasCacheControlDirective('public'));

        $bag = new ResponseHeaderBag(['ETag' => 'abcde']);
        $this->assertEquals('no-cache, private', $bag->get('Cache-Control'));
        $this->assertTrue($bag->hasCacheControlDirective('private'));
        $this->assertTrue($bag->hasCacheControlDirective('no-cache'));
        $this->assertFalse($bag->hasCacheControlDirective('max-age'));

        $bag = new ResponseHeaderBag(['Expires' => 'Wed, 16 Feb 2011 14:17:43 GMT']);
        $this->assertEquals('private, must-revalidate', $bag->get('Cache-Control'));

        $bag = new ResponseHeaderBag([
            'Expires' => 'Wed, 16 Feb 2011 14:17:43 GMT',
            'Cache-Control' => 'max-age=3600',
        ]);
        $this->assertEquals('max-age=3600, private', $bag->get('Cache-Control'));

        $bag = new ResponseHeaderBag(['Last-Modified' => 'abcde']);
        $this->assertEquals('private, must-revalidate', $bag->get('Cache-Control'));

        $bag = new ResponseHeaderBag(['Etag' => 'abcde', 'Last-Modified' => 'abcde']);
        $this->assertEquals('private, must-revalidate', $bag->get('Cache-Control'));

        $bag = new ResponseHeaderBag(['cache-control' => 'max-age=100']);
        $this->assertEquals('max-age=100, private', $bag->get('Cache-Control'));

        $bag = new ResponseHeaderBag(['cache-control' => 's-maxage=100']);
        $this->assertEquals('s-maxage=100', $bag->get('Cache-Control'));

        $bag = new ResponseHeaderBag(['cache-control' => 'private, max-age=100']);
        $this->assertEquals('max-age=100, private', $bag->get('Cache-Control'));

        $bag = new ResponseHeaderBag(['cache-control' => 'public, max-age=100']);
        $this->assertEquals('max-age=100, public', $bag->get('Cache-Control'));

        $bag = new ResponseHeaderBag();
        $bag->set('Last-Modified', 'abcde');
        $this->assertEquals('private, must-revalidate', $bag->get('Cache-Control'));

        $bag = new ResponseHeaderBag();
        $bag->set('Cache-Control', ['public', 'must-revalidate']);
        $this->assertCount(1, $bag->get('Cache-Control', null, false));
        $this->assertEquals('must-revalidate, public', $bag->get('Cache-Control'));

        $bag = new ResponseHeaderBag();
        $bag->set('Cache-Control', 'public');
        $bag->set('Cache-Control', 'must-revalidate', false);
        $this->assertCount(1, $bag->get('Cache-Control', null, false));
        $this->assertEquals('must-revalidate, public', $bag->get('Cache-Control'));
    }

    public function testCacheControlClone()
    {
        $headers = ['foo' => 'bar'];
        $bag1 = new ResponseHeaderBag($headers);
        $bag2 = new ResponseHeaderBag($bag1->allPreserveCase());
        $this->assertEquals($bag1->allPreserveCase(), $bag2->allPreserveCase());
    }

    public function testToStringIncludesCookieHeaders()
    {
        $bag = new ResponseHeaderBag([]);
        $bag->setCookie(new Cookie('foo', 'bar'));

        $this->assertSetCookieHeader('foo=bar; path=/; httponly', $bag);

        $bag->clearCookie('foo');

        $this->assertSetCookieHeader('foo=deleted; expires='.gmdate('D, d-M-Y H:i:s T', time() - 31536001).'; Max-Age=0; path=/; httponly', $bag);
    }

    public function testClearCookieSecureNotHttpOnly()
    {
        $bag = new ResponseHeaderBag([]);

        $bag->clearCookie('foo', '/', null, true, false);

        $this->assertSetCookieHeader('foo=deleted; expires='.gmdate('D, d-M-Y H:i:s T', time() - 31536001).'; Max-Age=0; path=/; secure', $bag);
    }

    public function testClearCookieSamesite()
    {
        $bag = new ResponseHeaderBag([]);

        $bag->clearCookie('foo', '/', null, true, false, 'none');
        $this->assertSetCookieHeader('foo=deleted; expires='.gmdate('D, d-M-Y H:i:s T', time() - 31536001).'; Max-Age=0; path=/; secure; samesite=none', $bag);
    }

    public function testReplace()
    {
        $bag = new ResponseHeaderBag([]);
        $this->assertEquals('no-cache, private', $bag->get('Cache-Control'));
        $this->assertTrue($bag->hasCacheControlDirective('no-cache'));

        $bag->replace(['Cache-Control' => 'public']);
        $this->assertEquals('public', $bag->get('Cache-Control'));
        $this->assertTrue($bag->hasCacheControlDirective('public'));
    }

    public function testReplaceWithRemove()
    {
        $bag = new ResponseHeaderBag([]);
        $this->assertEquals('no-cache, private', $bag->get('Cache-Control'));
        $this->assertTrue($bag->hasCacheControlDirective('no-cache'));

        $bag->remove('Cache-Control');
        $bag->replace([]);
        $this->assertEquals('no-cache, private', $bag->get('Cache-Control'));
        $this->assertTrue($bag->hasCacheControlDirective('no-cache'));
    }

    public function testCookiesWithSameNames()
    {
        $bag = new ResponseHeaderBag();
        $bag->setCookie(new Cookie('foo', 'bar', 0, '/path/foo', 'foo.bar'));
        $bag->setCookie(new Cookie('foo', 'bar', 0, '/path/bar', 'foo.bar'));
        $bag->setCookie(new Cookie('foo', 'bar', 0, '/path/bar', 'bar.foo'));
        $bag->setCookie(new Cookie('foo', 'bar'));

        $this->assertCount(4, $bag->getCookies());
        $this->assertEquals('foo=bar; path=/path/foo; domain=foo.bar; httponly', $bag->get('set-cookie'));
        $this->assertEquals([
            'foo=bar; path=/path/foo; domain=foo.bar; httponly',
            'foo=bar; path=/path/bar; domain=foo.bar; httponly',
            'foo=bar; path=/path/bar; domain=bar.foo; httponly',
            'foo=bar; path=/; httponly',
        ], $bag->get('set-cookie', null, false));

        $this->assertSetCookieHeader('foo=bar; path=/path/foo; domain=foo.bar; httponly', $bag);
        $this->assertSetCookieHeader('foo=bar; path=/path/bar; domain=foo.bar; httponly', $bag);
        $this->assertSetCookieHeader('foo=bar; path=/path/bar; domain=bar.foo; httponly', $bag);
        $this->assertSetCookieHeader('foo=bar; path=/; httponly', $bag);

        $cookies = $bag->getCookies(ResponseHeaderBag::COOKIES_ARRAY);

        $this->assertArrayHasKey('foo', $cookies['foo.bar']['/path/foo']);
        $this->assertArrayHasKey('foo', $cookies['foo.bar']['/path/bar']);
        $this->assertArrayHasKey('foo', $cookies['bar.foo']['/path/bar']);
        $this->assertArrayHasKey('foo', $cookies['']['/']);
    }

    public function testRemoveCookie()
    {
        $bag = new ResponseHeaderBag();
        $this->assertFalse($bag->has('set-cookie'));

        $bag->setCookie(new Cookie('foo', 'bar', 0, '/path/foo', 'foo.bar'));
        $bag->setCookie(new Cookie('bar', 'foo', 0, '/path/bar', 'foo.bar'));
        $this->assertTrue($bag->has('set-cookie'));

        $cookies = $bag->getCookies(ResponseHeaderBag::COOKIES_ARRAY);
        $this->assertArrayHasKey('/path/foo', $cookies['foo.bar']);

        $bag->removeCookie('foo', '/path/foo', 'foo.bar');
        $this->assertTrue($bag->has('set-cookie'));

        $cookies = $bag->getCookies(ResponseHeaderBag::COOKIES_ARRAY);
        $this->assertArrayNotHasKey('/path/foo', $cookies['foo.bar']);

        $bag->removeCookie('bar', '/path/bar', 'foo.bar');
        $this->assertFalse($bag->has('set-cookie'));

        $cookies = $bag->getCookies(ResponseHeaderBag::COOKIES_ARRAY);
        $this->assertArrayNotHasKey('foo.bar', $cookies);
    }

    public function testRemoveCookieWithNullRemove()
    {
        $bag = new ResponseHeaderBag();
        $bag->setCookie(new Cookie('foo', 'bar', 0));
        $bag->setCookie(new Cookie('bar', 'foo', 0));

        $cookies = $bag->getCookies(ResponseHeaderBag::COOKIES_ARRAY);
        $this->assertArrayHasKey('/', $cookies['']);

        $bag->removeCookie('foo', null);
        $cookies = $bag->getCookies(ResponseHeaderBag::COOKIES_ARRAY);
        $this->assertArrayNotHasKey('foo', $cookies['']['/']);

        $bag->removeCookie('bar', null);
        $cookies = $bag->getCookies(ResponseHeaderBag::COOKIES_ARRAY);
        $this->assertFalse(isset($cookies['']['/']['bar']));
    }

    public function testSetCookieHeader()
    {
        $bag = new ResponseHeaderBag();
        $bag->set('set-cookie', 'foo=bar');
        $this->assertEquals([new Cookie('foo', 'bar', 0, '/', null, false, false, true)], $bag->getCookies());

        $bag->set('set-cookie', 'foo2=bar2', false);
        $this->assertEquals([
            new Cookie('foo', 'bar', 0, '/', null, false, false, true),
            new Cookie('foo2', 'bar2', 0, '/', null, false, false, true),
        ], $bag->getCookies());

        $bag->remove('set-cookie');
        $this->assertEquals([], $bag->getCookies());
    }

    public function testGetCookiesWithInvalidArgument()
    {
        $this->expectException('InvalidArgumentException');
        $bag = new ResponseHeaderBag();

        $bag->getCookies('invalid_argument');
    }

    public function testMakeDispositionInvalidDisposition()
    {
        $this->expectException('InvalidArgumentException');
        $headers = new ResponseHeaderBag();

        $headers->makeDisposition('invalid', 'foo.html');
    }

    /**
     * @dataProvider provideMakeDisposition
     */
    public function testMakeDisposition($disposition, $filename, $filenameFallback, $expected)
    {
        $headers = new ResponseHeaderBag();

        $this->assertEquals($expected, $headers->makeDisposition($disposition, $filename, $filenameFallback));
    }

    public function testToStringDoesntMessUpHeaders()
    {
        $headers = new ResponseHeaderBag();

        $headers->set('Location', 'http://www.symfony.com');
        $headers->set('Content-type', 'text/html');

        (string) $headers;

        $allHeaders = $headers->allPreserveCase();
        $this->assertEquals(['http://www.symfony.com'], $allHeaders['Location']);
        $this->assertEquals(['text/html'], $allHeaders['Content-type']);
    }

    public function provideMakeDisposition()
    {
        return [
            ['attachment', 'foo.html', 'foo.html', 'attachment; filename="foo.html"'],
            ['attachment', 'foo.html', '', 'attachment; filename="foo.html"'],
            ['attachment', 'foo bar.html', '', 'attachment; filename="foo bar.html"'],
            ['attachment', 'foo "bar".html', '', 'attachment; filename="foo \\"bar\\".html"'],
            ['attachment', 'foo%20bar.html', 'foo bar.html', 'attachment; filename="foo bar.html"; filename*=utf-8\'\'foo%2520bar.html'],
            ['attachment', 'föö.html', 'foo.html', 'attachment; filename="foo.html"; filename*=utf-8\'\'f%C3%B6%C3%B6.html'],
        ];
    }

    /**
     * @dataProvider provideMakeDispositionFail
     */
    public function testMakeDispositionFail($disposition, $filename)
    {
        $this->expectException('InvalidArgumentException');
        $headers = new ResponseHeaderBag();

        $headers->makeDisposition($disposition, $filename);
    }

    public function provideMakeDispositionFail()
    {
        return [
            ['attachment', 'foo%20bar.html'],
            ['attachment', 'foo/bar.html'],
            ['attachment', '/foo.html'],
            ['attachment', 'foo\bar.html'],
            ['attachment', '\foo.html'],
            ['attachment', 'föö.html'],
        ];
    }

    public function testDateHeaderAddedOnCreation()
    {
        $now = time();

        $bag = new ResponseHeaderBag();
        $this->assertTrue($bag->has('Date'));

        $this->assertEquals($now, $bag->getDate('Date')->getTimestamp());
    }

    public function testDateHeaderCanBeSetOnCreation()
    {
        $someDate = 'Thu, 23 Mar 2017 09:15:12 GMT';
        $bag = new ResponseHeaderBag(['Date' => $someDate]);

        $this->assertEquals($someDate, $bag->get('Date'));
    }

    public function testDateHeaderWillBeRecreatedWhenRemoved()
    {
        $someDate = 'Thu, 23 Mar 2017 09:15:12 GMT';
        $bag = new ResponseHeaderBag(['Date' => $someDate]);
        $bag->remove('Date');

        // a (new) Date header is still present
        $this->assertTrue($bag->has('Date'));
        $this->assertNotEquals($someDate, $bag->get('Date'));
    }

    public function testDateHeaderWillBeRecreatedWhenHeadersAreReplaced()
    {
        $bag = new ResponseHeaderBag();
        $bag->replace([]);

        $this->assertTrue($bag->has('Date'));
    }

    private function assertSetCookieHeader($expected, ResponseHeaderBag $actual)
    {
        $this->assertRegExp('#^Set-Cookie:\s+'.preg_quote($expected, '#').'$#m', str_replace("\r\n", "\n", (string) $actual));
    }
}
