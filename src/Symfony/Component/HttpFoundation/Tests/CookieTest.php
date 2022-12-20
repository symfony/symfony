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

/**
 * CookieTest.
 *
 * @author John Kary <john@johnkary.net>
 * @author Hugo Hamon <hugo.hamon@sensio.com>
 *
 * @group time-sensitive
 */
class CookieTest extends TestCase
{
    public function namesWithSpecialCharacters()
    {
        return [
            [',MyName'],
            [';MyName'],
            [' MyName'],
            ["\tMyName"],
            ["\rMyName"],
            ["\nMyName"],
            ["\013MyName"],
            ["\014MyName"],
        ];
    }

    /**
     * @dataProvider namesWithSpecialCharacters
     */
    public function testInstantiationThrowsExceptionIfRawCookieNameContainsSpecialCharacters($name)
    {
        self::expectException(\InvalidArgumentException::class);
        Cookie::create($name, null, 0, null, null, null, false, true);
    }

    /**
     * @dataProvider namesWithSpecialCharacters
     */
    public function testWithRawThrowsExceptionIfCookieNameContainsSpecialCharacters($name)
    {
        self::expectException(\InvalidArgumentException::class);
        Cookie::create($name)->withRaw();
    }

    /**
     * @dataProvider namesWithSpecialCharacters
     */
    public function testInstantiationSucceedNonRawCookieNameContainsSpecialCharacters($name)
    {
        self::assertInstanceOf(Cookie::class, Cookie::create($name));
    }

    public function testInstantiationThrowsExceptionIfCookieNameIsEmpty()
    {
        self::expectException(\InvalidArgumentException::class);
        Cookie::create('');
    }

    public function testInvalidExpiration()
    {
        self::expectException(\InvalidArgumentException::class);
        Cookie::create('MyCookie', 'foo', 'bar');
    }

    public function testNegativeExpirationIsNotPossible()
    {
        $cookie = Cookie::create('foo', 'bar', -100);

        self::assertSame(0, $cookie->getExpiresTime());

        $cookie = Cookie::create('foo', 'bar')->withExpires(-100);

        self::assertSame(0, $cookie->getExpiresTime());
    }

    public function testGetValue()
    {
        $value = 'MyValue';
        $cookie = Cookie::create('MyCookie', $value);

        self::assertSame($value, $cookie->getValue(), '->getValue() returns the proper value');
    }

    public function testGetPath()
    {
        $cookie = Cookie::create('foo', 'bar');

        self::assertSame('/', $cookie->getPath(), '->getPath() returns / as the default path');
    }

    public function testGetExpiresTime()
    {
        $cookie = Cookie::create('foo', 'bar');

        self::assertEquals(0, $cookie->getExpiresTime(), '->getExpiresTime() returns the default expire date');

        $cookie = Cookie::create('foo', 'bar', $expire = time() + 3600);

        self::assertEquals($expire, $cookie->getExpiresTime(), '->getExpiresTime() returns the expire date');

        $cookie = Cookie::create('foo')->withExpires($expire = time() + 3600);

        self::assertEquals($expire, $cookie->getExpiresTime(), '->getExpiresTime() returns the expire date');
    }

    public function testGetExpiresTimeIsCastToInt()
    {
        $cookie = Cookie::create('foo', 'bar', 3600.9);

        self::assertSame(3600, $cookie->getExpiresTime(), '->getExpiresTime() returns the expire date as an integer');

        $cookie = Cookie::create('foo')->withExpires(3600.6);

        self::assertSame(3600, $cookie->getExpiresTime(), '->getExpiresTime() returns the expire date as an integer');
    }

    public function testConstructorWithDateTime()
    {
        $expire = new \DateTime();
        $cookie = Cookie::create('foo', 'bar', $expire);

        self::assertEquals($expire->format('U'), $cookie->getExpiresTime(), '->getExpiresTime() returns the expire date');

        $cookie = Cookie::create('foo')->withExpires($expire);

        self::assertEquals($expire->format('U'), $cookie->getExpiresTime(), '->getExpiresTime() returns the expire date');
    }

