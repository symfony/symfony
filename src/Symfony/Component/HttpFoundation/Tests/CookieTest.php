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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * CookieTest.
 *
 * @author John Kary <john@johnkary.net>
 * @author Hugo Hamon <hugo.hamon@sensio.com>
 */
#[Group('time-sensitive')]
class CookieTest extends TestCase
{
    public static function namesWithSpecialCharacters()
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

    #[DataProvider('namesWithSpecialCharacters')]
    public function testInstantiationThrowsExceptionIfRawCookieNameContainsSpecialCharacters($name)
    {
        $this->expectException(\InvalidArgumentException::class);
        Cookie::create($name, null, 0, null, null, null, false, true);
    }

    #[DataProvider('namesWithSpecialCharacters')]
    public function testWithRawThrowsExceptionIfCookieNameContainsSpecialCharacters($name)
    {
        $this->expectException(\InvalidArgumentException::class);
        Cookie::create($name)->withRaw();
    }

    #[DataProvider('namesWithSpecialCharacters')]
    public function testInstantiationSucceedNonRawCookieNameContainsSpecialCharacters($name)
    {
        $this->assertInstanceOf(Cookie::class, Cookie::create($name));
    }

    public function testParsingPreservesLegacyCookieData()
    {
        foreach (['a b', 'a,b', 'café'] as $value) {
            $this->assertSame($value, Cookie::fromString('name='.$value)->getValue());
        }

        $this->assertSame('a/b', Cookie::fromString('a/b=value')->getName());
        $this->assertSame('/café', Cookie::fromString('name=value; Path=/café')->getPath());
        $this->assertSame('éxample.com', Cookie::fromString('name=value; Domain=éxample.com')->getDomain());
    }

    public function testNonRawCookieNameEncodesNonTokenCharacters()
    {
        $cookie = Cookie::create('a/b[é]'."\0", 'value');

        $this->assertStringStartsWith('a%2Fb%5B%C3%A9%5D%00=value;', (string) $cookie);
    }

    public static function invalidRawNames()
    {
        return [['a/b'], ['a[b]'], ['a"b'], ['a\\b'], ["a\0b"], ["a\x7fb"], ['café']];
    }

    #[DataProvider('invalidRawNames')]
    public function testSerializationRejectsInvalidRawNames($name)
    {
        $this->expectException(\InvalidArgumentException::class);
        (string) Cookie::create($name, 'value', 0, '/', null, false, true, true);
    }

    #[DataProvider('invalidRawNames')]
    public function testSerializationRejectsInvalidNamesAfterWithRaw($name)
    {
        $this->expectException(\InvalidArgumentException::class);
        (string) Cookie::create($name, 'value')->withRaw();
    }

    public static function invalidRawValues()
    {
        return [['a;b'], ['a,b'], ['a b'], ['a\\b'], ['a"b'], ['"a'], ["a\0b"], ["a\x7fb"], ['café']];
    }

    #[DataProvider('invalidRawValues')]
    public function testSerializationRejectsInvalidRawValues($value)
    {
        $this->expectException(\InvalidArgumentException::class);
        (string) Cookie::create('name', $value, 0, '/', null, false, true, true);
    }

    #[DataProvider('invalidRawValues')]
    public function testSerializationRejectsInvalidValuesAfterWithRaw($value)
    {
        $this->expectException(\InvalidArgumentException::class);
        (string) Cookie::create('name', $value)->withRaw();
    }

    #[DataProvider('invalidRawValues')]
    public function testSerializationRejectsInvalidRawValuesAfterWithValue($value)
    {
        $this->expectException(\InvalidArgumentException::class);
        (string) Cookie::create('name', 'value', 0, '/', null, false, true, true)->withValue($value);
    }

    public function testRawCookieOctetsArePreserved()
    {
        foreach (["!#\$%&'()*+-./0123456789:<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[]^_`abcdefghijklmnopqrstuvwxyz{|}~", '"value"', '""'] as $value) {
            $this->assertStringStartsWith('name='.$value.';', (string) Cookie::create('name', $value, 0, '/', null, false, true, true));
        }
    }

