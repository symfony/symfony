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

use PHPUnit\Framework\Attributes\DataProvider;
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

        $this->assertEquals($cookie, $cookieJar->get('foo'), '->set() sets a cookie');

        $this->assertNull($cookieJar->get('foobar'), '->get() returns null if the cookie does not exist');

        $cookieJar->set($cookie = new Cookie('foo', 'bar', time() - 86400));
        $this->assertNull($cookieJar->get('foo'), '->get() returns null if the cookie is expired');
    }

    public function testHostOnlyAndDomainCookiesAreDistinct()
    {
        $cookieJar = new CookieJar();
        $cookieJar->updateFromSetCookie(['sid=host'], 'https://example.com/');
        $cookieJar->updateFromSetCookie(['sid=domain; Domain=example.com'], 'https://example.com/');

        $this->assertCount(2, $cookieJar->all());
        $this->assertSame(['sid' => 'domain'], $cookieJar->allValues('https://www.example.com/'));

        $cookieJar->updateFromSetCookie(['sid=deleted; Max-Age=0'], 'https://example.com/');

        $this->assertCount(1, $cookieJar->all());
        $this->assertSame('domain', $cookieJar->get('sid')->getValue());

        $cookieJar->expire('sid', '/', 'example.com');

        $this->assertSame([], $cookieJar->all());
    }

    public function testHostOnlyCookieKeysDoNotCollideWithArtificialCookieNames()
    {
        $cookieJar = new CookieJar();
        $cookieJar->updateFromSetCookie(['sid=host'], 'https://example.com/');
        $cookieJar->set(new Cookie("\0sid", 'artificial', null, '/', 'example.com'));

        $this->assertCount(2, $cookieJar->all());
        $this->assertSame('host', $cookieJar->get('sid')->getValue());
        $this->assertSame('artificial', $cookieJar->get("\0sid")->getValue());
    }

    public function testAllForUriKeepsDuplicateNamesInPathAndCreationOrder()
    {
        $cookieJar = new CookieJar();
        $cookieJar->updateFromSetCookie(['sid=root; Domain=example.com; Path=/'], 'https://www.example.com/');
        $cookieJar->updateFromSetCookie(['sid=deep; Path=/login'], 'https://www.example.com/');
        $cookieJar->updateFromSetCookie(['later=value; Path=/'], 'https://www.example.com/');
        $cookieJar->updateFromSetCookie(['sid=replaced; Domain=example.com; Path=/'], 'https://www.example.com/');

        $this->assertSame(['deep', 'replaced', 'value'], array_map(static fn (Cookie $cookie) => $cookie->getValue(), $cookieJar->allForUri('https://www.example.com/login')));
        $this->assertSame(['sid' => 'deep', 'later' => 'value'], $cookieJar->allValues('https://www.example.com/login'));
    }

    public function testAllForUriFiltersCookies()
    {
        $cookieJar = new CookieJar();
        $cookieJar->updateFromSetCookie(['host=value', 'secure=value; Secure', 'other=value; Path=/other', 'expired=value; Max-Age=0'], 'https://example.com/');
        $cookieJar->updateFromSetCookie(['foreign=value'], 'https://other.example/');
        $cookieJar->set(new Cookie('artificial', 'value'));

        $this->assertSame(['host', 'artificial'], array_map(static fn (Cookie $cookie) => $cookie->getName(), $cookieJar->allForUri('http://example.com/')));
        $this->assertSame(['artificial'], array_map(static fn (Cookie $cookie) => $cookie->getName(), $cookieJar->allForUri('https://www.example.com/')));
    }

    public function testAllForUriPreservesCreationOrderAfterSerialization()
    {
        $cookieJar = new CookieJar();
        $cookieJar->updateFromSetCookie(['first=old'], 'https://www.example.com/');
        $cookieJar->updateFromSetCookie(['second=old; Domain=example.com'], 'https://www.example.com/');
        $cookieJar->updateFromSetCookie(['third=old'], 'https://www.example.com/');
        $cookieJar = unserialize(serialize($cookieJar));

        $this->assertSame(['first', 'second', 'third'], array_map(static fn (Cookie $cookie) => $cookie->getName(), $cookieJar->allForUri('https://www.example.com/')));
    }

    public function testAllForUriUpdatesCreationOrderAfterRemoval()
    {
        $cookieJar = new CookieJar();
        $cookieJar->updateFromSetCookie(['first=expired; Max-Age=0', 'second=value'], 'https://example.com/');

        $this->assertCount(1, $cookieJar->allForUri('https://example.com/'));

        $cookieJar->updateFromSetCookie(['first=new'], 'https://example.com/');

        $this->assertSame(['second', 'first'], array_map(static fn (Cookie $cookie) => $cookie->getName(), $cookieJar->allForUri('https://example.com/')));

        $cookieJar->expire('second');
        $cookieJar->updateFromSetCookie(['second=new'], 'https://example.com/');

        $this->assertSame(['first', 'second'], array_map(static fn (Cookie $cookie) => $cookie->getName(), $cookieJar->allForUri('https://example.com/')));

        $cookieJar->clear();
        $cookieJar->updateFromSetCookie(['second=new', 'first=new'], 'https://example.com/');

        $this->assertSame(['second', 'first'], array_map(static fn (Cookie $cookie) => $cookie->getName(), $cookieJar->allForUri('https://example.com/')));
    }

    public function testExpire()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set($cookie = new Cookie('foo', 'bar'));
        $cookieJar->expire('foo');
        $this->assertNull($cookieJar->get('foo'), '->get() returns null if the cookie is expired');
    }

    public function testAll()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set($cookie1 = new Cookie('foo', 'bar'));
        $cookieJar->set($cookie2 = new Cookie('bar', 'foo'));

        $this->assertEquals([$cookie1, $cookie2], $cookieJar->all(), '->all() returns all cookies in the jar');
    }

    public function testClear()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set($cookie1 = new Cookie('foo', 'bar'));
        $cookieJar->set($cookie2 = new Cookie('bar', 'foo'));

        $cookieJar->clear();

        $this->assertEquals([], $cookieJar->all(), '->clear() expires all cookies');
    }

    public function testUpdateFromResponse()
    {
        $response = new Response('', 200, ['Set-Cookie' => 'foo=foo']);

        $cookieJar = new CookieJar();
        $cookieJar->updateFromResponse($response);

        $this->assertEquals('foo', $cookieJar->get('foo')->getValue(), '->updateFromResponse() updates cookies from a Response objects');
    }

    public function testUpdateFromSetCookie()
    {
        $setCookies = ['foo=foo'];

        $cookieJar = new CookieJar();
        $cookieJar->set(new Cookie('bar', 'bar'));
        $cookieJar->updateFromSetCookie($setCookies);

        $this->assertInstanceOf(Cookie::class, $cookieJar->get('foo'));
        $this->assertInstanceOf(Cookie::class, $cookieJar->get('bar'));
        $this->assertEquals('foo', $cookieJar->get('foo')->getValue(), '->updateFromSetCookie() updates cookies from a Set-Cookie header');
        $this->assertEquals('bar', $cookieJar->get('bar')->getValue(), '->updateFromSetCookie() keeps existing cookies');
    }

    public function testUpdateFromSetCookieWithMaxAge()
    {
        $cookieJar = new CookieJar();
        $cookieJar->updateFromSetCookie(['foo=bar', 'baz=qux'], 'http://example.com/');
        $cookieJar->updateFromSetCookie(['foo=bar; Max-Age=0', 'baz=qux; Max-Age=60'], 'http://example.com/');

        $this->assertSame(['baz' => 'qux'], $cookieJar->allValues('http://example.com/'));
        $this->assertNull($cookieJar->get('foo'));
    }

    public function testUpdateFromSetCookieIgnoresAnEmptyDomainAttribute()
    {
        $cookieJar = new CookieJar();
        $cookieJar->updateFromSetCookie(['foo=bar; Domain='], 'http://example.com/');

        $this->assertSame(['foo' => 'bar'], $cookieJar->allValues('http://example.com/'));
        $this->assertSame([], $cookieJar->allValues('http://other.example/'));
        $this->assertSame('example.com', $cookieJar->get('foo')->getDomain());
    }

    public function testUpdateFromEmptySetCookie()
    {
        $cookieJar = new CookieJar();
        $cookieJar->updateFromSetCookie(['']);
        $this->assertEquals([], $cookieJar->all());
    }

    public function testUpdateFromSetCookieWithMultipleCookies()
    {
        $timestamp = time() + 3600;
        $date = gmdate('D, d M Y H:i:s \G\M\T', $timestamp);
        $setCookies = [\sprintf('foo=foo; expires=%s; domain=.symfony.com; path=/, bar=bar; domain=.blog.symfony.com, PHPSESSID=id; expires=%1$s', $date)];

        $cookieJar = new CookieJar();
        $cookieJar->updateFromSetCookie($setCookies);

        $fooCookie = $cookieJar->get('foo', '/', '.symfony.com');
        $barCookie = $cookieJar->get('bar', '/', '.blog.symfony.com');
        $phpCookie = $cookieJar->get('PHPSESSID');

        $this->assertInstanceOf(Cookie::class, $fooCookie);
        $this->assertInstanceOf(Cookie::class, $barCookie);
        $this->assertInstanceOf(Cookie::class, $phpCookie);
        $this->assertEquals('foo', $fooCookie->getValue());
        $this->assertEquals('bar', $barCookie->getValue());
        $this->assertEquals('id', $phpCookie->getValue());
        $this->assertEquals($timestamp, $fooCookie->getExpiresTime());
        $this->assertNull($barCookie->getExpiresTime());
        $this->assertEquals($timestamp, $phpCookie->getExpiresTime());
    }

    #[DataProvider('provideAllValuesValues')]
    public function testAllValues($uri, $values)
    {
        $cookieJar = new CookieJar();
        $cookieJar->set($cookie1 = new Cookie('foo_nothing', 'foo'));
        $cookieJar->set($cookie2 = new Cookie('foo_expired', 'foo', time() - 86400));
        $cookieJar->set($cookie3 = new Cookie('foo_path', 'foo', null, '/foo'));
        $cookieJar->set($cookie4 = new Cookie('foo_domain', 'foo', null, '/', '.example.com'));
        $cookieJar->set($cookie4 = new Cookie('foo_strict_domain', 'foo', null, '/', '.www4.example.com'));
        $cookieJar->set($cookie5 = new Cookie('foo_secure', 'foo', null, '/', '', true));

        $this->assertEquals($values, array_keys($cookieJar->allValues($uri)), '->allValues() returns the cookie for a given URI');
    }

    public static function provideAllValuesValues()
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

        $this->assertEquals(['foo' => 'bar=baz'], $cookieJar->allValues('/'));
        $this->assertEquals(['foo' => 'bar%3Dbaz'], $cookieJar->allRawValues('/'));
    }

    public function testCookieExpireWithSameNameButDifferentPaths()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set($cookie1 = new Cookie('foo', 'bar1', null, '/foo'));
        $cookieJar->set($cookie2 = new Cookie('foo', 'bar2', null, '/bar'));
        $cookieJar->expire('foo', '/foo');

        $this->assertNull($cookieJar->get('foo'), '->get() returns null if the cookie is expired');
        $this->assertEquals([], array_keys($cookieJar->allValues('http://example.com/')));
        $this->assertEquals([], $cookieJar->allValues('http://example.com/foo'));
        $this->assertEquals(['foo' => 'bar2'], $cookieJar->allValues('http://example.com/bar'));
    }

    public function testCookieExpireWithNullPaths()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set($cookie1 = new Cookie('foo', 'bar1', null, '/'));
        $cookieJar->expire('foo', null);

        $this->assertNull($cookieJar->get('foo'), '->get() returns null if the cookie is expired');
        $this->assertEquals([], array_keys($cookieJar->allValues('http://example.com/')));
    }

    public function testCookieExpireWithDomain()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set($cookie1 = new Cookie('foo', 'bar1', null, '/foo', 'http://example2.com/'));
        $cookieJar->expire('foo', '/foo', 'http://example2.com/');

        $this->assertNull($cookieJar->get('foo'), '->get() returns null if the cookie is expired');
        $this->assertEquals([], array_keys($cookieJar->allValues('http://example2.com/')));
    }

    public function testCookieWithSameNameButDifferentPaths()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set($cookie1 = new Cookie('foo', 'bar1', null, '/foo'));
        $cookieJar->set($cookie2 = new Cookie('foo', 'bar2', null, '/bar'));

        $this->assertEquals([], array_keys($cookieJar->allValues('http://example.com/')));
        $this->assertEquals(['foo' => 'bar1'], $cookieJar->allValues('http://example.com/foo'));
        $this->assertEquals(['foo' => 'bar2'], $cookieJar->allValues('http://example.com/bar'));
    }

    public function testCookieWithSameNameButDifferentDomains()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set($cookie1 = new Cookie('foo', 'bar1', null, '/', 'foo.example.com'));
        $cookieJar->set($cookie2 = new Cookie('foo', 'bar2', null, '/', 'bar.example.com'));

        $this->assertEquals([], array_keys($cookieJar->allValues('http://example.com/')));
        $this->assertEquals(['foo' => 'bar1'], $cookieJar->allValues('http://foo.example.com/'));
        $this->assertEquals(['foo' => 'bar2'], $cookieJar->allValues('http://bar.example.com/'));
    }

    public function testCookieGetPrefersTheMostSpecificDomain()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set($parent = new Cookie('foo', 'bar1', null, '/', 'example.com'));
        $cookieJar->set($child = new Cookie('foo', 'bar2', null, '/', 'admin.example.com'));

        $this->assertEquals($child, $cookieJar->get('foo', '/', 'admin.example.com'));
        $this->assertEquals($parent, $cookieJar->get('foo', '/', 'www.example.com'));

        $cookieJar = new CookieJar();
        $cookieJar->set($child = new Cookie('foo', 'bar2', null, '/', 'admin.example.com'));
        $cookieJar->set($parent = new Cookie('foo', 'bar1', null, '/', 'example.com'));

        $this->assertEquals($child, $cookieJar->get('foo', '/', 'admin.example.com'));
        $this->assertEquals($parent, $cookieJar->get('foo', '/', 'www.example.com'));
    }

    public function testCookieGetPrefersTheLongestPath()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set($root = new Cookie('foo', 'bar1', null, '/'));
        $cookieJar->set($admin = new Cookie('foo', 'bar2', null, '/admin'));

        $this->assertEquals($admin, $cookieJar->get('foo', '/admin'));
        $this->assertEquals($root, $cookieJar->get('foo', '/'));
    }

    public function testAllValuesPrefersTheMostSpecificCookie()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set(new Cookie('foo', 'bar2', null, '/', 'admin.example.com'));
        $cookieJar->set(new Cookie('foo', 'bar1', null, '/', 'example.com'));

        $this->assertEquals(['foo' => 'bar2'], $cookieJar->allValues('http://admin.example.com/'));
        $this->assertEquals(['foo' => 'bar1'], $cookieJar->allValues('http://www.example.com/'));

        $cookieJar = new CookieJar();
        $cookieJar->set(new Cookie('foo', 'bar2', null, '/admin'));
        $cookieJar->set(new Cookie('foo', 'bar1', null, '/'));

        $this->assertEquals(['foo' => 'bar2'], $cookieJar->allValues('http://example.com/admin'));
        $this->assertEquals(['foo' => 'bar1'], $cookieJar->allValues('http://example.com/'));
    }

    public function testCookieGetWithSubdomain()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set($cookie1 = new Cookie('foo', 'bar', null, '/', '.example.com'));
        $cookieJar->set($cookie2 = new Cookie('foo1', 'bar', null, '/', 'test.example.com'));

        $this->assertEquals($cookie1, $cookieJar->get('foo', '/', 'foo.example.com'));
        $this->assertEquals($cookie1, $cookieJar->get('foo', '/', 'example.com'));
        $this->assertEquals($cookie2, $cookieJar->get('foo1', '/', 'test.example.com'));
    }

    public function testCookieGetWithWrongSubdomain()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set($cookie1 = new Cookie('foo1', 'bar', null, '/', 'test.example.com'));

        $this->assertNull($cookieJar->get('foo1', '/', 'foo.example.com'));
    }

    public function testCookieGetWithSubdirectory()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set($cookie1 = new Cookie('foo', 'bar', null, '/test', '.example.com'));
        $cookieJar->set($cookie2 = new Cookie('foo1', 'bar1', null, '/', '.example.com'));

        $this->assertNull($cookieJar->get('foo', '/', '.example.com'));
        $this->assertNull($cookieJar->get('foo', '/bar', '.example.com'));
        $this->assertEquals($cookie1, $cookieJar->get('foo', '/test', 'example.com'));
        $this->assertEquals($cookie2, $cookieJar->get('foo1', '/', 'example.com'));
        $this->assertEquals($cookie2, $cookieJar->get('foo1', '/bar', 'example.com'));

        $this->assertEquals($cookie2, $cookieJar->get('foo1', '/bar'));
    }

    public function testCookieWithWildcardDomain()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set(new Cookie('foo', 'bar', null, '/', '.example.com'));

        $this->assertEquals(['foo' => 'bar'], $cookieJar->allValues('http://www.example.com'));
        $this->assertSame([], $cookieJar->allValues('http://wwwexample.com'));
    }

    public function testCookiePathMatchesAtSegmentBoundaries()
    {
        $cookieJar = new CookieJar();
        $cookieJar->updateFromSetCookie(['foo=bar; Path=/foo', 'baz=qux; Path=/baz/'], 'http://example.com/');

        $this->assertSame(['foo' => 'bar'], $cookieJar->allValues('http://example.com/foo'));
        $this->assertSame(['foo' => 'bar'], $cookieJar->allValues('http://example.com/foo/bar'));
        $this->assertSame([], $cookieJar->allValues('http://example.com/foobar'));
        $this->assertSame(['baz' => 'qux'], $cookieJar->allValues('http://example.com/baz/qux'));
        $this->assertSame([], $cookieJar->allValues('http://example.com/baz'));

        $this->assertSame('bar', $cookieJar->get('foo', '/foo/bar')->getValue());
        $this->assertNull($cookieJar->get('foo', '/foobar'));
    }

    public function testUpdateFromSetCookieIgnoresCookiesForAnotherDomain()
    {
        $cookieJar = new CookieJar();
        $cookieJar->updateFromSetCookie([
            'foreign=value; Domain=bank.example',
            'sibling=value; Domain=other.example.com',
            'suffix=value; Domain=ample.com',
            'parent=value; Domain=.Example.com',
            'self=value; Domain=www.example.com',
        ], 'http://www.example.com/');
        $cookieJar->updateFromSetCookie(['ip=value; Domain=127.0.0.1', 'ipsuffix=value; Domain=0.0.1'], 'http://127.0.0.1/');

        $this->assertSame(['parent', 'self', 'ip'], array_map(static fn (Cookie $cookie) => $cookie->getName(), $cookieJar->all()));
        $this->assertSame([], $cookieJar->allValues('http://bank.example/'));
    }

    public function testHostOnlyCookiesAreNotSentToSubdomains()
    {
        $cookieJar = new CookieJar();
        $cookieJar->updateFromSetCookie(['host=value', 'domain=value; Domain=example.com'], 'http://example.com/');

        $this->assertSame(['host' => 'value', 'domain' => 'value'], $cookieJar->allValues('http://example.com/'));
        $this->assertSame(['domain' => 'value'], $cookieJar->allValues('http://sub.example.com/'));
        $this->assertSame('value', $cookieJar->get('host', '/', 'example.com')->getValue());
        $this->assertNull($cookieJar->get('host', '/', 'sub.example.com'));
    }
}
