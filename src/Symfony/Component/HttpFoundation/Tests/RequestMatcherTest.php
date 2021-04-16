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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher;

class RequestMatcherTest extends TestCase
{
    /**
     * @dataProvider getMethodData
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

    public function getMethodData()
    {
        return [
            ['get', 'get', true],
            ['get', ['get', 'post'], true],
            ['get', 'post', false],
            ['get', 'GET', true],
            ['get', ['GET', 'POST'], true],
            ['get', 'POST', false],
        ];
    }

    public function testScheme()
    {
        $httpRequest = $request = $request = Request::create('');
        $httpsRequest = $request = $request = Request::create('', 'get', [], [], [], ['HTTPS' => 'on']);

        $matcher = new RequestMatcher();
        $matcher->matchScheme('https');
        $this->assertFalse($matcher->matches($httpRequest));
        $this->assertTrue($matcher->matches($httpsRequest));

        $matcher->matchScheme('http');
        $this->assertFalse($matcher->matches($httpsRequest));
        $this->assertTrue($matcher->matches($httpRequest));

        $matcher = new RequestMatcher();
        $this->assertTrue($matcher->matches($httpsRequest));
        $this->assertTrue($matcher->matches($httpRequest));
    }

    /**
     * @dataProvider getHostData
     */
    public function testHost($pattern, $isMatch)
    {
        $matcher = new RequestMatcher();
        $request = Request::create('', 'get', [], [], [], ['HTTP_HOST' => 'foo.example.com']);

        $matcher->matchHost($pattern);
        $this->assertSame($isMatch, $matcher->matches($request));

        $matcher = new RequestMatcher(null, $pattern);
        $this->assertSame($isMatch, $matcher->matches($request));
    }

    public function testPort()
    {
        $matcher = new RequestMatcher();
        $request = Request::create('', 'get', [], [], [], ['HTTP_HOST' => null, 'SERVER_PORT' => 8000]);

        $matcher->matchPort(8000);
        $this->assertTrue($matcher->matches($request));

        $matcher->matchPort(9000);
        $this->assertFalse($matcher->matches($request));

        $matcher = new RequestMatcher(null, null, null, null, [], null, 8000);
        $this->assertTrue($matcher->matches($request));
    }

    public function getHostData()
    {
        return [
            ['.*\.example\.com', true],
            ['\.example\.com$', true],
            ['^.*\.example\.com$', true],
            ['.*\.sensio\.com', false],
            ['.*\.example\.COM', true],
            ['\.example\.COM$', true],
            ['^.*\.example\.COM$', true],
            ['.*\.sensio\.COM', false],
        ];
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
        $request->setLocale('en');

        $matcher->matchPath('^/{_locale}/login$');
        $this->assertFalse($matcher->matches($request));
    }

    public function testPathWithEncodedCharacters()
    {
        $matcher = new RequestMatcher();
        $request = Request::create('/admin/fo%20o');
        $matcher->matchPath('^/admin/fo o*$');
        $this->assertTrue($matcher->matches($request));
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

    public function testAttributesWithClosure()
    {
        $matcher = new RequestMatcher();

        $request = Request::create('/admin/foo');
        $request->attributes->set('_controller', function () {
            return new Response('foo');
        });

        $matcher->matchAttribute('_controller', 'babar');
        $this->assertFalse($matcher->matches($request));
    }

    public function testIps()
    {
        $matcher = new RequestMatcher();

        $request = Request::create('', 'GET', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);

        $matcher->matchIp('127.0.0.1');
        $this->assertTrue($matcher->matches($request));

        $matcher->matchIp('192.168.0.1');
        $this->assertFalse($matcher->matches($request));

        $matcher->matchIps('127.0.0.1');
        $this->assertTrue($matcher->matches($request));

        $matcher->matchIps('127.0.0.1, ::1');
        $this->assertTrue($matcher->matches($request));

        $matcher->matchIps('192.168.0.1, ::1');
        $this->assertFalse($matcher->matches($request));

        $matcher->matchIps(['127.0.0.1', '::1']);
        $this->assertTrue($matcher->matches($request));

        $matcher->matchIps(['192.168.0.1', '::1']);
        $this->assertFalse($matcher->matches($request));

        $matcher->matchIps(['1.1.1.1', '2.2.2.2', '127.0.0.1, ::1']);
        $this->assertTrue($matcher->matches($request));

        $matcher->matchIps(['1.1.1.1', '2.2.2.2', '192.168.0.1, ::1']);
        $this->assertFalse($matcher->matches($request));
    }
}
