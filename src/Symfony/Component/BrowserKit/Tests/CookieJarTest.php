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
    public function testCreateBrowserCompatible()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->updateFromSetCookie(['foo=bar'], 'https://example.com/');

        $this->assertSame(['foo' => 'bar'], $cookieJar->allValues('https://example.com/'));
        $this->assertSame([], $cookieJar->allValues('https://sub.example.com/'));
    }

    public function testSetGet()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set($cookie = new Cookie('foo', 'bar'));

        $this->assertEquals($cookie, $cookieJar->get('foo'), '->set() sets a cookie');

        $this->assertNull($cookieJar->get('foobar'), '->get() returns null if the cookie does not exist');

        $cookieJar->set($cookie = new Cookie('foo', 'bar', time() - 86400));
        $this->assertNull($cookieJar->get('foo'), '->get() returns null if the cookie is expired');
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

    public function testLegacyModeKeepsExistingCookieSemantics()
    {
        $cookieJar = new CookieJar();
        $cookieJar->updateFromSetCookie(['host=origin; Path=/foo'], 'http://example.com/foo/login');
        $cookieJar->updateFromSetCookie(['secure=value; Secure'], 'http://example.com/');
        $cookieJar->updateFromSetCookie(['foreign=value; Domain=other.example'], 'http://example.com/');
        $cookieJar->updateFromSetCookie(['deleted=; Max-Age=0'], 'http://example.com/');

        $this->assertSame(['host' => 'origin', 'secure' => 'value', 'deleted' => ''], $cookieJar->allValues('http://sub.example.com/foobar'));
        $this->assertSame(['secure' => 'value', 'deleted' => ''], $cookieJar->allValues('http://example.com/'));
        $this->assertSame(['foreign' => 'value'], $cookieJar->allValues('http://other.example/'));
    }

    public function testLegacyModeCookieHeaderKeepsExistingCookieSemantics()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set(new Cookie('foo', 'bar=baz'));

        $this->assertSame('foo=bar%3Dbaz', $cookieJar->getCookieHeader('https://example.com/'));
    }

    public function testSubclassWithoutParentConstructorKeepsLegacyMode()
    {
        $cookieJar = new class extends CookieJar {
            public function __construct()
            {
            }
        };
        $cookieJar->set(new Cookie('foo', 'bar'));

        $this->assertSame(['foo' => 'bar'], $cookieJar->allValues('https://example.com/'));
    }

    public function testCreateBrowserCompatibleInitializesSubclassesWithoutAParentConstructorCall()
    {
        $cookieJar = new class extends CookieJar {
            public function __construct()
            {
            }
        };
        $class = $cookieJar::class;
        $cookieJar = $class::createBrowserCompatible();
        $cookieJar->updateFromSetCookie(['foo=bar'], 'https://example.com/');

        $this->assertSame(['foo' => 'bar'], $cookieJar->allValues('https://example.com/'));
        $this->assertSame([], $cookieJar->allValues('https://sub.example.com/'));
    }

    public function testBrowserCompatibleModeHandlesMaxAge()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->updateFromSetCookie(['foo=old'], 'https://example.com/');
        $cookieJar->updateFromSetCookie(['foo=new; expires=Fri, 20 May 2099 15:25:52 GMT; Max-Age=0'], 'https://example.com/');

        $this->assertNull($cookieJar->get('foo', '/', 'example.com'));

        $before = time();
        $cookieJar->updateFromSetCookie(['foo=new; expires=Fri, 20 May 2011 15:25:52 GMT; Max-Age=60'], 'https://example.com/');

        $cookie = $cookieJar->get('foo', '/', 'example.com');
        $this->assertSame(60, $cookie->getMaxAge());
        $this->assertGreaterThanOrEqual($before + 60, (int) $cookie->getExpiresTime());

        $cookieJar->updateFromSetCookie(['foo=newer; Max-Age=120'], 'https://example.com/');

        $cookie = $cookieJar->get('foo', '/', 'example.com');
        $this->assertSame('newer', $cookie->getValue());
        $this->assertSame(120, $cookie->getMaxAge());
    }

    public function testBrowserCompatibleModeExpiresACookieAtTheUnixEpoch()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->updateFromSetCookie(['foo=value'], 'https://example.com/');
        $cookieJar->updateFromSetCookie(['foo=value; Expires=Thu, 01 Jan 1970 00:00:00 GMT'], 'https://example.com/');

        $this->assertSame([], $cookieJar->all());
    }

    public function testBrowserCompatibleModeExpiresManuallySetCookiesAtTheUnixEpoch()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->set(new Cookie('foo', 'value', 0, domain: 'example.com'));

        $this->assertSame([], $cookieJar->all());
    }

    #[DataProvider('provideInvalidResponseUris')]
    public function testBrowserCompatibleModeRequiresAValidResponseUri(?string $uri)
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->updateFromSetCookie(['foo=bar'], $uri);

        $this->assertSame([], $cookieJar->all());
    }

    public static function provideInvalidResponseUris(): iterable
    {
        yield 'missing' => [null];
        yield 'relative' => ['/relative'];
        yield 'hostless' => ['mailto:user@example.com'];
        yield 'malformed' => ['http:///path'];
    }

    public function testBrowserCompatibleModeDistinguishesHostOnlyAndDomainCookies()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->updateFromSetCookie(['host=origin'], 'https://example.com/');
        $cookieJar->updateFromSetCookie(['domain=parent; Domain=.Example.com'], 'https://example.com/');

        $this->assertSame(['host' => 'origin', 'domain' => 'parent'], $cookieJar->allValues('https://example.com/'));
        $this->assertSame(['domain' => 'parent'], $cookieJar->allValues('https://sub.example.com/'));
        $this->assertTrue($cookieJar->get('host', '/', 'example.com')->isHostOnly());
        $this->assertFalse($cookieJar->get('domain', '/', 'example.com')->isHostOnly());
    }

    public function testBrowserCompatibleModeRejectsUnrelatedCookieDomains()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->updateFromSetCookie(['foo=bar; Domain=other.example'], 'https://example.com/');

        $this->assertSame([], $cookieJar->all());
    }

    public function testBrowserCompatibleModeMatchesCookiePathsAtSegmentBoundaries()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->updateFromSetCookie(['foo=bar; Path=/foo'], 'https://example.com/foo');

        $this->assertSame(['foo' => 'bar'], $cookieJar->allValues('https://example.com/foo/bar'));
        $this->assertSame([], $cookieJar->allValues('https://example.com/foobar'));
        $this->assertSame([], $cookieJar->allValues('https://example.com/bar'));
    }

    public function testBrowserCompatibleModeRejectsSecureCookiesReceivedOverHttp()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->updateFromSetCookie(['foo=bar; Secure'], 'http://example.com/');

        $this->assertSame([], $cookieJar->all());
    }

    public function testBrowserCompatibleModeTreatsHttpsSchemeCaseInsensitively()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->updateFromSetCookie(['foo=bar; Secure'], 'HTTPS://example.com/');

        $this->assertSame('foo=bar', $cookieJar->getCookieHeader('HtTpS://example.com/'));
    }

    public function testBrowserCompatibleModeDoesNotSendSecureCookiesOverHttp()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->updateFromSetCookie(['foo=bar; Secure'], 'https://example.com/');

        $this->assertSame([], $cookieJar->allValues('http://example.com/'));
    }

    #[DataProvider('provideRepeatedSecureAttributes')]
    public function testBrowserCompatibleModeRejectsRepeatedSecureCookiesReceivedOverHttp(string $header)
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->updateFromSetCookie([$header], 'http://example.com/');

        $this->assertSame([], $cookieJar->all());
    }

    public static function provideRepeatedSecureAttributes(): iterable
    {
        yield ['foo=bar; Secure; Secure='];
        yield ['foo=bar; Secure; Secure=0'];
    }

    public function testBrowserCompatibleModeProtectsSecureCookiesFromInsecureOverlays()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->updateFromSetCookie(['foo=secure; Secure'], 'https://example.com/');
        $cookieJar->updateFromSetCookie(['foo=insecure'], 'http://example.com/');

        $this->assertSame(['foo' => 'secure'], $cookieJar->allValues('https://example.com/'));
    }

    #[DataProvider('provideBrowserCompatibleSecureCookieOverlays')]
    public function testBrowserCompatibleModeProtectsSecureCookiesAcrossDomainsAndPaths(Cookie $stored, string $uri, string $header, array $expectedValues)
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->set($stored);
        $cookieJar->updateFromSetCookie([$header], $uri);

        $this->assertSame($expectedValues, array_map(static fn (Cookie $cookie): string => $cookie->getValue(), $cookieJar->all()));
    }

    public static function provideBrowserCompatibleSecureCookieOverlays(): iterable
    {
        yield 'stored parent domain and new child host' => [
            new Cookie('sid', 'secure', path: '/', domain: 'example.com', secure: true),
            'http://child.example.com/',
            'sid=insecure; Path=/',
            ['secure'],
        ];
        yield 'stored child host and new parent domain' => [
            new Cookie('sid', 'secure', path: '/', domain: 'child.example.com', secure: true, hostOnly: true),
            'http://child.example.com/',
            'sid=insecure; Domain=example.com; Path=/',
            ['secure'],
        ];
        yield 'overlapping insecure deletion' => [
            new Cookie('sid', 'secure', path: '/login', domain: 'example.com', secure: true),
            'http://example.com/login/en',
            'sid=deleted; Domain=example.com; Path=/login/en; Max-Age=0',
            ['secure'],
        ];
        yield 'new parent path' => [
            new Cookie('sid', 'secure', path: '/login', domain: 'example.com', secure: true),
            'http://example.com/',
            'sid=insecure; Domain=example.com; Path=/',
            ['secure', 'insecure'],
        ];
    }

    #[DataProvider('provideNonOverlappingSecureCookies')]
    public function testBrowserCompatibleModeAllowsInsecureCookiesThatDoNotOverlaySecureCookies(string $secureUri, string $secureHeader)
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->updateFromSetCookie([$secureHeader], $secureUri);
        $cookieJar->updateFromSetCookie(['foo=insecure'], 'http://example.com/');

        $this->assertSame(['foo' => 'insecure'], $cookieJar->allValues('http://example.com/'));
    }

    public static function provideNonOverlappingSecureCookies(): iterable
    {
        yield 'different name' => ['https://example.com/', 'other=secure; Secure'];
        yield 'different domain' => ['https://other.example/', 'foo=secure; Secure'];
        yield 'different path' => ['https://example.com/login', 'foo=secure; Secure; Path=/login'];
    }

    #[DataProvider('provideBrowserCompatibleCookieNamePrefixes')]
    public function testBrowserCompatibleModeEnforcesCookieNamePrefixes(string $uri, string $header, bool $accepted)
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->updateFromSetCookie([$header], $uri);

        $this->assertSame($accepted ? 1 : 0, \count($cookieJar->all()));
    }

    public static function provideBrowserCompatibleCookieNamePrefixes(): iterable
    {
        yield ['https://example.com/', '__Secure-foo=bar; Secure', true];
        yield ['https://example.com/', '__SeCuRe-foo=bar; Secure', true];
        yield ['https://example.com/', '__Secure-foo=bar', false];
        yield ['https://example.com/', '__Host-foo=bar; Secure; Path=/', true];
        yield ['https://example.com/', '__HoSt-foo=bar; Secure; Path=/', true];
        yield ['https://example.com/', '__Host-foo=bar; Secure; Domain=; Path=/', true];
        yield ['https://example.com/', '__Host-foo=bar; Secure; Path=', true];
        yield ['https://example.com/', 'x__Host-foo=bar', true];
        yield ['https://example.com/', '__Host-foo=bar; Secure', false];
        yield ['https://example.com/', '__Host-foo=bar; Secure; Path', false];
        yield ['https://example.com/', '__Host-foo=bar; Secure; Path=/account', false];
        yield ['https://example.com/', '__Host-foo=bar; Secure; Path=/; Domain=example.com', false];
    }

    public function testBrowserCompatibleModeRejectsControlPrefixedCookieNames()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->updateFromSetCookie(["\x0Bfoo=bar"], 'https://example.com/');

        $this->assertSame([], $cookieJar->all());
    }

    public function testBrowserCompatibleModeDoesNotTreatControlPrefixedPathAsExplicit()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->updateFromSetCookie(["__Host-foo=bar; Secure; \x0BPath=/"], 'https://example.com/');

        $this->assertSame([], $cookieJar->all());
    }

    public function testBrowserCompatibleModeDoesNotDeleteAValidCookieWithAnInvalidPrefix()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->updateFromSetCookie(['__Host-foo=secure; Secure; Path=/'], 'https://example.com/');
        $cookieJar->updateFromSetCookie(['__Host-foo=deleted; Path=/; Max-Age=0'], 'https://example.com/');

        $this->assertSame('__Host-foo=secure', $cookieJar->getCookieHeader('https://example.com/'));
    }

    public function testBrowserCompatibleModePreservesMatchingCookiesWithTheSameNameInTheCookieHeader()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->updateFromSetCookie(['foo=root; Path=/'], 'https://example.com/');
        $cookieJar->updateFromSetCookie(['foo=account; Path=/account'], 'https://example.com/account');

        $this->assertSame('foo=root; foo=account', $cookieJar->getCookieHeader('https://example.com/account/profile'));
        $this->assertSame(['foo' => 'account'], $cookieJar->allRawValues('https://example.com/account/profile'));

        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->updateFromSetCookie(['foo=account; Path=/account'], 'https://example.com/account');
        $cookieJar->updateFromSetCookie(['foo=root; Path=/'], 'https://example.com/');

        $this->assertSame('account', $cookieJar->get('foo', '/account/profile', 'example.com')->getValue());
    }

    public function testBrowserCompatibleModeAllValuesPrefersTheMostSpecificDomain()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->updateFromSetCookie(['foo=parent; Domain=example.com'], 'https://example.com/');
        $cookieJar->updateFromSetCookie(['foo=child; Domain=admin.example.com'], 'https://admin.example.com/');

        $this->assertSame(['foo' => 'child'], $cookieJar->allValues('https://admin.example.com/'));
        $this->assertSame('foo=parent; foo=child', $cookieJar->getCookieHeader('https://admin.example.com/'));
    }

    public function testBrowserCompatibleModeLimitsAcceptedResponseCookies()
    {
        $headers = ['oversized='.str_repeat('x', 8190)];
        for ($i = 0; $i < 51; ++$i) {
            $headers[] = 'cookie'.$i.'=value';
        }

        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->updateFromSetCookie($headers, 'https://example.com/');

        $this->assertCount(50, $cookieJar->all());
        $this->assertNull($cookieJar->get('cookie50', '/', 'example.com'));
        $this->assertNull($cookieJar->get('oversized', '/', 'example.com'));
    }

    public function testBrowserCompatibleModeLimitsSetCookieFieldLengthAtTheExactBoundary()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->updateFromSetCookie([
            'a='.str_repeat('x', 8188),
            'b='.str_repeat('x', 8189),
        ], 'https://example.com/');

        $this->assertSame(8188, \strlen($cookieJar->get('a', '/', 'example.com')->getValue()));
        $this->assertNull($cookieJar->get('b', '/', 'example.com'));
    }

    public function testBrowserCompatibleModeKeepsHostOnlyAndDomainCookiesWithTheSameIdentity()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->updateFromSetCookie(['foo=host'], 'https://example.com/');
        $cookieJar->updateFromSetCookie(['foo=domain; Domain=example.com'], 'https://example.com/');

        $this->assertCount(2, $cookieJar->all());
        $this->assertSame('foo=host; foo=domain', $cookieJar->getCookieHeader('https://example.com/'));
        $this->assertSame(['foo' => 'host'], $cookieJar->allValues('https://example.com/'));
        $this->assertSame(['foo' => 'domain'], $cookieJar->allValues('https://sub.example.com/'));
    }

    public function testBrowserCompatibleModeMatchesIpDomainsExactly()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->updateFromSetCookie(['foo=bar; Domain=127.0.0.1'], 'http://127.0.0.1/');

        $this->assertSame(['foo' => 'bar'], $cookieJar->allValues('http://127.0.0.1/'));
        $this->assertSame([], $cookieJar->allValues('http://sub.127.0.0.1/'));
        $this->assertNull($cookieJar->get('foo', '/', '192.0.2.1'));
    }

    #[DataProvider('provideInvalidRequestHosts')]
    public function testBrowserCompatibleModeDoesNotSuffixMatchInvalidRequestHosts(string $domain)
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->set(new Cookie('foo', 'bar', domain: 'example.com'));

        $this->assertNull($cookieJar->get('foo', '/', $domain));
    }

    public static function provideInvalidRequestHosts(): iterable
    {
        yield 'tab' => ["evil\t.example.com"];
        yield 'newline' => ["evil\n.example.com"];
        yield 'backslash' => ['evil\\.example.com'];
        yield 'slash' => ['evil/.example.com'];
        yield 'at sign' => ['evil@.example.com'];
        yield 'encoded space' => ['evil%20.example.com'];
        yield 'encoded slash' => ['evil%2f.example.com'];
        yield 'malformed percent encoding' => ['evil%gg.example.com'];
    }

    public function testBrowserCompatibleModeSuffixMatchesValidRegisteredNames()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->set(new Cookie('foo', 'bar', domain: 'example.com'));

        $this->assertSame('foo=bar', $cookieJar->getCookieHeader('https://valid_name!.example.com/'));
        $this->assertSame('foo=bar', $cookieJar->getCookieHeader('https://valid%41.example.com/'));
    }

    public function testBrowserCompatibleModeKeepsInvalidDomainsExactMatchOnly()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->set(new Cookie('foo', 'bar', domain: "evil\t.example.com"));

        $this->assertSame('bar', $cookieJar->get('foo', '/', "evil\t.example.com")->getValue());
        $this->assertNull($cookieJar->get('foo', '/', "sub.evil\t.example.com"));
    }

    public function testBrowserCompatibleModeCanonicalizesIpv6HostOnlyCookieIdentity()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->updateFromSetCookie(['foo=old'], 'https://[0:0:0:0:0:0:0:1]/');
        $cookieJar->updateFromSetCookie(['foo=new'], 'https://[::1]/');

        $this->assertCount(1, $cookieJar->all());
        $this->assertSame(['foo' => 'new'], $cookieJar->allValues('https://[::1]/'));
    }

    public function testBrowserCompatibleModeCanonicalizesBareIpv6CookieIdentity()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->set(new Cookie('foo', 'old', domain: '0:0:0:0:0:0:0:1'));
        $cookieJar->set(new Cookie('foo', 'new', domain: '::1'));

        $this->assertCount(1, $cookieJar->all());
        $this->assertSame('new', $cookieJar->get('foo', '/', '::1')->getValue());
    }

    public function testBrowserCompatibleModeKeepsBareAndBracketedIpv6CookieIdentitiesSeparate()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->set(new Cookie('foo', 'bare', domain: '::1'));
        $cookieJar->set(new Cookie('foo', 'bracketed', domain: '[::1]'));

        $this->assertCount(2, $cookieJar->all());
    }

    public function testBrowserCompatibleModeRejectsInvalidCookieNamesAndDomains()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->updateFromSetCookie([
            'missing-equals',
            'invalid name=value',
            'invalid-domain=value; Domain=..example.com',
        ], 'https://example.com/');

        $this->assertSame([], $cookieJar->all());
    }

    public function testBrowserCompatibleModeRejectsInvalidManuallySetCookies()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->set(new Cookie('', 'value', domain: 'example.com'));
        $cookieJar->set(new Cookie('invalid name', 'value', domain: 'example.com'));
        $cookieJar->set(new Cookie('missing-domain', 'value'));
        $cookieJar->set(new Cookie('dot-domain', 'value', domain: '..'));

        $this->assertSame([], $cookieJar->all());
    }

    public function testBrowserCompatibleModeStripsAtMostOneLeadingDomainDot()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->set(new Cookie('foo', 'single', domain: '.example.com'));

        $this->assertSame('foo=single', $cookieJar->getCookieHeader('https://sub.example.com/'));

        foreach (['..example.com', '...example.com'] as $domain) {
            $cookieJar = CookieJar::createBrowserCompatible();
            $cookieJar->set(new Cookie('foo', 'invalid', domain: $domain));

            $this->assertSame('', $cookieJar->getCookieHeader('https://example.com/'));
            $this->assertSame('', $cookieJar->getCookieHeader('https://sub.example.com/'));
        }
    }

    public function testBrowserCompatibleModeTreatsATrailingDotDomainAsHostOnly()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->updateFromSetCookie(['foo=bar; Domain=example.com.'], 'https://example.com/');

        $this->assertSame(['foo' => 'bar'], $cookieJar->allValues('https://example.com/'));
        $this->assertSame([], $cookieJar->allValues('https://sub.example.com/'));
        $this->assertTrue($cookieJar->get('foo', '/', 'example.com')->isHostOnly());
    }

    public function testBrowserCompatibleModeKeepsPercentEncodedDomainsExactMatchOnly()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->updateFromSetCookie(['foo=bar; Domain=example%2ecom'], 'https://example%2ecom/');

        $this->assertSame(['foo' => 'bar'], $cookieJar->allValues('https://example%2ecom/'));
        $this->assertSame([], $cookieJar->allValues('https://sub.example%2ecom/'));
    }

    public function testBrowserCompatibleModeDoesNotCountIdenticalCookiesTowardsTheResponseLimit()
    {
        $headers = array_fill(0, 50, 'same=value');
        $headers[] = 'last=value';

        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->updateFromSetCookie($headers, 'https://example.com/');

        $this->assertSame(['same', 'last'], array_map(static fn (Cookie $cookie): string => $cookie->getName(), $cookieJar->all()));
    }

    public function testBrowserCompatibleModeTreatsEachSetCookieHeaderAsOneField()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->updateFromSetCookie(['foo=first, bar=second'], 'https://example.com/');

        $this->assertSame(['foo' => 'first, bar=second'], $cookieJar->allValues('https://example.com/'));
    }

    public function testBrowserCompatibleModeLimitsTheCookieHeader()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $this->assertSame('', $cookieJar->getCookieHeader('https://example.com/'));

        for ($i = 0; $i < 151; ++$i) {
            $cookieJar->set(new Cookie('cookie'.$i, 'value', domain: 'example.com'));
        }

        $this->assertCount(150, explode('; ', $cookieJar->getCookieHeader('https://example.com/')));

        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->set(new Cookie('a', str_repeat('x', 8180), domain: 'example.com'));
        $cookieJar->set(new Cookie('b', 'value', domain: 'example.com'));

        $this->assertSame('a='.str_repeat('x', 8180), $cookieJar->getCookieHeader('https://example.com/'));
    }

    public function testBrowserCompatibleModeFiltersCookiesBeforeApplyingRequestLimits()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        for ($i = 0; $i < 147; ++$i) {
            $cookieJar->set(new Cookie('other'.$i, 'value', domain: 'other.example'));
        }
        $cookieJar->set(new Cookie('path', 'value', path: '/other', domain: 'example.com'));
        $cookieJar->set(new Cookie('secure', 'value', domain: 'example.com', secure: true));
        $cookieJar->set(new Cookie('expired', 'value', time() - 1, domain: 'example.com'));
        $cookieJar->set(new Cookie('matching', 'value', domain: 'example.com'));

        $this->assertSame('matching=value', $cookieJar->getCookieHeader('http://example.com/path'));
    }

    public function testBrowserCompatibleModeCanSetAndExpireHostOnlyCookiesManually()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->set(new Cookie('foo', 'host', domain: 'example.com', hostOnly: true));
        $cookieJar->set(new Cookie('foo', 'domain', domain: 'example.com'));

        $this->assertSame('foo=host; foo=domain', $cookieJar->getCookieHeader('https://example.com/'));

        $cookieJar->expire('foo', '/', 'example.com');

        $this->assertSame([], $cookieJar->all());
    }

    #[DataProvider('provideBrowserCompatibleCookieDeletions')]
    public function testBrowserCompatibleModeResponseDeletionPreservesTheOtherHostOnlyIdentity(string $deletion, string $sameHost, string $childHost)
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->updateFromSetCookie([
            'sid=host; Path=/',
            'sid=domain; Domain=example.com; Path=/',
        ], 'https://example.com/');
        $cookieJar->updateFromSetCookie([$deletion], 'https://example.com/');

        $this->assertCount(1, $cookieJar->all());
        $this->assertSame($sameHost, $cookieJar->getCookieHeader('https://example.com/'));
        $this->assertSame($childHost, $cookieJar->getCookieHeader('https://www.example.com/'));
    }

    public static function provideBrowserCompatibleCookieDeletions(): iterable
    {
        yield 'host-only cookie' => ['sid=deleted; Max-Age=0; Path=/', 'sid=domain', 'sid=domain'];
        yield 'domain cookie' => ['sid=deleted; Domain=example.com; Max-Age=0; Path=/', 'sid=host', ''];
    }

    public function testClearSessionCookies()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set(new Cookie('session', 'value'));
        $cookieJar->set(new Cookie('epoch', 'value', 0));
        $cookieJar->set(new Cookie('persistent', 'value', time() + 3600));

        $cookieJar->clearSessionCookies();

        $this->assertSame(['persistent'], array_map(static fn (Cookie $cookie): string => $cookie->getName(), $cookieJar->all()));
    }

    public function testClearSessionCookiesInBrowserCompatibleMode()
    {
        $cookieJar = CookieJar::createBrowserCompatible();
        $cookieJar->set(new Cookie('session', 'value', domain: 'example.com'));
        $cookieJar->set(new Cookie('persistent', 'value', time() + 3600, domain: 'example.com'));

        $cookieJar->clearSessionCookies();

        $this->assertSame(['persistent'], array_map(static fn (Cookie $cookie): string => $cookie->getName(), $cookieJar->all()));
    }
}
