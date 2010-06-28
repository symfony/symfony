<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\BrowserKit;

use Symfony\Components\BrowserKit\Cookie;

class CookieTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getTestsForToFromString
     */
    public function testToFromString($cookie)
    {
        $this->assertEquals($cookie, (string) Cookie::fromString($cookie));
    }

    public function getTestsForToFromString()
    {
        return array(
            array('foo=bar'),
            array('foo=bar; expires=Fri, 31-Dec-2010 23:59:59 GMT'),
            array('foo=bar; path=/foo'),
            array('foo=bar; domain=google.com'),
            array('foo=bar; secure'),
            array('foo=bar; httponly'),
            array('foo=bar; domain=google.com; path=/foo; secure; httponly'),
        );
    }

    public function testFromStringWithUrl()
    {
        $this->assertEquals('foo=bar; domain=www.example.com', (string) Cookie::FromString('foo=bar', 'http://www.example.com/'));
        $this->assertEquals('foo=bar; domain=www.example.com; path=/foo', (string) Cookie::FromString('foo=bar', 'http://www.example.com/foo/bar'));
    }

    public function testFromStringThrowsAnExceptionIfCookieIsNotValid()
    {
        $this->setExpectedException('InvalidArgumentException');
        Cookie::FromString('foo');
    }

    public function testFromStringThrowsAnExceptionIfCookieDateIsNotValid()
    {
        $this->setExpectedException('InvalidArgumentException');
        Cookie::FromString('foo=bar; expires=foo');
    }

    public function testFromStringThrowsAnExceptionIfUrlIsNotValid()
    {
        $this->setExpectedException('InvalidArgumentException');
        Cookie::FromString('foo=bar', 'foobar');
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
        $this->assertFalse($cookie->isHttponly(), '->isHttponly() returns false if not defined');

        $cookie = new Cookie('foo', 'bar', 0, '/', 'foo.com', false, true);
        $this->assertTrue($cookie->isHttponly(), '->isHttponly() returns the cookie httponly flag');
    }

    public function testGetExpiresTime()
    {
        $cookie = new Cookie('foo', 'bar');
        $this->assertEquals(null, $cookie->getExpiresTime(), '->getExpiresTime() returns the expires time');

        $cookie = new Cookie('foo', 'bar', $time = time() - 86400);
        $this->assertEquals($time, $cookie->getExpiresTime(), '->getExpiresTime() returns the expires time');
    }

    public function testIsExpired()
    {
        $cookie = new Cookie('foo', 'bar');
        $this->assertFalse($cookie->isExpired(), '->isExpired() returns false when the cookie never expires (null as expires time)');

        $cookie = new Cookie('foo', 'bar', time() - 86400);
        $this->assertTrue($cookie->isExpired(), '->isExpired() returns true when the cookie is expired');
    }
}
