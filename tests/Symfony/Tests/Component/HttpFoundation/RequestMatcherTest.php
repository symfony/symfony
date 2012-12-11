<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpFoundation;

use Symfony\Component\HttpFoundation\SessionStorage\ArraySessionStorage;

use Symfony\Component\HttpFoundation\Session;

use Symfony\Component\HttpFoundation\RequestMatcher;
use Symfony\Component\HttpFoundation\Request;

class RequestMatcherTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider testIpv4Provider
     */
    public function testIpv4($matches, $remoteAddr, $cidr)
    {
        $request = Request::create('', 'get', array(), array(), array(), array('REMOTE_ADDR' => $remoteAddr));

        $matcher = new RequestMatcher();
        $matcher->matchIp($cidr);

        $this->assertEquals($matches, $matcher->matches($request));
    }

    public function testIpv4Provider()
    {
        return array(
            array(true, '192.168.1.1', '192.168.1.1'),
            array(true, '192.168.1.1', '192.168.1.1/1'),
            array(true, '192.168.1.1', '192.168.1.0/24'),
            array(false, '192.168.1.1', '1.2.3.4/1'),
            array(false, '192.168.1.1', '192.168.1/33'),
        );
    }

    /**
     * @dataProvider testIpv6Provider
     */
    public function testIpv6($matches, $remoteAddr, $cidr)
    {
        if (!defined('AF_INET6')) {
            $this->markTestSkipped('Only works when PHP is compiled without the option "disable-ipv6".');
        }

        $request = Request::create('', 'get', array(), array(), array(), array('REMOTE_ADDR' => $remoteAddr));

        $matcher = new RequestMatcher();
        $matcher->matchIp($cidr);

        $this->assertEquals($matches, $matcher->matches($request));
    }

    public function testIpv6Provider()
    {
        return array(
            array(true, '2a01:198:603:0:396e:4789:8e99:890f', '2a01:198:603:0::/65'),
            array(false, '2a00:198:603:0:396e:4789:8e99:890f', '2a01:198:603:0::/65'),
            array(false, '2a01:198:603:0:396e:4789:8e99:890f', '::1'),
            array(true, '0:0:0:0:0:0:0:1', '::1'),
            array(false, '0:0:603:0:396e:4789:8e99:0001', '::1'),
        );
    }

    public function testAnIpv6WithOptionDisabledIpv6()
    {
        if (defined('AF_INET6')) {
            $this->markTestSkipped('Only works when PHP is compiled with the option "disable-ipv6".');
        }

        $request = Request::create('', 'get', array(), array(), array(), array('REMOTE_ADDR' => '2a01:198:603:0:396e:4789:8e99:890f'));

        $matcher = new RequestMatcher();
        $matcher->matchIp('2a01:198:603:0::/65');

        try {
            $matcher->matches($request);

            $this->fail('An expected RuntimeException has not been raised.');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\RuntimeException', $e);
        }
    }

    /**
     * @dataProvider testMethodFixtures
     */
    public function testMethod($requestMethod, $matcherMethod, $isMatch)
    {
        $matcher = new RequestMatcher();
        $matcher->matchMethod($matcherMethod);
        $request = Request::create('', $requestMethod);
        $this->assertSame($isMatch, $matcher->matches($request));

        $matcher = new RequestMatcher(null, null, $matcherMethod);
        $request = Request::create('', $requestMethod);
        $this->assertSame($isMatch, $matcher->matches($request));
    }

    public function testMethodFixtures()
    {
        return array(
            array('get', 'get', true),
            array('get', array('get', 'post'), true),
            array('get', 'post', false),
            array('get', 'GET', true),
            array('get', array('GET', 'POST'), true),
            array('get', 'POST', false),
        );
    }

    /**
     * @dataProvider testHostFixture
     */
    public function testHost($pattern, $isMatch)
    {
        $matcher = new RequestMatcher();
        $request = Request::create('', 'get', array(), array(), array(), array('HTTP_HOST' => 'foo.example.com'));

        $matcher->matchHost($pattern);
        $this->assertSame($isMatch, $matcher->matches($request));

        $matcher= new RequestMatcher(null, $pattern);
        $this->assertSame($isMatch, $matcher->matches($request));
    }

    public function testHostFixture()
    {
        return array(
            array('.*\.example\.com', true),
            array('\.example\.com$', true),
            array('^.*\.example\.com$', true),
            array('.*\.sensio\.com', false),
            array('.*\.example\.COM', true),
            array('\.example\.COM$', true),
            array('^.*\.example\.COM$', true),
            array('.*\.sensio\.COM', false),        );
    }

    public function testPath()
    {
        $matcher = new RequestMatcher();

        $request = Request::create('/admin/foo');

        $matcher->matchPath('/admin/.*');
        $this->assertTrue($matcher->matches($request));

        $matcher->matchPath('/admin');
        $this->assertTrue($matcher->matches($request));

        $matcher->matchPath('^/admin/.*$');
        $this->assertTrue($matcher->matches($request));

        $matcher->matchMethod('/blog/.*');
        $this->assertFalse($matcher->matches($request));
    }

    public function testPathWithLocaleIsNotSupported()
    {
        $matcher = new RequestMatcher();
        $request = Request::create('/en/login');

        $session = new Session(new ArraySessionStorage());
        $session->setLocale('en');
        $request->setSession($session);

        $matcher->matchPath('^/{_locale}/login$');
        $this->assertFalse($matcher->matches($request));
    }

    public function testAttributes()
    {
        $matcher = new RequestMatcher();

        $request = Request::create('/admin/foo');
        $request->attributes->set('foo', 'foo_bar');

        $matcher->matchAttribute('foo', 'foo_.*');
        $this->assertTrue($matcher->matches($request));

        $matcher->matchAttribute('foo', 'foo');
        $this->assertTrue($matcher->matches($request));

        $matcher->matchAttribute('foo', '^foo_bar$');
        $this->assertTrue($matcher->matches($request));

        $matcher->matchAttribute('foo', 'babar');
        $this->assertFalse($matcher->matches($request));
    }
}
