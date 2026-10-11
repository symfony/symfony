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
use Symfony\Component\BrowserKit\Exception\InvalidArgumentException;
use Symfony\Component\BrowserKit\Exception\UnexpectedValueException;

class CookieTest extends TestCase
{
    public function testToString()
    {
        $cookie = new Cookie('foo', 'bar', strtotime('Fri, 20-May-2011 15:25:52 GMT'), '/', '.myfoodomain.com', true);
        $this->assertEquals('foo=bar; expires=Fri, 20 May 2011 15:25:52 GMT; domain=.myfoodomain.com; path=/; secure; httponly', (string) $cookie, '->__toString() returns string representation of the cookie');

        $cookie = new Cookie('foo', 'bar with white spaces', strtotime('Fri, 20-May-2011 15:25:52 GMT'), '/', '.myfoodomain.com', true);
        $this->assertEquals('foo=bar%20with%20white%20spaces; expires=Fri, 20 May 2011 15:25:52 GMT; domain=.myfoodomain.com; path=/; secure; httponly', (string) $cookie, '->__toString() encodes the value of the cookie according to RFC 3986 (white space = %20)');

        $cookie = new Cookie('foo', null, 1, '/admin/', '.myfoodomain.com');
        $this->assertEquals('foo=; expires=Thu, 01 Jan 1970 00:00:01 GMT; domain=.myfoodomain.com; path=/admin/; httponly', (string) $cookie, '->__toString() returns string representation of a cleared cookie if value is NULL');

        $cookie = new Cookie('foo', 'bar', 0, '/', '');
        $this->assertEquals('foo=bar; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; httponly', (string) $cookie);

        $cookie = new Cookie('foo', 'bar', 2, '/', '', true, true, false, 'lax');
        $this->assertEquals('foo=bar; expires=Thu, 01 Jan 1970 00:00:02 GMT; path=/; secure; httponly; samesite=lax', (string) $cookie);
    }

    #[DataProvider('getTestsForToFromString')]
    public function testToFromString($cookie, $url = null)
    {
        $this->assertEquals($cookie, (string) Cookie::fromString($cookie, $url));
    }

    public static function getTestsForToFromString()
    {
        return [
            ['foo=bar; path=/'],
            ['foo=bar; path=/foo'],
            ['foo="Test"; path=/'],
            ['foo=bar; domain=example.com; path=/'],
            ['foo=bar; domain=example.com; path=/; secure', 'https://example.com/'],
            ['foo=bar; path=/; httponly'],
            ['foo=bar; domain=example.com; path=/foo; secure; httponly', 'https://example.com/'],
            ['foo=bar=baz; path=/'],
            ['foo=bar%3Dbaz; path=/'],
        ];
    }

    public function testFromStringIgnoreSecureFlag()
    {
        $this->assertFalse(Cookie::fromString('foo=bar; secure')->isSecure());
        $this->assertFalse(Cookie::fromString('foo=bar; secure', 'http://example.com/')->isSecure());
    }