    public function testConstructorWithDateTimeImmutable()
    {
        $expire = new \DateTimeImmutable();
        $cookie = Cookie::create('foo', 'bar', $expire);

        self::assertEquals($expire->format('U'), $cookie->getExpiresTime(), '->getExpiresTime() returns the expire date');

        $cookie = Cookie::create('foo')->withValue('bar')->withExpires($expire);

        self::assertEquals($expire->format('U'), $cookie->getExpiresTime(), '->getExpiresTime() returns the expire date');
    }

    public function testGetExpiresTimeWithStringValue()
    {
        $value = '+1 day';
        $cookie = Cookie::create('foo', 'bar', $value);
        $expire = strtotime($value);

        self::assertEqualsWithDelta($expire, $cookie->getExpiresTime(), 1, '->getExpiresTime() returns the expire date');

        $cookie = Cookie::create('foo')->withValue('bar')->withExpires($value);

        self::assertEqualsWithDelta($expire, $cookie->getExpiresTime(), 1, '->getExpiresTime() returns the expire date');
    }

    public function testGetDomain()
    {
        $cookie = Cookie::create('foo', 'bar', 0, '/', '.myfoodomain.com');

        self::assertEquals('.myfoodomain.com', $cookie->getDomain(), '->getDomain() returns the domain name on which the cookie is valid');

        $cookie = Cookie::create('foo')->withDomain('.mybardomain.com');

        self::assertEquals('.mybardomain.com', $cookie->getDomain(), '->getDomain() returns the domain name on which the cookie is valid');
    }

    public function testIsSecure()
    {
        $cookie = Cookie::create('foo', 'bar', 0, '/', '.myfoodomain.com', true);

        self::assertTrue($cookie->isSecure(), '->isSecure() returns whether the cookie is transmitted over HTTPS');

        $cookie = Cookie::create('foo')->withSecure(true);

        self::assertTrue($cookie->isSecure(), '->isSecure() returns whether the cookie is transmitted over HTTPS');
    }

    public function testIsHttpOnly()
    {
        $cookie = Cookie::create('foo', 'bar', 0, '/', '.myfoodomain.com', false, true);

        self::assertTrue($cookie->isHttpOnly(), '->isHttpOnly() returns whether the cookie is only transmitted over HTTP');

        $cookie = Cookie::create('foo')->withHttpOnly(true);

        self::assertTrue($cookie->isHttpOnly(), '->isHttpOnly() returns whether the cookie is only transmitted over HTTP');
    }

    public function testCookieIsNotCleared()
    {
        $cookie = Cookie::create('foo', 'bar', time() + 3600 * 24);

        self::assertFalse($cookie->isCleared(), '->isCleared() returns false if the cookie did not expire yet');

        $cookie = Cookie::create('foo')->withExpires(time() + 3600 * 24);

        self::assertFalse($cookie->isCleared(), '->isCleared() returns false if the cookie did not expire yet');
    }

    public function testCookieIsCleared()
    {
        $cookie = Cookie::create('foo', 'bar', time() - 20);

        self::assertTrue($cookie->isCleared(), '->isCleared() returns true if the cookie has expired');

        $cookie = Cookie::create('foo')->withExpires(time() - 20);

        self::assertTrue($cookie->isCleared(), '->isCleared() returns true if the cookie has expired');

        $cookie = Cookie::create('foo', 'bar');

        self::assertFalse($cookie->isCleared());

        $cookie = Cookie::create('foo', 'bar');

        self::assertFalse($cookie->isCleared());

        $cookie = Cookie::create('foo', 'bar', -1);

        self::assertFalse($cookie->isCleared());

        $cookie = Cookie::create('foo')->withExpires(-1);

        self::assertFalse($cookie->isCleared());
    }

