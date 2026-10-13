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

    /**
     * @dataProvider getTestsForToFromString
     */
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

    /**
     * @dataProvider getExpireCookieStrings
     */
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

    public function testFromStringTrimsAttributeNamesAndValues()
    {
        $cookie = Cookie::fromString('foo=bar; Domain = .example.com; Path = /account; Expires = Fri, 20 May 2011 15:25:52 GMT; SameSite = Lax', 'http://www.example.com/');

        $this->assertSame('.example.com', $cookie->getDomain());
        $this->assertSame('/account', $cookie->getPath());
        $this->assertSame('1305905152', $cookie->getExpiresTime());
        $this->assertSame('Lax', $cookie->getSameSite());

        $this->assertTrue(Cookie::fromString('foo=bar; Max-Age = 0')->isExpired());
    }

    public function testFromStringUsesTheDefaultPathWhenThePathAttributeDoesNotStartWithASlash()
    {
        $this->assertSame('/foo', Cookie::fromString('foo=bar; Path=relative', 'http://example.com/foo/bar')->getPath());
        $this->assertSame('/foo', Cookie::fromString('foo=bar; Path=', 'http://example.com/foo/bar')->getPath());
        $this->assertSame('/', Cookie::fromString('foo=bar; Path=relative')->getPath());
    }

    public function testFromStringIgnoresUnknownAttributes()
    {
        $cookie = Cookie::fromString('foo=bar; name=baz; value=qux; secure=1; unknown=value', 'http://example.com/');

        $this->assertSame('foo', $cookie->getName());
        $this->assertSame('bar', $cookie->getValue());
        $this->assertFalse($cookie->isSecure());
        $this->assertSame('foo=bar; domain=example.com; path=/', (string) $cookie);
    }

    public function testFromStringWithMaxAge()
    {
        $before = time();
        $cookie = Cookie::fromString('foo=bar; expires=Fri, 20 May 2011 15:25:52 GMT; Max-Age=60');

        $this->assertGreaterThanOrEqual($before + 60, $cookie->getExpiresTime());
        $this->assertLessThanOrEqual(time() + 60, $cookie->getExpiresTime());
        $this->assertFalse($cookie->isExpired());

        $this->assertSame((string) \PHP_INT_MAX, Cookie::fromString('foo=bar; Max-Age=99999999999999999999')->getExpiresTime());
    }

    public function testFromStringExpiresCookiesWithNonPositiveMaxAge()
    {
        $this->assertTrue(Cookie::fromString('foo=bar; Max-Age=0')->isExpired());
        $this->assertTrue(Cookie::fromString('foo=bar; Max-Age=-1')->isExpired());
        $this->assertTrue(Cookie::fromString('foo=bar; Max-Age=-99999999999999999999')->isExpired());
        $this->assertTrue(Cookie::fromString('foo=bar; expires=Fri, 20 May 2099 15:25:52 GMT; Max-Age=0')->isExpired());
        $this->assertTrue(Cookie::fromString('foo=bar; Max-Age=0; expires=Fri, 20 May 2099 15:25:52 GMT')->isExpired());
    }

    public function testFromStringIgnoresInvalidMaxAge()
    {
        $this->assertSame('1305905152', Cookie::fromString('foo=bar; expires=Fri, 20 May 2011 15:25:52 GMT; Max-Age=')->getExpiresTime());
        $this->assertSame('1305905152', Cookie::fromString('foo=bar; expires=Fri, 20 May 2011 15:25:52 GMT; Max-Age=1.5')->getExpiresTime());
        $this->assertSame('1305905152', Cookie::fromString('foo=bar; expires=Fri, 20 May 2011 15:25:52 GMT; Max-Age=+60')->getExpiresTime());
        $this->assertSame('1305905152', Cookie::fromString('foo=bar; expires=Fri, 20 May 2011 15:25:52 GMT; Max-Age=60s')->getExpiresTime());

        $this->assertTrue(Cookie::fromString('foo=bar; Max-Age=0; Max-Age=')->isExpired());
        $this->assertTrue(Cookie::fromString('foo=bar; Max-Age=0; Max-Age=1.5')->isExpired());
        $this->assertTrue(Cookie::fromString('foo=bar; Max-Age=0; Max-Age=+60')->isExpired());
        $this->assertTrue(Cookie::fromString('foo=bar; Max-Age=0; Max-Age=60s')->isExpired());
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