    public function testFromStringInBrowserCompatibleMode()
    {
        $before = time();
        $cookie = Cookie::fromStringBrowserCompatible('foo=bar; expires=Fri, 20 May 2011 15:25:52 GMT; Max-Age=60; secure', 'http://example.com/foo/bar');

        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHostOnly());
        $this->assertSame(60, $cookie->getMaxAge());
        $this->assertSame('/foo', $cookie->getPath());
        $this->assertGreaterThanOrEqual($before + 60, (int) $cookie->getExpiresTime());
        $this->assertLessThanOrEqual(time() + 60, (int) $cookie->getExpiresTime());
        $this->assertSame('foo=bar; expires='.gmdate('D, d M Y H:i:s T', (int) $cookie->getExpiresTime()).'; max-age=60; path=/foo; secure', (string) $cookie);
    }

    public function testFromStringInBrowserCompatibleModeUsesDomainAndPathAttributes()
    {
        $cookie = Cookie::fromStringBrowserCompatible('foo=bar; Domain=.Example.COM; Path=relative', 'https://www.example.com/foo/bar');

        $this->assertFalse($cookie->isHostOnly());
        $this->assertSame('example.com', $cookie->getDomain());
        $this->assertSame('/foo', $cookie->getPath());
        $this->assertSame('foo=bar; domain=example.com; path=/foo', (string) $cookie);
    }

    public function testFromStringInBrowserCompatibleModeTrimsAttributeNamesAndValues()
    {
        $cookie = Cookie::fromStringBrowserCompatible('foo=bar; Domain = .Example.COM; Path = /account; Max-Age = 60', 'https://www.example.com/');

        $this->assertSame('example.com', $cookie->getDomain());
        $this->assertSame('/account', $cookie->getPath());
        $this->assertSame(60, $cookie->getMaxAge());
    }

    public function testFromStringInBrowserCompatibleModeTrimsOnlyRfc6265Whitespace()
    {
        $cookie = Cookie::fromStringBrowserCompatible("\tfoo\t=\t\x0Bbar\x0B\t; \tPath\t=\t/p\t", 'https://example.com/');

        $this->assertSame('foo', $cookie->getName());
        $this->assertSame("\x0Bbar\x0B", $cookie->getRawValue());
        $this->assertSame('/p', $cookie->getPath());
    }

    public function testFromStringInBrowserCompatibleModeExpiresNonPositiveMaxAge()
    {
        $cookie = Cookie::fromStringBrowserCompatible('foo=bar; expires=Fri, 20 May 2099 15:25:52 GMT; Max-Age=0', 'https://example.com/');

        $this->assertSame(0, $cookie->getMaxAge());
        $this->assertTrue($cookie->isExpired());
    }

    public function testFromStringInBrowserCompatibleModeParsesLeadingZeroMaxAge()
    {
        $cookie = Cookie::fromStringBrowserCompatible('foo=bar; Max-Age=00060', 'https://example.com/');

        $this->assertSame(60, $cookie->getMaxAge());
    }

    public function testFromStringInBrowserCompatibleModeClampsMaxAgeOverflow()
    {
        $cookie = Cookie::fromStringBrowserCompatible('foo=bar; Max-Age='.\PHP_INT_MAX, 'https://example.com/');

        $this->assertSame((string) \PHP_INT_MAX, $cookie->getExpiresTime());
    }

    public function testFromStringInBrowserCompatibleModeIgnoresInvalidMaxAge()
    {
        $cookie = Cookie::fromStringBrowserCompatible('foo=bar; Max-Age=60; Max-Age=1.5', 'https://example.com/');

        $this->assertSame(60, $cookie->getMaxAge());
    }

    #[DataProvider('provideBrowserCompatibleNumericExpires')]
    public function testFromStringInBrowserCompatibleModeParsesNumericExpires(string $expires, ?string $expected, bool $expired)
    {
        $cookie = Cookie::fromStringBrowserCompatible('foo=bar; Expires='.$expires, 'https://example.com/');

        $this->assertSame($expected, $cookie->getExpiresTime());
        $this->assertSame($expired, $cookie->isExpired());
    }

    public static function provideBrowserCompatibleNumericExpires(): iterable
    {
        yield ['0', '0', true];
        yield ['-3', '-3', true];
        yield ['1.5', '1', true];
        yield ['1e999', null, false];
        yield ['9223372036854775808.0', null, false];
        yield ['999999999999999999999999', null, false];
    }

    public function testFromStringInBrowserCompatibleModeTreatsTheUnixEpochAsExpired()
    {
        $cookie = Cookie::fromStringBrowserCompatible('foo=bar; Expires=Thu, 01 Jan 1970 00:00:00 GMT', 'https://example.com/');

        $this->assertSame('0', $cookie->getExpiresTime());
        $this->assertTrue($cookie->isExpired());
    }

    public function testFromStringInBrowserCompatibleModeDoesNotClearBooleanAttributes()
    {
        $cookie = Cookie::fromStringBrowserCompatible('foo=bar; Secure; Secure=; HttpOnly; HttpOnly=0', 'https://example.com/');

        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
    }

    public function testFromStringInBrowserCompatibleModeAcceptsTruthyBooleanAttributeValues()
    {
        $cookie = Cookie::fromStringBrowserCompatible('foo=bar; Secure=1; HttpOnly=true', 'https://example.com/');

        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
    }

    public function testFromStringSignatureRemainsUnchanged()
    {
        $this->assertCount(2, (new \ReflectionMethod(Cookie::class, 'fromString'))->getParameters());
    }

    #[DataProvider('getExpireCookieStrings')]
    public function testFromStringAcceptsSeveralExpiresDateFormats($cookie)
    {
        $this->assertEquals(1596185377, Cookie::fromString($cookie)->getExpiresTime());
    }

    public static function getExpireCookieStrings()
    {
        return [
            ['foo=bar; expires=Fri, 31-Jul-2020 08:49:37 GMT'],
            ['foo=bar; expires=Fri, 31 Jul 2020 08:49:37 GMT'],
            ['foo=bar; expires=Fri, 31-07-2020 08:49:37 GMT'],
            ['foo=bar; expires=Fri, 31-07-20 08:49:37 GMT'],
            ['foo=bar; expires=Friday, 31-Jul-20 08:49:37 GMT'],
            ['foo=bar; expires=Fri Jul 31 08:49:37 2020'],
            ['foo=bar; expires=\'Fri Jul 31 08:49:37 2020\''],
            ['foo=bar; expires=Friday July 31st 2020, 08:49:37 GMT'],
        ];
    }

    public function testFromStringWithCapitalization()
    {
        $this->assertEquals('Foo=Bar; path=/', (string) Cookie::fromString('Foo=Bar'));
        $this->assertEquals('foo=bar; expires=Fri, 31 Dec 2010 23:59:59 GMT; path=/', (string) Cookie::fromString('foo=bar; Expires=Fri, 31 Dec 2010 23:59:59 GMT'));
        $this->assertEquals('foo=bar; domain=www.example.org; path=/; httponly', (string) Cookie::fromString('foo=bar; DOMAIN=www.example.org; HttpOnly'));
    }

    public function testFromStringWithUrl()
    {
        $this->assertEquals('foo=bar; domain=www.example.com; path=/', (string) Cookie::fromString('foo=bar', 'http://www.example.com/'));
        $this->assertEquals('foo=bar; domain=www.example.com; path=/', (string) Cookie::fromString('foo=bar', 'http://www.example.com'));
        $this->assertEquals('foo=bar; domain=www.example.com; path=/', (string) Cookie::fromString('foo=bar', 'http://www.example.com?foo'));
        $this->assertEquals('foo=bar; domain=www.example.com; path=/foo', (string) Cookie::fromString('foo=bar', 'http://www.example.com/foo/bar'));
        $this->assertEquals('foo=bar; domain=www.example.com; path=/', (string) Cookie::fromString('foo=bar; path=/', 'http://www.example.com/foo/bar'));
        $this->assertEquals('foo=bar; domain=www.myotherexample.com; path=/', (string) Cookie::fromString('foo=bar; domain=www.myotherexample.com', 'http://www.example.com/'));
    }

    public function testFromStringThrowsAnExceptionIfCookieIsNotValid()
    {
        $this->expectException(InvalidArgumentException::class);
        Cookie::fromString('foo');
    }

    public function testFromStringIgnoresInvalidExpiresDate()
    {
        $cookie = Cookie::fromString('foo=bar; expires=Flursday July 31st 2020, 08:49:37 GMT');

        $this->assertFalse($cookie->isExpired());
    }

    public function testFromStringThrowsAnExceptionIfUrlIsNotValid()
    {
        $this->expectException(InvalidArgumentException::class);
        Cookie::fromString('foo=bar', 'foobar');
    }

    public function testGetName()
    {
        $cookie = new Cookie('foo', 'bar');
        $this->assertEquals('foo', $cookie->getName(), '->getName() returns the cookie name');
    }

    public function testGetValue()
    {
        $cookie = new Cookie('foo', 'bar');
        $this->assertEquals('bar', $cookie->getValue(), '->getValue() returns the cookie value');

        $cookie = new Cookie('foo', 'bar%3Dbaz', null, '/', '', false, true, true); // raw value
        $this->assertEquals('bar=baz', $cookie->getValue(), '->getValue() returns the urldecoded cookie value');

        $cookie = new Cookie('foo', 'bar+baz', null, '/', '', false, true, true); // raw value
        $this->assertEquals('bar+baz', $cookie->getValue(), '->getValue() keeps plus signs of the raw cookie value');

        $cookie = new Cookie('foo', 'bar%2Bbaz', null, '/', '', false, true, true); // raw value
        $this->assertEquals('bar+baz', $cookie->getValue(), '->getValue() decodes percent-encoded plus signs');

        $this->assertEquals('bar+baz', Cookie::fromString('foo=bar+baz')->getValue());
    }

    public function testGetRawValue()
    {
        $cookie = new Cookie('foo', 'bar=baz'); // decoded value
        $this->assertEquals('bar%3Dbaz', $cookie->getRawValue(), '->getRawValue() returns the urlencoded cookie value');
        $cookie = new Cookie('foo', 'bar%3Dbaz', null, '/', '', false, true, true); // raw value
        $this->assertEquals('bar%3Dbaz', $cookie->getRawValue(), '->getRawValue() returns the non-urldecoded cookie value');

        $cookie = new Cookie('foo', 'bar+baz'); // decoded value
        $this->assertEquals('bar%2Bbaz', $cookie->getRawValue(), '->getRawValue() encodes plus signs of the cookie value');
    }

    public function testGetPath()
    {
        $cookie = new Cookie('foo', 'bar', 0);
        $this->assertEquals('/', $cookie->getPath(), '->getPath() returns / is no path is defined');

        $cookie = new Cookie('foo', 'bar', 0, '/foo');
        $this->assertEquals('/foo', $cookie->getPath(), '->getPath() returns the cookie path');
    }

    public function testGetDomain()
    {
        $cookie = new Cookie('foo', 'bar', 0, '/', 'foo.com');
        $this->assertEquals('foo.com', $cookie->getDomain(), '->getDomain() returns the cookie domain');
    }

    public function testIsSecure()
    {
        $cookie = new Cookie('foo', 'bar');
        $this->assertFalse($cookie->isSecure(), '->isSecure() returns false if not defined');

        $cookie = new Cookie('foo', 'bar', 0, '/', 'foo.com', true);
        $this->assertTrue($cookie->isSecure(), '->isSecure() returns the cookie secure flag');
    }

    public function testIsHttponly()
    {
        $cookie = new Cookie('foo', 'bar');
        $this->assertTrue($cookie->isHttpOnly(), '->isHttpOnly() returns false if not defined');

        $cookie = new Cookie('foo', 'bar', 0, '/', 'foo.com', false, true);
        $this->assertTrue($cookie->isHttpOnly(), '->isHttpOnly() returns the cookie httponly flag');
    }

    public function testGetExpiresTime()
    {
        $cookie = new Cookie('foo', 'bar');
        $this->assertNull($cookie->getExpiresTime(), '->getExpiresTime() returns the expires time');

        $cookie = new Cookie('foo', 'bar', $time = time() - 86400);
        $this->assertEquals($time, $cookie->getExpiresTime(), '->getExpiresTime() returns the expires time');
    }

    public function testIsExpired()
    {
        $cookie = new Cookie('foo', 'bar');
        $this->assertFalse($cookie->isExpired(), '->isExpired() returns false when the cookie never expires (null as expires time)');

        $cookie = new Cookie('foo', 'bar', time() - 86400);
        $this->assertTrue($cookie->isExpired(), '->isExpired() returns true when the cookie is expired');

        $cookie = new Cookie('foo', 'bar', 0);
        $this->assertFalse($cookie->isExpired());
    }

    public function testConstructException()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('The cookie expiration time "string" is not valid.');
        new Cookie('foo', 'bar', 'string');
    }

    public function testSameSite()
    {
        $cookie = new Cookie('foo', 'bar');
        $this->assertNull($cookie->getSameSite());

        $cookie = new Cookie('foo', 'bar', 0, '/', 'foo.com', false, true, false, 'lax');
        $this->assertSame('lax', $cookie->getSameSite());
    }
}