    public static function invalidAttributeBytes()
    {
        return [["\0"], ["\x01"], ["\x08"], ["\x0e"], ["\x1f"], ["\x7f"], ['é']];
    }

    #[DataProvider('invalidAttributeBytes')]
    public function testSerializationRejectsInvalidPathBytes($byte)
    {
        $this->expectException(\InvalidArgumentException::class);
        (string) Cookie::create('name', 'value', 0, '/a'.$byte.'b');
    }

    #[DataProvider('invalidAttributeBytes')]
    public function testSerializationRejectsInvalidBytesAfterWithPath($byte)
    {
        $this->expectException(\InvalidArgumentException::class);
        (string) Cookie::create('name', 'value')->withPath('/a'.$byte.'b');
    }

    public static function invalidDomains()
    {
        return [['/'], ['/foo'], ['/foo=bar'], ['/foo%20bar'], ['example.com/'], ['example.com=value'], ['example..com'], ['-example.com'], ['example-.com'], ['éxample.com'], ["exam\0ple.com"], [str_repeat('a', 64).'.com'], [str_repeat(str_repeat('a', 63).'.', 3).str_repeat('a', 62)]];
    }

    #[DataProvider('invalidDomains')]
    public function testSerializationRejectsInvalidDomains($domain)
    {
        $this->expectException(\InvalidArgumentException::class);
        (string) Cookie::create('name', 'value', 0, '/', $domain);
    }

    #[DataProvider('invalidDomains')]
    public function testSerializationRejectsInvalidDomainsAfterWithDomain($domain)
    {
        $this->expectException(\InvalidArgumentException::class);
        (string) Cookie::create('name', 'value')->withDomain($domain);
    }

    public function testInstantiationThrowsExceptionIfCookieNameIsEmpty()
    {
        $this->expectException(\InvalidArgumentException::class);
        Cookie::create('');
    }

    public static function pathsAndDomainsWithSpecialCharacters()
    {
        return [
            ['/p,q'],
            ['/p;q'],
            ['/p q'],
            ["/p\tq"],
            ["/p\rq"],
            ["/p\nq"],
            ["/p\013q"],
            ["/p\014q"],
            ['/p; SameSite=None; Secure'],
            ['victim.com; secure'],
        ];
    }

    #[DataProvider('pathsAndDomainsWithSpecialCharacters')]
    public function testInstantiationThrowsExceptionIfPathContainsSpecialCharacters($path)
    {
        $this->expectException(\InvalidArgumentException::class);
        Cookie::create('MyCookie', 'MyValue', 0, $path);
    }

    #[DataProvider('pathsAndDomainsWithSpecialCharacters')]
    public function testInstantiationThrowsExceptionIfDomainContainsSpecialCharacters($domain)
    {
        $this->expectException(\InvalidArgumentException::class);
        Cookie::create('MyCookie', 'MyValue', 0, '/', $domain);
    }

    #[DataProvider('pathsAndDomainsWithSpecialCharacters')]
    public function testWithPathThrowsExceptionIfPathContainsSpecialCharacters($path)
    {
        $this->expectException(\InvalidArgumentException::class);
        Cookie::create('MyCookie')->withPath($path);
    }

    #[DataProvider('pathsAndDomainsWithSpecialCharacters')]
    public function testWithDomainThrowsExceptionIfDomainContainsSpecialCharacters($domain)
    {
        $this->expectException(\InvalidArgumentException::class);
        Cookie::create('MyCookie')->withDomain($domain);
    }

    public function testFromStringThrowsExceptionIfPathContainsSpecialCharacters()
    {
        $this->expectException(\InvalidArgumentException::class);
        Cookie::fromString('foo=bar; path="/p; SameSite=None"');
    }

    public static function validPathsAndDomains()
    {
        return [
            ['/', 'localhost'],
            ['/foo', '127.0.0.1'],
            ['/foo/bar', 'myfoodomain.com'],
            ['/foo=bar', '.myfoodomain.com'],
            ['/foo%20bar', 'xn--xample-9ua.com'],
            ['.myfoodomain.com', 'MyFooDomain.com'],
            ['myfoodomain.com', str_repeat(str_repeat('a', 63).'.', 3).str_repeat('a', 61)],
        ];
    }

