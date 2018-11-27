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
    public function invalidNames()
    {
        return array(
            array(''),
            array(',MyName'),
            array(';MyName'),
            array(' MyName'),
            array("\tMyName"),
            array("\rMyName"),
            array("\nMyName"),
            array("\013MyName"),
            array("\014MyName"),
        );
    }

    /**
     * @dataProvider invalidNames
     * @expectedException \InvalidArgumentException
     */
    public function testInstantiationThrowsExceptionIfCookieNameContainsInvalidCharacters($name)
    {
        Cookie::create($name);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidExpiration()
    {
        Cookie::create('MyCookie', 'foo', 'bar');
    }

    public function testNegativeExpirationIsNotPossible()
    {
        $cookie = Cookie::create('foo', 'bar', -100);

        $this->assertSame(0, $cookie->getExpiresTime());
    }

    public function testGetValue()
    {
        $value = 'MyValue';
        $cookie = Cookie::create('MyCookie', $value);

        $this->assertSame($value, $cookie->getValue(), '->getValue() returns the proper value');
    }

    public function testGetPath()
    {
        $cookie = Cookie::create('foo', 'bar');

        $this->assertSame('/', $cookie->getPath(), '->getPath() returns / as the default path');
    }

    public function testGetExpiresTime()
    {
        $cookie = Cookie::create('foo', 'bar');

        $this->assertEquals(0, $cookie->getExpiresTime(), '->getExpiresTime() returns the default expire date');

        $cookie = Cookie::create('foo', 'bar', $expire = time() + 3600);

        $this->assertEquals($expire, $cookie->getExpiresTime(), '->getExpiresTime() returns the expire date');
    }

    public function testGetExpiresTimeIsCastToInt()
    {
        $cookie = Cookie::create('foo', 'bar', 3600.9);

        $this->assertSame(3600, $cookie->getExpiresTime(), '->getExpiresTime() returns the expire date as an integer');
    }

    public function testConstructorWithDateTime()
    {
        $expire = new \DateTime();
        $cookie = Cookie::create('foo', 'bar', $expire);

        $this->assertEquals($expire->format('U'), $cookie->getExpiresTime(), '->getExpiresTime() returns the expire date');
    }

    public function testConstructorWithDateTimeImmutable()
    {
        $expire = new \DateTimeImmutable();
        $cookie = Cookie::create('foo', 'bar', $expire);

        $this->assertEquals($expire->format('U'), $cookie->getExpiresTime(), '->getExpiresTime() returns the expire date');
    }

    public function testGetExpiresTimeWithStringValue()
    {
        $value = '+1 day';
        $cookie = Cookie::create('foo', 'bar', $value);
        $expire = strtotime($value);

        $this->assertEquals($expire, $cookie->getExpiresTime(), '->getExpiresTime() returns the expire date', 1);
    }

    public function testGetDomain()
    {
        $cookie = Cookie::create('foo', 'bar', 0, '/', '.myfoodomain.com');

        $this->assertEquals('.myfoodomain.com', $cookie->getDomain(), '->getDomain() returns the domain name on which the cookie is valid');
    }

    public function testIsSecure()
    {
        $cookie = Cookie::create('foo', 'bar', 0, '/', '.myfoodomain.com', true);

        $this->assertTrue($cookie->isSecure(), '->isSecure() returns whether the cookie is transmitted over HTTPS');
    }

    public function testIsHttpOnly()
    {
        $cookie = Cookie::create('foo', 'bar', 0, '/', '.myfoodomain.com', false, true);

        $this->assertTrue($cookie->isHttpOnly(), '->isHttpOnly() returns whether the cookie is only transmitted over HTTP');
    }

    public function testCookieIsNotCleared()
    {
        $cookie = Cookie::create('foo', 'bar', time() + 3600 * 24);

        $this->assertFalse($cookie->isCleared(), '->isCleared() returns false if the cookie did not expire yet');
    }

    public function testCookieIsCleared()
    {
        $cookie = Cookie::create('foo', 'bar', time() - 20);

        $this->assertTrue($cookie->isCleared(), '->isCleared() returns true if the cookie has expired');

        $cookie = Cookie::create('foo', 'bar');

        $this->assertFalse($cookie->isCleared());

        $cookie = Cookie::create('foo', 'bar');

        $this->assertFalse($cookie->isCleared());

        $cookie = Cookie::create('foo', 'bar', -1);

        $this->assertFalse($cookie->isCleared());
    }

    public function testToString()
    {
        $cookie = Cookie::create('foo', 'bar', $expire = strtotime('Fri, 20-May-2011 15:25:52 GMT'), '/', '.myfoodomain.com', true, true, false, null);
        $this->assertEquals('foo=bar; expires=Fri, 20-May-2011 15:25:52 GMT; Max-Age=0; path=/; domain=.myfoodomain.com; secure; httponly', (string) $cookie, '->__toString() returns string representation of the cookie');

        $cookie = Cookie::create('foo', 'bar with white spaces', strtotime('Fri, 20-May-2011 15:25:52 GMT'), '/', '.myfoodomain.com', true, true, false, null);
        $this->assertEquals('foo=bar%20with%20white%20spaces; expires=Fri, 20-May-2011 15:25:52 GMT; Max-Age=0; path=/; domain=.myfoodomain.com; secure; httponly', (string) $cookie, '->__toString() encodes the value of the cookie according to RFC 3986 (white space = %20)');

        $cookie = Cookie::create('foo', null, 1, '/admin/', '.myfoodomain.com', false, true, false, null);
        $this->assertEquals('foo=deleted; expires='.gmdate('D, d-M-Y H:i:s T', $expire = time() - 31536001).'; Max-Age=0; path=/admin/; domain=.myfoodomain.com; httponly', (string) $cookie, '->__toString() returns string representation of a cleared cookie if value is NULL');

        $cookie = Cookie::create('foo', 'bar');
        $this->assertEquals('foo=bar; path=/; httponly; samesite=lax', (string) $cookie);
    }

    public function testRawCookie()
    {
        $cookie = Cookie::create('foo', 'b a r', 0, '/', null, false, false, false, null);
        $this->assertFalse($cookie->isRaw());
        $this->assertEquals('foo=b%20a%20r; path=/', (string) $cookie);

        $cookie = Cookie::create('foo', 'b+a+r', 0, '/', null, false, false, true, null);
        $this->assertTrue($cookie->isRaw());
        $this->assertEquals('foo=b+a+r; path=/', (string) $cookie);
    }

    public function testGetMaxAge()
    {
        $cookie = Cookie::create('foo', 'bar');
        $this->assertEquals(0, $cookie->getMaxAge());

        $cookie = Cookie::create('foo', 'bar', $expire = time() + 100);
        $this->assertEquals($expire - time(), $cookie->getMaxAge());

        $cookie = Cookie::create('foo', 'bar', $expire = time() - 100);
        $this->assertEquals(0, $cookie->getMaxAge());
    }

    public function testFromString()
    {
        $cookie = Cookie::fromString('foo=bar; expires=Fri, 20-May-2011 15:25:52 GMT; path=/; domain=.myfoodomain.com; secure; httponly');
        $this->assertEquals(Cookie::create('foo', 'bar', strtotime('Fri, 20-May-2011 15:25:52 GMT'), '/', '.myfoodomain.com', true, true, true, null), $cookie);

        $cookie = Cookie::fromString('foo=bar', true);
        $this->assertEquals(Cookie::create('foo', 'bar', 0, '/', null, false, false, false, null), $cookie);

        $cookie = Cookie::fromString('foo', true);
        $this->assertEquals(Cookie::create('foo', null, 0, '/', null, false, false, false, null), $cookie);
    }

    public function testFromStringWithHttpOnly()
    {
        $cookie = Cookie::fromString('foo=bar; expires=Fri, 20-May-2011 15:25:52 GMT; path=/; domain=.myfoodomain.com; secure; httponly');
        $this->assertTrue($cookie->isHttpOnly());

        $cookie = Cookie::fromString('foo=bar; expires=Fri, 20-May-2011 15:25:52 GMT; path=/; domain=.myfoodomain.com; secure');
        $this->assertFalse($cookie->isHttpOnly());
    }

    public function testSameSiteAttribute()
    {
        $cookie = new Cookie('foo', 'bar', 0, '/', null, false, true, false, 'Lax');
        $this->assertEquals('lax', $cookie->getSameSite());

        $cookie = new Cookie('foo', 'bar', 0, '/', null, false, true, false, '');
        $this->assertNull($cookie->getSameSite());
    }

    public function testSetSecureDefault()
    {
        $cookie = Cookie::create('foo', 'bar');

        $this->assertFalse($cookie->isSecure());

        $cookie->setSecureDefault(true);

        $this->assertTrue($cookie->isSecure());

        $cookie->setSecureDefault(false);

        $this->assertFalse($cookie->isSecure());
    }
}