    public function testToString()
    {
        $expected = 'foo=bar; expires=Fri, 20-May-2011 15:25:52 GMT; Max-Age=0; path=/; domain=.myfoodomain.com; secure; httponly';
        $cookie = Cookie::create('foo', 'bar', $expire = strtotime('Fri, 20-May-2011 15:25:52 GMT'), '/', '.myfoodomain.com', true, true, false, null);
        self::assertEquals($expected, (string) $cookie, '->__toString() returns string representation of the cookie');

        $cookie = Cookie::create('foo')
            ->withValue('bar')
            ->withExpires(strtotime('Fri, 20-May-2011 15:25:52 GMT'))
            ->withDomain('.myfoodomain.com')
            ->withSecure(true)
            ->withSameSite(null);
        self::assertEquals($expected, (string) $cookie, '->__toString() returns string representation of the cookie');

        $expected = 'foo=bar%20with%20white%20spaces; expires=Fri, 20-May-2011 15:25:52 GMT; Max-Age=0; path=/; domain=.myfoodomain.com; secure; httponly';
        $cookie = Cookie::create('foo', 'bar with white spaces', strtotime('Fri, 20-May-2011 15:25:52 GMT'), '/', '.myfoodomain.com', true, true, false, null);
        self::assertEquals($expected, (string) $cookie, '->__toString() encodes the value of the cookie according to RFC 3986 (white space = %20)');

        $cookie = Cookie::create('foo')
            ->withValue('bar with white spaces')
            ->withExpires(strtotime('Fri, 20-May-2011 15:25:52 GMT'))
            ->withDomain('.myfoodomain.com')
            ->withSecure(true)
            ->withSameSite(null);
        self::assertEquals($expected, (string) $cookie, '->__toString() encodes the value of the cookie according to RFC 3986 (white space = %20)');

        $expected = 'foo=deleted; expires='.gmdate('D, d-M-Y H:i:s T', $expire = time() - 31536001).'; Max-Age=0; path=/admin/; domain=.myfoodomain.com; httponly';
        $cookie = Cookie::create('foo', null, 1, '/admin/', '.myfoodomain.com', false, true, false, null);
        self::assertEquals($expected, (string) $cookie, '->__toString() returns string representation of a cleared cookie if value is NULL');

        $cookie = Cookie::create('foo')
            ->withExpires(1)
            ->withPath('/admin/')
            ->withDomain('.myfoodomain.com')
            ->withSameSite(null);
        self::assertEquals($expected, (string) $cookie, '->__toString() returns string representation of a cleared cookie if value is NULL');

        $expected = 'foo=bar; path=/; httponly; samesite=lax';
        $cookie = Cookie::create('foo', 'bar');
        self::assertEquals($expected, (string) $cookie);

        $cookie = Cookie::create('foo')->withValue('bar');
        self::assertEquals($expected, (string) $cookie);
    }

    public function testRawCookie()
    {
        $cookie = Cookie::create('foo', 'b a r', 0, '/', null, false, false, false, null);
        self::assertFalse($cookie->isRaw());
        self::assertEquals('foo=b%20a%20r; path=/', (string) $cookie);

        $cookie = Cookie::create('test')->withValue('t e s t')->withHttpOnly(false)->withSameSite(null);
        self::assertFalse($cookie->isRaw());
        self::assertEquals('test=t%20e%20s%20t; path=/', (string) $cookie);

        $cookie = Cookie::create('foo', 'b+a+r', 0, '/', null, false, false, true, null);
        self::assertTrue($cookie->isRaw());
        self::assertEquals('foo=b+a+r; path=/', (string) $cookie);

        $cookie = Cookie::create('foo')
            ->withValue('t+e+s+t')
            ->withHttpOnly(false)
            ->withRaw(true)
            ->withSameSite(null);
        self::assertTrue($cookie->isRaw());
        self::assertEquals('foo=t+e+s+t; path=/', (string) $cookie);
    }

    public function testGetMaxAge()
    {
        $cookie = Cookie::create('foo', 'bar');
        self::assertEquals(0, $cookie->getMaxAge());

        $cookie = Cookie::create('foo', 'bar', $expire = time() + 100);
        self::assertEquals($expire - time(), $cookie->getMaxAge());

        $cookie = Cookie::create('foo', 'bar', $expire = time() - 100);
        self::assertEquals(0, $cookie->getMaxAge());
    }