    #[DataProvider('validPathsAndDomains')]
    public function testOrdinaryPathsAndDomainsAreAccepted($path, $domain)
    {
        $cookie = Cookie::create('MyCookie', 'MyValue', 0, $path, $domain);

        $this->assertSame($path, $cookie->getPath());
        $this->assertSame($domain, $cookie->getDomain());

        $cookie = Cookie::create('MyCookie')->withPath($path)->withDomain($domain);

        $this->assertSame($path, $cookie->getPath());
        $this->assertSame($domain, $cookie->getDomain());
    }

    public function testNullAndEmptyPathsAndDomainsAreAccepted()
    {
        $cookie = Cookie::create('MyCookie', 'MyValue', 0, null, null);

        $this->assertSame('/', $cookie->getPath());
        $this->assertNull($cookie->getDomain());

        $cookie = Cookie::create('MyCookie', 'MyValue', 0, '', null)->withDomain(null);

        $this->assertSame('/', $cookie->getPath());
        $this->assertNull($cookie->getDomain());
    }

    public function testInstantiationThrowsExceptionIfHostPrefixedNameHasDomain()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The cookie name "__Host-foo" uses the "__Host-" prefix, which requires the cookie to have no "domain" attribute.');
        Cookie::create('__Host-foo', 'bar', 0, '/', 'example.com');
    }

    public function testInstantiationThrowsExceptionIfHostPrefixedNameHasNonRootPath()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The cookie name "__Host-foo" uses the "__Host-" prefix, which requires the cookie path to be "/".');
        Cookie::create('__Host-foo', 'bar', 0, '/admin');
    }

    public function testWithDomainThrowsExceptionIfHostPrefixedNameGetsADomain()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The cookie name "__Host-foo" uses the "__Host-" prefix, which requires the cookie to have no "domain" attribute.');
        Cookie::create('__Host-foo', 'bar')->withDomain('example.com');
    }

    public function testWithPathThrowsExceptionIfHostPrefixedNameGetsANonRootPath()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The cookie name "__Host-foo" uses the "__Host-" prefix, which requires the cookie path to be "/".');
        Cookie::create('__Host-foo', 'bar')->withPath('/admin');
    }

    public function testHostPrefixedNameIsAcceptedWithoutDomainAndOnRootPath()
    {
        $cookie = Cookie::create('__Host-foo', 'bar');

        $this->assertNull($cookie->getDomain());
        $this->assertSame('/', $cookie->getPath());

        $cookie = Cookie::create('__Host-foo', 'bar', 0, '', '');

        $this->assertSame('', $cookie->getDomain());
        $this->assertSame('/', $cookie->getPath());

        $cookie = Cookie::create('__Host-foo', 'bar')->withDomain('')->withPath('');

        $this->assertSame('', $cookie->getDomain());
        $this->assertSame('/', $cookie->getPath());
    }

    public function testSecurePrefixedNameIsNotConstrainedToADomainOrAPath()
    {
        $cookie = Cookie::create('__Secure-foo', 'bar', 0, '/admin', 'example.com');

        $this->assertSame('example.com', $cookie->getDomain());
        $this->assertSame('/admin', $cookie->getPath());
    }

    public function testInstantiationThrowsExceptionIfPrefixedNameIsExplicitlyInsecure()
    {
        foreach (['__Secure-foo', '__Host-foo'] as $name) {
            try {
                Cookie::create($name, 'bar', 0, '/', null, false);
                $this->fail(\sprintf('Expected an exception for "%s".', $name));
            } catch (\InvalidArgumentException $e) {
                $this->assertSame(\sprintf('The cookie name "%s" uses a reserved prefix, which requires the "secure" flag to be enabled.', $name), $e->getMessage());
            }
        }
    }

    public function testWithSecureThrowsExceptionIfPrefixedNameIsMadeInsecure()
    {
        foreach (['__Secure-foo', '__Host-foo'] as $name) {
            try {
                Cookie::create($name, 'bar', 0, '/', null, true)->withSecure(false);
                $this->fail(\sprintf('Expected an exception for "%s".', $name));
            } catch (\InvalidArgumentException $e) {
                $this->assertSame(\sprintf('The cookie name "%s" uses a reserved prefix, which requires the "secure" flag to be enabled.', $name), $e->getMessage());
            }
        }
    }

    public function testPrefixedNameWithNullSecureIsAccepted()
    {
        foreach (['__Secure-foo', '__Host-foo'] as $name) {
            $cookie = Cookie::create($name, 'bar', 0, '/', null, null);

            $this->assertFalse($cookie->isSecure());

            $cookie->setSecureDefault(true);

            $this->assertTrue($cookie->isSecure());
        }
    }

    public function testHostPrefixedNameIsParsedFromAValidHeader()
    {
        $cookie = Cookie::fromString('__Host-foo=bar; path=/; secure');

        $this->assertSame('__Host-foo', $cookie->getName());
        $this->assertNull($cookie->getDomain());
        $this->assertSame('/', $cookie->getPath());
    }

    public function testParsingAHeaderWithoutSecureDefersInsteadOfFailing()
    {
        $cookie = Cookie::fromString('__Host-foo=bar');

        $this->assertFalse($cookie->isSecure());

        $cookie->setSecureDefault(true);

        $this->assertTrue($cookie->isSecure());
    }

    public function testInvalidExpiration()
    {
        $this->expectException(\InvalidArgumentException::class);
        Cookie::create('MyCookie', 'foo', 'bar');
    }

    public function testNegativeExpirationIsNotPossible()
    {
        $cookie = Cookie::create('foo', 'bar', -100);

        $this->assertSame(0, $cookie->getExpiresTime());

        $cookie = Cookie::create('foo', 'bar')->withExpires(-100);

        $this->assertSame(0, $cookie->getExpiresTime());
    }

    public function testMinimalParameters()
    {
        $constructedCookie = new Cookie('foo');

        $createdCookie = Cookie::create('foo');

        $cookie = new Cookie('foo', null, 0, '/', null, null, true, false, 'lax');

        $this->assertEquals($constructedCookie, $cookie);

        $this->assertEquals($createdCookie, $cookie);
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

        $cookie = Cookie::create('foo')->withExpires($expire = time() + 3600);

        $this->assertEquals($expire, $cookie->getExpiresTime(), '->getExpiresTime() returns the expire date');
    }

    public function testConstructorWithDateTime()
    {
        $expire = new \DateTime();
        $cookie = Cookie::create('foo', 'bar', $expire);

        $this->assertEquals($expire->getTimestamp(), $cookie->getExpiresTime(), '->getExpiresTime() returns the expire date');

        $cookie = Cookie::create('foo')->withExpires($expire);

        $this->assertEquals($expire->getTimestamp(), $cookie->getExpiresTime(), '->getExpiresTime() returns the expire date');
    }

    public function testConstructorWithDateTimeImmutable()
    {
        $expire = new \DateTimeImmutable();
        $cookie = Cookie::create('foo', 'bar', $expire);

        $this->assertEquals($expire->getTimestamp(), $cookie->getExpiresTime(), '->getExpiresTime() returns the expire date');

        $cookie = Cookie::create('foo')->withValue('bar')->withExpires($expire);

        $this->assertEquals($expire->getTimestamp(), $cookie->getExpiresTime(), '->getExpiresTime() returns the expire date');
    }

    public function testGetExpiresTimeWithStringValue()
    {
        $value = '+1 day';
        $cookie = Cookie::create('foo', 'bar', $value);
        $expire = strtotime($value);

        $this->assertEqualsWithDelta($expire, $cookie->getExpiresTime(), 1, '->getExpiresTime() returns the expire date');

        $cookie = Cookie::create('foo')->withValue('bar')->withExpires($value);

        $this->assertEqualsWithDelta($expire, $cookie->getExpiresTime(), 1, '->getExpiresTime() returns the expire date');
    }

    public function testGetDomain()
    {
        $cookie = Cookie::create('foo', 'bar', 0, '/', '.myfoodomain.com');

        $this->assertEquals('.myfoodomain.com', $cookie->getDomain(), '->getDomain() returns the domain name on which the cookie is valid');

        $cookie = Cookie::create('foo')->withDomain('.mybardomain.com');

        $this->assertEquals('.mybardomain.com', $cookie->getDomain(), '->getDomain() returns the domain name on which the cookie is valid');
    }

    public function testIsSecure()
    {
        $cookie = Cookie::create('foo', 'bar', 0, '/', '.myfoodomain.com', true);

        $this->assertTrue($cookie->isSecure(), '->isSecure() returns whether the cookie is transmitted over HTTPS');

        $cookie = Cookie::create('foo')->withSecure(true);

        $this->assertTrue($cookie->isSecure(), '->isSecure() returns whether the cookie is transmitted over HTTPS');
    }

    public function testIsHttpOnly()
    {
        $cookie = Cookie::create('foo', 'bar', 0, '/', '.myfoodomain.com', false, true);

        $this->assertTrue($cookie->isHttpOnly(), '->isHttpOnly() returns whether the cookie is only transmitted over HTTP');

        $cookie = Cookie::create('foo')->withHttpOnly(true);

        $this->assertTrue($cookie->isHttpOnly(), '->isHttpOnly() returns whether the cookie is only transmitted over HTTP');
    }

    public function testIsPartitioned()
    {
        $cookie = new Cookie('foo', 'bar', 0, '/', '.myfoodomain.com', true, true, false, 'Lax', true);

        $this->assertTrue($cookie->isPartitioned());

        $cookie = Cookie::create('foo')->withPartitioned(true);

        $this->assertTrue($cookie->isPartitioned());
    }

    public function testCookieIsNotCleared()
    {
        $cookie = Cookie::create('foo', 'bar', time() + 3600 * 24);

        $this->assertFalse($cookie->isCleared(), '->isCleared() returns false if the cookie did not expire yet');

        $cookie = Cookie::create('foo')->withExpires(time() + 3600 * 24);

        $this->assertFalse($cookie->isCleared(), '->isCleared() returns false if the cookie did not expire yet');
    }

    public function testCookieIsCleared()
    {
        $cookie = Cookie::create('foo', 'bar', time() - 20);

        $this->assertTrue($cookie->isCleared(), '->isCleared() returns true if the cookie has expired');

        $cookie = Cookie::create('foo')->withExpires(time() - 20);

        $this->assertTrue($cookie->isCleared(), '->isCleared() returns true if the cookie has expired');

        $cookie = Cookie::create('foo', 'bar');

        $this->assertFalse($cookie->isCleared());

        $cookie = Cookie::create('foo', 'bar');

        $this->assertFalse($cookie->isCleared());

        $cookie = Cookie::create('foo', 'bar', -1);

        $this->assertFalse($cookie->isCleared());

        $cookie = Cookie::create('foo')->withExpires(-1);

        $this->assertFalse($cookie->isCleared());
    }

    public function testToString()
    {
        $expected = 'foo=bar; expires=Fri, 20 May 2011 15:25:52 GMT; Max-Age=0; path=/; domain=.myfoodomain.com; secure; httponly';
        $cookie = Cookie::create('foo', 'bar', $expire = strtotime('Fri, 20 May 2011 15:25:52 GMT'), '/', '.myfoodomain.com', true, true, false, null);
        $this->assertEquals($expected, (string) $cookie, '->__toString() returns string representation of the cookie');

        $cookie = Cookie::create('foo')
            ->withValue('bar')
            ->withExpires(strtotime('Fri, 20 May 2011 15:25:52 GMT'))
            ->withDomain('.myfoodomain.com')
            ->withSecure(true)
            ->withSameSite(null);
        $this->assertEquals($expected, (string) $cookie, '->__toString() returns string representation of the cookie');

        $expected = 'foo=bar%20with%20white%20spaces; expires=Fri, 20 May 2011 15:25:52 GMT; Max-Age=0; path=/; domain=.myfoodomain.com; secure; httponly';
        $cookie = Cookie::create('foo', 'bar with white spaces', strtotime('Fri, 20 May 2011 15:25:52 GMT'), '/', '.myfoodomain.com', true, true, false, null);
        $this->assertEquals($expected, (string) $cookie, '->__toString() encodes the value of the cookie according to RFC 3986 (white space = %20)');

        $cookie = Cookie::create('foo')
            ->withValue('bar with white spaces')
            ->withExpires(strtotime('Fri, 20 May 2011 15:25:52 GMT'))
            ->withDomain('.myfoodomain.com')
            ->withSecure(true)
            ->withSameSite(null);
        $this->assertEquals($expected, (string) $cookie, '->__toString() encodes the value of the cookie according to RFC 3986 (white space = %20)');

        $expected = 'foo=deleted; expires='.gmdate('D, d M Y H:i:s T', $expire = time() - 31536001).'; Max-Age=0; path=/admin/; domain=.myfoodomain.com; httponly';
        $cookie = Cookie::create('foo', null, 1, '/admin/', '.myfoodomain.com', false, true, false, null);
        $this->assertEquals($expected, (string) $cookie, '->__toString() returns string representation of a cleared cookie if value is NULL');

        $cookie = Cookie::create('foo')
            ->withExpires(1)
            ->withPath('/admin/')
            ->withDomain('.myfoodomain.com')
            ->withSameSite(null);
        $this->assertEquals($expected, (string) $cookie, '->__toString() returns string representation of a cleared cookie if value is NULL');

        $expected = 'foo=deleted; expires='.gmdate('D, d M Y H:i:s T', $expire = time() - 31536001).'; Max-Age=0; path=/admin/; domain=.myfoodomain.com; secure; httponly; samesite=none; partitioned';
        $cookie = new Cookie('foo', null, 1, '/admin/', '.myfoodomain.com', true, true, false, 'none', true);
        $this->assertEquals($expected, (string) $cookie, '->__toString() returns string representation of a cleared cookie if value is NULL');

        $cookie = Cookie::create('foo')
            ->withExpires(1)
            ->withPath('/admin/')
            ->withDomain('.myfoodomain.com')
            ->withSecure(true)
            ->withHttpOnly(true)
            ->withSameSite('none')
            ->withPartitioned(true);
        $this->assertEquals($expected, (string) $cookie, '->__toString() returns string representation of a cleared cookie if value is NULL');

        $expected = 'foo=bar; path=/; httponly; samesite=lax';
        $cookie = Cookie::create('foo', 'bar');
        $this->assertEquals($expected, (string) $cookie);

        $cookie = Cookie::create('foo')->withValue('bar');
        $this->assertEquals($expected, (string) $cookie);
    }

    public function testRawCookie()
    {
        $cookie = Cookie::create('foo', 'b a r', 0, '/', null, false, false, false, null);
        $this->assertFalse($cookie->isRaw());
        $this->assertEquals('foo=b%20a%20r; path=/', (string) $cookie);

        $cookie = Cookie::create('test')->withValue('t e s t')->withHttpOnly(false)->withSameSite(null);
        $this->assertFalse($cookie->isRaw());
        $this->assertEquals('test=t%20e%20s%20t; path=/', (string) $cookie);

        $cookie = Cookie::create('foo', 'b+a+r', 0, '/', null, false, false, true, null);
        $this->assertTrue($cookie->isRaw());
        $this->assertEquals('foo=b+a+r; path=/', (string) $cookie);

        $cookie = Cookie::create('foo')
            ->withValue('t+e+s+t')
            ->withHttpOnly(false)
            ->withRaw(true)
            ->withSameSite(null);
        $this->assertTrue($cookie->isRaw());
        $this->assertEquals('foo=t+e+s+t; path=/', (string) $cookie);
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
        $cookie = Cookie::fromString('foo=bar; expires=Fri, 20 May 2011 15:25:52 GMT; path=/; domain=.myfoodomain.com; secure; httponly');
        $this->assertEquals(Cookie::create('foo', 'bar', strtotime('Fri, 20 May 2011 15:25:52 GMT'), '/', '.myfoodomain.com', true, true, true, null), $cookie);

        $cookie = Cookie::fromString('foo=bar', true);
        $this->assertEquals(Cookie::create('foo', 'bar', 0, '/', null, false, false, false, null), $cookie);

        $cookie = Cookie::fromString('foo=bar=', true);
        $this->assertEquals(Cookie::create('foo', 'bar=', 0, '/', null, false, false, false, null), $cookie);

        $cookie = Cookie::fromString('foo', true);
        $this->assertEquals(Cookie::create('foo', null, 0, '/', null, false, false, false, null), $cookie);

        $cookie = Cookie::fromString('foo_cookie=foo=1&bar=2&baz=3; expires=Tue, 22 Sep 2020 06:27:09 GMT; path=/');
        $this->assertEquals(Cookie::create('foo_cookie', 'foo=1&bar=2&baz=3', strtotime('Tue, 22 Sep 2020 06:27:09 GMT'), '/', null, false, false, true, null), $cookie);

        $cookie = Cookie::fromString('foo_cookie=foo==; expires=Tue, 22 Sep 2020 06:27:09 GMT; path=/');
        $this->assertEquals(Cookie::create('foo_cookie', 'foo==', strtotime('Tue, 22 Sep 2020 06:27:09 GMT'), '/', null, false, false, true, null), $cookie);

        $cookie = Cookie::fromString('foo_cookie=foo==; expires=Tue, 22 Sep 2020 06:27:09 GMT; path=/; secure; httponly; samesite=none; partitioned');
        $this->assertEquals(new Cookie('foo_cookie', 'foo==', strtotime('Tue, 22 Sep 2020 06:27:09 GMT'), '/', null, true, true, true, 'none', true), $cookie);
    }

    public function testFromStringWithHttpOnly()
    {
        $cookie = Cookie::fromString('foo=bar; expires=Fri, 20 May 2011 15:25:52 GMT; path=/; domain=.myfoodomain.com; secure; httponly');
        $this->assertTrue($cookie->isHttpOnly());

        $cookie = Cookie::fromString('foo=bar; expires=Fri, 20 May 2011 15:25:52 GMT; path=/; domain=.myfoodomain.com; secure');
        $this->assertFalse($cookie->isHttpOnly());
    }

    public function testSameSiteAttribute()
    {
        $cookie = new Cookie('foo', 'bar', 0, '/', null, false, true, false, 'Lax');
        $this->assertEquals('lax', $cookie->getSameSite());

        $cookie = new Cookie('foo', 'bar', 0, '/', null, false, true, false, '');
        $this->assertNull($cookie->getSameSite());

        $cookie = Cookie::create('foo')->withSameSite('Lax');
        $this->assertEquals('lax', $cookie->getSameSite());
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

    public function testMaxAge()
    {
        $futureDateOneHour = gmdate('D, d M Y H:i:s T', time() + 3600);

        $cookie = Cookie::fromString('foo=bar; Max-Age=3600; path=/');
        $this->assertEquals('foo=bar; expires='.$futureDateOneHour.'; Max-Age=3600; path=/', $cookie->__toString());

        $cookie = Cookie::fromString('foo=bar; expires='.$futureDateOneHour.'; Max-Age=3600; path=/');
        $this->assertEquals('foo=bar; expires='.$futureDateOneHour.'; Max-Age=3600; path=/', $cookie->__toString());

        $futureDateHalfHour = gmdate('D, d M Y H:i:s T', time() + 1800);

        // Max-Age value takes precedence before expires
        $cookie = Cookie::fromString('foo=bar; expires='.$futureDateHalfHour.'; Max-Age=3600; path=/');
        $this->assertEquals('foo=bar; expires='.$futureDateOneHour.'; Max-Age=3600; path=/', $cookie->__toString());
    }

    public function testExpiredWithMaxAge()
    {
        $cookie = Cookie::fromString('foo=bar; expires=Fri, 20 May 2011 15:25:52 GMT; Max-Age=0; path=/');
        $this->assertEquals('foo=bar; expires=Fri, 20 May 2011 15:25:52 GMT; Max-Age=0; path=/', $cookie->__toString());

        $futureDate = gmdate('D, d-M-Y H:i:s T', time() + 864000);

        $cookie = Cookie::fromString('foo=bar; expires='.$futureDate.'; Max-Age=0; path=/');
        $this->assertEquals(time(), $cookie->getExpiresTime());
        $this->assertEquals('foo=bar; expires='.gmdate('D, d M Y H:i:s T', $cookie->getExpiresTime()).'; Max-Age=0; path=/', $cookie->__toString());
    }
}
