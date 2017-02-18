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
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * @group time-sensitive
 */
class ResponseHeaderBagTest extends TestCase
{
    /**
     * @dataProvider provideAllPreserveCase
     */
    public function testAllPreserveCase($headers, $expected)
    {
        $bag = new ResponseHeaderBag($headers);

        $this->assertEquals($expected, $bag->allPreserveCase(), '->allPreserveCase() gets all input keys in original case');
    }

    public function provideAllPreserveCase()
    {
        return array(
            array(
                array('fOo' => 'BAR'),
                array('fOo' => array('BAR'), 'Cache-Control' => array('no-cache')),
            ),
            array(
                array('ETag' => 'xyzzy'),
                array('ETag' => array('xyzzy'), 'Cache-Control' => array('private, must-revalidate')),
            ),
            array(
                array('Content-MD5' => 'Q2hlY2sgSW50ZWdyaXR5IQ=='),
                array('Content-MD5' => array('Q2hlY2sgSW50ZWdyaXR5IQ=='), 'Cache-Control' => array('no-cache')),
            ),
            array(
                array('P3P' => 'CP="CAO PSA OUR"'),
                array('P3P' => array('CP="CAO PSA OUR"'), 'Cache-Control' => array('no-cache')),
            ),
            array(
                array('WWW-Authenticate' => 'Basic realm="WallyWorld"'),
                array('WWW-Authenticate' => array('Basic realm="WallyWorld"'), 'Cache-Control' => array('no-cache')),
            ),
            array(
                array('X-UA-Compatible' => 'IE=edge,chrome=1'),
                array('X-UA-Compatible' => array('IE=edge,chrome=1'), 'Cache-Control' => array('no-cache')),
            ),
            array(
                array('X-XSS-Protection' => '1; mode=block'),
                array('X-XSS-Protection' => array('1; mode=block'), 'Cache-Control' => array('no-cache')),
            ),
        );
    }

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
            'Cache-Control' => 'max-age=3600',
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

        $this->assertSetCookieHeader('foo=bar; path=/; httponly', $bag);

        $bag->clearCookie('foo');

        $this->assertSetCookieHeader('foo=deleted; expires='.gmdate('D, d-M-Y H:i:s T', time() - 31536001).'; path=/; httponly', $bag);
    }

    public function testClearCookieSecureNotHttpOnly()
    {
        $bag = new ResponseHeaderBag(array());

        $bag->clearCookie('foo', '/', null, true, false);

        $this->assertSetCookieHeader('foo=deleted; expires='.gmdate('D, d-M-Y H:i:s T', time() - 31536001).'; path=/; secure', $bag);
    }

    public function testReplace()
    {
        $bag = new ResponseHeaderBag(array());
        $this->assertEquals('no-cache', $bag->get('Cache-Control'));
        $this->assertTrue($bag->hasCacheControlDirective('no-cache'));

        $bag->replace(array('Cache-Control' => 'public'));
        $this->assertEquals('public', $bag->get('Cache-Control'));
        $this->assertTrue($bag->hasCacheControlDirective('public'));
    }

    public function testReplaceWithRemove()
    {
        $bag = new ResponseHeaderBag(array());
        $this->assertEquals('no-cache', $bag->get('Cache-Control'));
        $this->assertTrue($bag->hasCacheControlDirective('no-cache'));

        $bag->remove('Cache-Control');
        $bag->replace(array());
        $this->assertEquals('no-cache', $bag->get('Cache-Control'));
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

        $this->assertSetCookieHeader('foo=bar; path=/path/foo; domain=foo.bar; httponly', $bag);
        $this->assertSetCookieHeader('foo=bar; path=/path/bar; domain=foo.bar; httponly', $bag);
        $this->assertSetCookieHeader('foo=bar; path=/path/bar; domain=bar.foo; httponly', $bag);
        $this->assertSetCookieHeader('foo=bar; path=/; httponly', $bag);

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

    public function testRemoveCookieWithNullRemove()
    {
        $bag = new ResponseHeaderBag();
        $bag->setCookie(new Cookie('foo', 'bar', 0));
        $bag->setCookie(new Cookie('bar', 'foo', 0));

        $cookies = $bag->getCookies(ResponseHeaderBag::COOKIES_ARRAY);
        $this->assertTrue(isset($cookies['']['/']));

        $bag->removeCookie('foo', null);
        $cookies = $bag->getCookies(ResponseHeaderBag::COOKIES_ARRAY);
        $this->assertFalse(isset($cookies['']['/']['foo']));

        $bag->removeCookie('bar', null);
        $cookies = $bag->getCookies(ResponseHeaderBag::COOKIES_ARRAY);
        $this->assertFalse(isset($cookies['']['/']['bar']));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetCookiesWithInvalidArgument()
    {
        $bag = new ResponseHeaderBag();

        $bag->getCookies('invalid_argument');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testMakeDispositionInvalidDisposition()
    {
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
        $this->assertEquals(array('http://www.symfony.com'), $allHeaders['Location']);
        $this->assertEquals(array('text/html'), $allHeaders['Content-type']);
    }

    public function provideMakeDisposition()
    {
        return array(
            array('attachment', 'foo.html', 'foo.html', 'attachment; filename="foo.html"'),
            array('attachment', 'foo.html', '', 'attachment; filename="foo.html"'),
            array('attachment', 'foo bar.html', '', 'attachment; filename="foo bar.html"'),
            array('attachment', 'foo "bar".html', '', 'attachment; filename="foo \\"bar\\".html"'),
            array('attachment', 'foo%20bar.html', 'foo bar.html', 'attachment; filename="foo bar.html"; filename*=utf-8\'\'foo%2520bar.html'),
            array('attachment', 'föö.html', 'foo.html', 'attachment; filename="foo.html"; filename*=utf-8\'\'f%C3%B6%C3%B6.html'),
        );
    }

    /**
     * @dataProvider provideMakeDispositionFail
     * @expectedException \InvalidArgumentException
     */
    public function testMakeDispositionFail($disposition, $filename)
    {
        $headers = new ResponseHeaderBag();

        $headers->makeDisposition($disposition, $filename);
    }

    public function provideMakeDispositionFail()
    {
        return array(
            array('attachment', 'foo%20bar.html'),
            array('attachment', 'foo/bar.html'),
            array('attachment', '/foo.html'),
            array('attachment', 'foo\bar.html'),
            array('attachment', '\foo.html'),
            array('attachment', 'föö.html'),
        );
    }

    private function assertSetCookieHeader($expected, ResponseHeaderBag $actual)
    {
        $this->assertRegExp('#^Set-Cookie:\s+'.preg_quote($expected, '#').'$#m', str_replace("\r\n", "\n", (string) $actual));
    }
}
