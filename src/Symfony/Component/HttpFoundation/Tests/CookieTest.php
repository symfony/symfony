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
        $this->expectException('InvalidArgumentException');
        new Cookie($name, null, 0, null, null, null, false, true);
    }

    /**
     * @dataProvider namesWithSpecialCharacters
     */
    public function testInstantiationSucceedNonRawCookieNameContainsSpecialCharacters($name)
    {
        $this->assertInstanceOf(Cookie::class, new Cookie($name));
    }

    public function testInstantiationThrowsExceptionIfCookieNameIsEmpty()
    {
        $this->expectException('InvalidArgumentException');
        new Cookie('');
    }

    public function testInvalidExpiration()
    {
        $this->expectException('InvalidArgumentException');
        new Cookie('MyCookie', 'foo', 'bar');
    }

    public function testNegativeExpirationIsNotPossible()
    {
        $cookie = new Cookie('foo', 'bar', -100);

        $this->assertSame(0, $cookie->getExpiresTime());
    }

    public function testGetValue()
    {
        $value = 'MyValue';
        $cookie = new Cookie('MyCookie', $value);

        $this->assertSame($value, $cookie->getValue(), '->getValue() returns the proper value');
    }

    public function testGetPath()
    {
        $cookie = new Cookie('foo', 'bar');

        $this->assertSame('/', $cookie->getPath(), '->getPath() returns / as the default path');
    }

    public function testGetExpiresTime()
    {
        $cookie = new Cookie('foo', 'bar');

        $this->assertEquals(0, $cookie->getExpiresTime(), '->getExpiresTime() returns the default expire date');

        $cookie = new Cookie('foo', 'bar', $expire = time() + 3600);

        $this->assertEquals($expire, $cookie->getExpiresTime(), '->getExpiresTime() returns the expire date');
    }

    public function testGetExpiresTimeIsCastToInt()
    {
        $cookie = new Cookie('foo', 'bar', 3600.9);

        $this->assertSame(3600, $cookie->getExpiresTime(), '->getExpiresTime() returns the expire date as an integer');
    }

    public function testConstructorWithDateTime()
    {
        $expire = new \DateTime();
        $cookie = new Cookie('foo', 'bar', $expire);

        $this->assertEquals($expire->format('U'), $cookie->getExpiresTime(), '->getExpiresTime() returns the expire date');
    }

    /**
     * @requires PHP 5.5
     */
    public function testConstructorWithDateTimeImmutable()
    {
        $expire = new \DateTimeImmutable();
        $cookie = new Cookie('foo', 'bar', $expire);

        $this->assertEquals($expire->format('U'), $cookie->getExpiresTime(), '->getExpiresTime() returns the expire date');
    }

    public function testGetExpiresTimeWithStringValue()
    {
        $value = '+1 day';
        $cookie = new Cookie('foo', 'bar', $value);
        $expire = strtotime($value);

        $this->assertEqualsWithDelta($expire, $cookie->getExpiresTime(), 1, '->getExpiresTime() returns the expire date');
    }

    public function testGetDomain()
    {
        $cookie = new Cookie('foo', 'bar', 0, '/', '.myfoodomain.com');

        $this->assertEquals('.myfoodomain.com', $cookie->getDomain(), '->getDomain() returns the domain name on which the cookie is valid');
    }

    public function testIsSecure()
    {
        $cookie = new Cookie('foo', 'bar', 0, '/', '.myfoodomain.com', true);

        $this->assertTrue($cookie->isSecure(), '->isSecure() returns whether the cookie is transmitted over HTTPS');
    }

    public function testIsHttpOnly()
    {
        $cookie = new Cookie('foo', 'bar', 0, '/', '.myfoodomain.com', false, true);

        $this->assertTrue($cookie->isHttpOnly(), '->isHttpOnly() returns whether the cookie is only transmitted over HTTP');
    }

    public function testCookieIsNotCleared()
    {
        $cookie = new Cookie('foo', 'bar', time() + 3600 * 24);

        $this->assertFalse($cookie->isCleared(), '->isCleared() returns false if the cookie did not expire yet');
    }

    public function testCookieIsCleared()
    {
        $cookie = new Cookie('foo', 'bar', time() - 20);

        $this->assertTrue($cookie->isCleared(), '->isCleared() returns true if the cookie has expired');

        $cookie = new Cookie('foo', 'bar');

        $this->assertFalse($cookie->isCleared());

        $cookie = new Cookie('foo', 'bar', 0);

        $this->assertFalse($cookie->isCleared());

        $cookie = new Cookie('foo', 'bar', -1);

        $this->assertFalse($cookie->isCleared());
    }

    public function testToString()
    {
        $cookie = new Cookie('foo', 'bar', $expire = strtotime('Fri, 20-May-2011 15:25:52 GMT'), '/', '.myfoodomain.com', true);
        $this->assertEquals('foo=bar; expires=Fri, 20-May-2011 15:25:52 GMT; Max-Age=0; path=/; domain=.myfoodomain.com; secure; httponly', (string) $cookie, '->__toString() returns string representation of the cookie');

        $cookie = new Cookie('foo', 'bar with white spaces', strtotime('Fri, 20-May-2011 15:25:52 GMT'), '/', '.myfoodomain.com', true);
        $this->assertEquals('foo=bar%20with%20white%20spaces; expires=Fri, 20-May-2011 15:25:52 GMT; Max-Age=0; path=/; domain=.myfoodomain.com; secure; httponly', (string) $cookie, '->__toString() encodes the value of the cookie according to RFC 3986 (white space = %20)');

        $cookie = new Cookie('foo', null, 1, '/admin/', '.myfoodomain.com');
        $this->assertEquals('foo=deleted; expires='.gmdate('D, d-M-Y H:i:s T', $expire = time() - 31536001).'; Max-Age=0; path=/admin/; domain=.myfoodomain.com; httponly', (string) $cookie, '->__toString() returns string representation of a cleared cookie if value is NULL');

        $cookie = new Cookie('foo', 'bar', 0, '/', '');
        $this->assertEquals('foo=bar; path=/; httponly', (string) $cookie);
    }

    public function testRawCookie()
    {
        $cookie = new Cookie('foo', 'b a r', 0, '/', null, false, false);
        $this->assertFalse($cookie->isRaw());
        $this->assertEquals('foo=b%20a%20r; path=/', (string) $cookie);

        $cookie = new Cookie('foo', 'b+a+r', 0, '/', null, false, false, true);
        $this->assertTrue($cookie->isRaw());
        $this->assertEquals('foo=b+a+r; path=/', (string) $cookie);
    }

    public function testGetMaxAge()
    {
        $cookie = new Cookie('foo', 'bar');
        $this->assertEquals(0, $cookie->getMaxAge());

        $cookie = new Cookie('foo', 'bar', $expire = time() + 100);
        $this->assertEquals($expire - time(), $cookie->getMaxAge());

        $cookie = new Cookie('foo', 'bar', $expire = time() - 100);
        $this->assertEquals(0, $cookie->getMaxAge());
    }

    public function testFromString()
    {
        $cookie = Cookie::fromString('foo=bar; expires=Fri, 20-May-2011 15:25:52 GMT; path=/; domain=.myfoodomain.com; secure; httponly');
        $this->assertEquals(new Cookie('foo', 'bar', strtotime('Fri, 20-May-2011 15:25:52 GMT'), '/', '.myfoodomain.com', true, true, true), $cookie);

        $cookie = Cookie::fromString('foo=bar', true);
        $this->assertEquals(new Cookie('foo', 'bar', 0, '/', null, false, false), $cookie);
    }

    public function testFromStringWithHttpOnly()
    {
        $cookie = Cookie::fromString('foo=bar; expires=Fri, 20-May-2011 15:25:52 GMT; path=/; domain=.myfoodomain.com; secure; httponly');
        $this->assertTrue($cookie->isHttpOnly());

        $cookie = Cookie::fromString('foo=bar; expires=Fri, 20-May-2011 15:25:52 GMT; path=/; domain=.myfoodomain.com; secure');
        $this->assertFalse($cookie->isHttpOnly());
    }

    public function testSameSiteAttributeIsCaseInsensitive()
    {
        $cookie = new Cookie('foo', 'bar', 0, '/', null, false, true, false, 'Lax');
        $this->assertEquals('lax', $cookie->getSameSite());
    }
}
