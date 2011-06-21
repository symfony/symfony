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
     * @dataProvider testIpProvider
     */
    public function testIp($matches, $remoteAddr, $cidr)
    {
        $request = Request::create('', 'get', array(), array(), array(), array('REMOTE_ADDR' => $remoteAddr));

        $matcher = new RequestMatcher();
        $matcher->matchIp($cidr);

        $this->assertEquals($matches, $matcher->matches($request));
    }

    public function testIpProvider()
    {
        return array(
            array(true, '192.168.1.1', '192.168.1.1'),
            array(true, '192.168.1.1', '192.168.1.1/1'),
            array(true, '192.168.1.1', '192.168.1.0/24'),
            array(false, '192.168.1.1', '1.2.3.4/1'),
            array(false, '192.168.1.1', '192.168.1/33'),
            array(true, '2a01:198:603:0:396e:4789:8e99:890f', '2a01:198:603:0::/65'),
            array(false, '2a00:198:603:0:396e:4789:8e99:890f', '2a01:198:603:0::/65'),
        );
    }

    public function testMethod()
    {
        $matcher = new RequestMatcher();

        $matcher->matchMethod('get');
        $request = Request::create('', 'get');
        $this->assertTrue($matcher->matches($request));

        $matcher->matchMethod('post');
        $this->assertFalse($matcher->matches($request));

        $matcher->matchMethod(array('get', 'post'));
        $this->assertTrue($matcher->matches($request));
    }

    public function testHost()
    {
        $matcher = new RequestMatcher();

        $request = Request::create('', 'get', array(), array(), array(), array('HTTP_HOST' => 'foo.example.com'));

        $matcher->matchHost('.*\.example\.com');
        $this->assertTrue($matcher->matches($request));

        $matcher->matchHost('\.example\.com$');
        $this->assertTrue($matcher->matches($request));

        $matcher->matchHost('^.*\.example\.com$');
        $this->assertTrue($matcher->matches($request));

        $matcher->matchMethod('.*\.sensio\.com');
        $this->assertFalse($matcher->matches($request));
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

    public function testPathWithLocale()
    {
        $matcher = new RequestMatcher();
        $request = Request::create('/en/login');

        $session = new Session(new ArraySessionStorage());
        $session->setLocale('en');
        $request->setSession($session);

        $matcher->matchPath('^/{_locale}/login$');
        $this->assertTrue($matcher->matches($request));

        $session->setLocale('de');
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