    public function testFromString()
    {
        $cookie = Cookie::fromString('foo=bar; expires=Fri, 20-May-2011 15:25:52 GMT; path=/; domain=.myfoodomain.com; secure; httponly');
        self::assertEquals(Cookie::create('foo', 'bar', strtotime('Fri, 20-May-2011 15:25:52 GMT'), '/', '.myfoodomain.com', true, true, true, null), $cookie);

        $cookie = Cookie::fromString('foo=bar', true);
        self::assertEquals(Cookie::create('foo', 'bar', 0, '/', null, false, false, false, null), $cookie);

        $cookie = Cookie::fromString('foo', true);
        self::assertEquals(Cookie::create('foo', null, 0, '/', null, false, false, false, null), $cookie);

        $cookie = Cookie::fromString('foo_cookie=foo=1&bar=2&baz=3; expires=Tue, 22-Sep-2020 06:27:09 GMT; path=/');
        self::assertEquals(Cookie::create('foo_cookie', 'foo=1&bar=2&baz=3', strtotime('Tue, 22-Sep-2020 06:27:09 GMT'), '/', null, false, false, true, null), $cookie);

        $cookie = Cookie::fromString('foo_cookie=foo==; expires=Tue, 22-Sep-2020 06:27:09 GMT; path=/');
        self::assertEquals(Cookie::create('foo_cookie', 'foo==', strtotime('Tue, 22-Sep-2020 06:27:09 GMT'), '/', null, false, false, true, null), $cookie);
    }

    public function testFromStringWithHttpOnly()
    {
        $cookie = Cookie::fromString('foo=bar; expires=Fri, 20-May-2011 15:25:52 GMT; path=/; domain=.myfoodomain.com; secure; httponly');
        self::assertTrue($cookie->isHttpOnly());

        $cookie = Cookie::fromString('foo=bar; expires=Fri, 20-May-2011 15:25:52 GMT; path=/; domain=.myfoodomain.com; secure');
        self::assertFalse($cookie->isHttpOnly());
    }

    public function testSameSiteAttribute()
    {
        $cookie = new Cookie('foo', 'bar', 0, '/', null, false, true, false, 'Lax');
        self::assertEquals('lax', $cookie->getSameSite());

        $cookie = new Cookie('foo', 'bar', 0, '/', null, false, true, false, '');
        self::assertNull($cookie->getSameSite());

        $cookie = Cookie::create('foo')->withSameSite('Lax');
        self::assertEquals('lax', $cookie->getSameSite());
    }

    public function testSetSecureDefault()
    {
        $cookie = Cookie::create('foo', 'bar');

        self::assertFalse($cookie->isSecure());

        $cookie->setSecureDefault(true);

        self::assertTrue($cookie->isSecure());

        $cookie->setSecureDefault(false);

        self::assertFalse($cookie->isSecure());
    }

    public function testMaxAge()
    {
        $futureDateOneHour = gmdate('D, d-M-Y H:i:s T', time() + 3600);

        $cookie = Cookie::fromString('foo=bar; Max-Age=3600; path=/');
        self::assertEquals('foo=bar; expires='.$futureDateOneHour.'; Max-Age=3600; path=/', $cookie->__toString());

        $cookie = Cookie::fromString('foo=bar; expires='.$futureDateOneHour.'; Max-Age=3600; path=/');
        self::assertEquals('foo=bar; expires='.$futureDateOneHour.'; Max-Age=3600; path=/', $cookie->__toString());

        $futureDateHalfHour = gmdate('D, d-M-Y H:i:s T', time() + 1800);

        // Max-Age value takes precedence before expires
        $cookie = Cookie::fromString('foo=bar; expires='.$futureDateHalfHour.'; Max-Age=3600; path=/');
        self::assertEquals('foo=bar; expires='.$futureDateOneHour.'; Max-Age=3600; path=/', $cookie->__toString());
    }

    public function testExpiredWithMaxAge()
    {
        $cookie = Cookie::fromString('foo=bar; expires=Fri, 20-May-2011 15:25:52 GMT; Max-Age=0; path=/');
        self::assertEquals('foo=bar; expires=Fri, 20-May-2011 15:25:52 GMT; Max-Age=0; path=/', $cookie->__toString());

        $futureDate = gmdate('D, d-M-Y H:i:s T', time() + 864000);

        $cookie = Cookie::fromString('foo=bar; expires='.$futureDate.'; Max-Age=0; path=/');
        self::assertEquals(time(), $cookie->getExpiresTime());
        self::assertEquals('foo=bar; expires='.gmdate('D, d-M-Y H:i:s T', $cookie->getExpiresTime()).'; Max-Age=0; path=/', $cookie->__toString());
    }
}
