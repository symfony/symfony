<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\IpRetriever;

use Symfony\Component\HttpFoundation\IpRetriever\IpRetriever;
use Symfony\Component\HttpFoundation\Request;

class IpRetrieverTest extends \PHPUnit_Framework_TestCase
{
    private function getRequestInstanceForClientIpTests($remoteAddr, $httpForwardedFor)
    {
        $request = new Request();

        $server = array('REMOTE_ADDR' => $remoteAddr);
        if (null !== $httpForwardedFor) {
            $server['HTTP_X_FORWARDED_FOR'] = $httpForwardedFor;
        }

        $request->initialize(array(), array(), array(), array(), array(), $server);

        return $request;
    }

    private function getRequestInstanceForClientIpsForwardedTests($remoteAddr, $httpForwarded)
    {
        $request = new Request();

        $server = array('REMOTE_ADDR' => $remoteAddr);

        if (null !== $httpForwarded) {
            $server['HTTP_FORWARDED'] = $httpForwarded;
        }

        $request->initialize(array(), array(), array(), array(), array(), $server);

        return $request;
    }

    private function getRequestInstanceForClientIpsRealIpTests($remoteAddr, $httpRealIp)
    {
        $request = new Request();

        $server = array('REMOTE_ADDR' => $remoteAddr);

        if (null !== $httpRealIp) {
            $server['HTTP_X_REAL_IP'] = $httpRealIp;
        }

        $request->initialize(array(), array(), array(), array(), array(), $server);

        return $request;
    }

    /**
     * @dataProvider testGetClientIpsProvider
     */
    public function testGetClientIp($expected, $remoteAddr, $httpForwardedFor, $trustedProxies)
    {
        $ipRetriever = new IpRetriever();
        $request = $this->getRequestInstanceForClientIpTests($remoteAddr, $httpForwardedFor);
        if ($trustedProxies) {
            $ipRetriever->setTrustedProxies($trustedProxies);
        }

        $this->assertEquals($expected[0], $ipRetriever->getClientIp($request));
    }

    /**
     * @dataProvider testGetClientIpsProvider
     */
    public function testGetClientIps($expected, $remoteAddr, $httpForwardedFor, $trustedProxies)
    {
        $ipRetriever = new IpRetriever();
        $request = $this->getRequestInstanceForClientIpTests($remoteAddr, $httpForwardedFor);

        if ($trustedProxies) {
            $ipRetriever->setTrustedProxies($trustedProxies);
        }

        $this->assertEquals($expected, $ipRetriever->getClientIps($request));

        $ipRetriever->setTrustedProxies(array());
    }

    /**
     * @dataProvider testGetClientIpsForwardedProvider
     */
    public function testGetClientIpsForwarded($expected, $remoteAddr, $httpForwarded, $trustedProxies)
    {
        $ipRetriever = new IpRetriever();
        $request = $this->getRequestInstanceForClientIpsForwardedTests($remoteAddr, $httpForwarded);

        if ($trustedProxies) {
            $ipRetriever->setTrustedProxies($trustedProxies);
        }

        $this->assertEquals($expected, $ipRetriever->getClientIps($request));

        $ipRetriever->setTrustedProxies(array());
    }

    /**
     * @dataProvider testGetClientIpsRealIpProvider
     */
    public function testGetClientIpsRealIp($expected, $remoteAddr, $httpRealIp, $trustedProxies)
    {
        $ipRetriever = new IpRetriever();
        $request = $this->getRequestInstanceForClientIpsRealIpTests($remoteAddr, $httpRealIp);

        if ($trustedProxies) {
            $ipRetriever->setTrustedProxies($trustedProxies);
        }

        $this->assertEquals($expected, $ipRetriever->getClientIps($request));

        $ipRetriever->setTrustedProxies(array());
    }

    public function testGetClientIpsRealIpProvider()
    {
        //              $expected                                  $remoteAddr  $httpRealIp                                  $trustedProxies
        return array(
            array(array('88.88.88.88'),                            '127.0.0.1', '88.88.88.88',                               array('127.0.0.1')),
            array(array('192.0.2.60'),                             '::1',       '192.0.2.60',          array('::1')),
            array(array('2620:0:1cfe:face:b00c::3', '192.0.2.43'), '::1',       '192.0.2.43,2620:0:1cfe:face:b00c::3',       array('::1')),
            array(array('2001:db8:cafe::17'),                      '::1',       '2001:db8:cafe::17',                         array('::1')),
        );
    }

    public function testGetClientIpsForwardedProvider()
    {
        //              $expected                                  $remoteAddr  $httpForwarded                                       $trustedProxies
        return array(
            array(array('127.0.0.1'),                              '127.0.0.1', 'for="_gazonk"',                                      null),
            array(array('_gazonk'),                                '127.0.0.1', 'for="_gazonk"',                                      array('127.0.0.1')),
            array(array('88.88.88.88'),                            '127.0.0.1', 'for="88.88.88.88:80"',                               array('127.0.0.1')),
            array(array('192.0.2.60'),                             '::1',       'for=192.0.2.60;proto=http;by=203.0.113.43',          array('::1')),
            array(array('2620:0:1cfe:face:b00c::3', '192.0.2.43'), '::1',       'for=192.0.2.43, for=2620:0:1cfe:face:b00c::3',       array('::1')),
            array(array('2001:db8:cafe::17'),                      '::1',       'for="[2001:db8:cafe::17]:4711',                      array('::1')),
        );
    }

    public function testGetClientIpsProvider()
    {
        //        $expected                   $remoteAddr                $httpForwardedFor            $trustedProxies
        return array(
            // simple IPv4
            array(array('88.88.88.88'),              '88.88.88.88',              null,                        null),
            // trust the IPv4 remote addr
            array(array('88.88.88.88'),              '88.88.88.88',              null,                        array('88.88.88.88')),

            // simple IPv6
            array(array('::1'),                      '::1',                      null,                        null),
            // trust the IPv6 remote addr
            array(array('::1'),                      '::1',                      null,                        array('::1')),

            // forwarded for with remote IPv4 addr not trusted
            array(array('127.0.0.1'),                '127.0.0.1',                '88.88.88.88',               null),
            // forwarded for with remote IPv4 addr trusted
            array(array('88.88.88.88'),              '127.0.0.1',                '88.88.88.88',               array('127.0.0.1')),
            // forwarded for with remote IPv4 and all FF addrs trusted
            array(array('88.88.88.88'),              '127.0.0.1',                '88.88.88.88',               array('127.0.0.1', '88.88.88.88')),
            // forwarded for with remote IPv4 range trusted
            array(array('88.88.88.88'),              '123.45.67.89',             '88.88.88.88',               array('123.45.67.0/24')),

            // forwarded for with remote IPv6 addr not trusted
            array(array('1620:0:1cfe:face:b00c::3'), '1620:0:1cfe:face:b00c::3', '2620:0:1cfe:face:b00c::3',  null),
            // forwarded for with remote IPv6 addr trusted
            array(array('2620:0:1cfe:face:b00c::3'), '1620:0:1cfe:face:b00c::3', '2620:0:1cfe:face:b00c::3',  array('1620:0:1cfe:face:b00c::3')),
            // forwarded for with remote IPv6 range trusted
            array(array('88.88.88.88'),              '2a01:198:603:0:396e:4789:8e99:890f', '88.88.88.88',     array('2a01:198:603:0::/65')),

            // multiple forwarded for with remote IPv4 addr trusted
            array(array('88.88.88.88', '87.65.43.21', '127.0.0.1'), '123.45.67.89', '127.0.0.1, 87.65.43.21, 88.88.88.88', array('123.45.67.89')),
            // multiple forwarded for with remote IPv4 addr and some reverse proxies trusted
            array(array('87.65.43.21', '127.0.0.1'), '123.45.67.89',             '127.0.0.1, 87.65.43.21, 88.88.88.88', array('123.45.67.89', '88.88.88.88')),
            // multiple forwarded for with remote IPv4 addr and some reverse proxies trusted but in the middle
            array(array('88.88.88.88', '127.0.0.1'), '123.45.67.89',             '127.0.0.1, 87.65.43.21, 88.88.88.88', array('123.45.67.89', '87.65.43.21')),
            // multiple forwarded for with remote IPv4 addr and all reverse proxies trusted
            array(array('127.0.0.1'),                '123.45.67.89',             '127.0.0.1, 87.65.43.21, 88.88.88.88', array('123.45.67.89', '87.65.43.21', '88.88.88.88', '127.0.0.1')),

            // multiple forwarded for with remote IPv6 addr trusted
            array(array('2620:0:1cfe:face:b00c::3', '3620:0:1cfe:face:b00c::3'), '1620:0:1cfe:face:b00c::3', '3620:0:1cfe:face:b00c::3,2620:0:1cfe:face:b00c::3', array('1620:0:1cfe:face:b00c::3')),
            // multiple forwarded for with remote IPv6 addr and some reverse proxies trusted
            array(array('3620:0:1cfe:face:b00c::3'), '1620:0:1cfe:face:b00c::3', '3620:0:1cfe:face:b00c::3,2620:0:1cfe:face:b00c::3', array('1620:0:1cfe:face:b00c::3', '2620:0:1cfe:face:b00c::3')),
            // multiple forwarded for with remote IPv4 addr and some reverse proxies trusted but in the middle
            array(array('2620:0:1cfe:face:b00c::3', '4620:0:1cfe:face:b00c::3'), '1620:0:1cfe:face:b00c::3', '4620:0:1cfe:face:b00c::3,3620:0:1cfe:face:b00c::3,2620:0:1cfe:face:b00c::3', array('1620:0:1cfe:face:b00c::3', '3620:0:1cfe:face:b00c::3')),

            // client IP with port
            array(array('88.88.88.88'), '127.0.0.1', '88.88.88.88:12345, 127.0.0.1', array('127.0.0.1')),
        );
    }

    public function legacyTestTrustedProxies()
    {
        $ipRetriever = new IpRetriever();

        $request = Request::create('http://example.com/');
        $request->server->set('REMOTE_ADDR', '3.3.3.3');
        $request->headers->set('X_FORWARDED_FOR', '1.1.1.1, 2.2.2.2');
        $request->headers->set('X_FORWARDED_HOST', 'foo.example.com, real.example.com:8080');
        $request->headers->set('X_FORWARDED_PROTO', 'https');
        $request->headers->set('X_FORWARDED_PORT', 443);
        $request->headers->set('X_MY_FOR', '3.3.3.3, 4.4.4.4');
        $request->headers->set('X_MY_HOST', 'my.example.com');
        $request->headers->set('X_MY_PROTO', 'http');
        $request->headers->set('X_MY_PORT', 81);

        // no trusted proxies
        $this->assertEquals('3.3.3.3', $ipRetriever->getClientIp($request));
        $this->assertEquals('example.com', $request->getHost());
        $this->assertEquals(80, $request->getPort());
        $this->assertFalse($request->isSecure());

        // disabling proxy trusting
        $ipRetriever->setTrustedProxies(array());
        $this->assertEquals('3.3.3.3', $ipRetriever->getClientIp($request));
        $this->assertEquals('example.com', $request->getHost());
        $this->assertEquals(80, $request->getPort());
        $this->assertFalse($request->isSecure());

        // trusted proxy via setTrustedProxies()
        $ipRetriever->setTrustedProxies(array('3.3.3.3', '2.2.2.2'));
        $this->assertEquals('1.1.1.1', $ipRetriever->getClientIp($request));
        $this->assertEquals('real.example.com', $request->getHost());
        $this->assertEquals(443, $request->getPort());
        $this->assertTrue($request->isSecure());

        // check various X_FORWARDED_PROTO header values
        $request->headers->set('X_FORWARDED_PROTO', 'ssl');
        $this->assertTrue($request->isSecure());

        $request->headers->set('X_FORWARDED_PROTO', 'https, http');
        $this->assertTrue($request->isSecure());

        // custom header names
        $ipRetriever->setTrustedHeaderName($ipRetriever::HEADER_CLIENT_IP, 'X_MY_FOR');
        $ipRetriever->setTrustedHeaderName($ipRetriever::HEADER_CLIENT_HOST, 'X_MY_HOST');
        $ipRetriever->setTrustedHeaderName($ipRetriever::HEADER_CLIENT_PORT, 'X_MY_PORT');
        $ipRetriever->setTrustedHeaderName($ipRetriever::HEADER_CLIENT_PROTO, 'X_MY_PROTO');
        $this->assertEquals('4.4.4.4', $ipRetriever->getClientIp($request));
        $this->assertEquals('my.example.com', $request->getHost());
        $this->assertEquals(81, $request->getPort());
        $this->assertFalse($request->isSecure());

        // disabling via empty header names
        $ipRetriever->setTrustedHeaderName($ipRetriever::HEADER_CLIENT_IP, null);
        $ipRetriever->setTrustedHeaderName($ipRetriever::HEADER_CLIENT_HOST, null);
        $ipRetriever->setTrustedHeaderName($ipRetriever::HEADER_CLIENT_PORT, null);
        $ipRetriever->setTrustedHeaderName($ipRetriever::HEADER_CLIENT_PROTO, null);
        $this->assertEquals('3.3.3.3', $ipRetriever->getClientIp($request));
        $this->assertEquals('example.com', $request->getHost());
        $this->assertEquals(80, $request->getPort());
        $this->assertFalse($request->isSecure());

        // reset
        $ipRetriever->setTrustedProxies(array());
        $ipRetriever->setTrustedHeaderName($ipRetriever::HEADER_CLIENT_IP, 'X_FORWARDED_FOR');
        $ipRetriever->setTrustedHeaderName($ipRetriever::HEADER_CLIENT_HOST, 'X_FORWARDED_HOST');
        $ipRetriever->setTrustedHeaderName($ipRetriever::HEADER_CLIENT_PORT, 'X_FORWARDED_PORT');
        $ipRetriever->setTrustedHeaderName($ipRetriever::HEADER_CLIENT_PROTO, 'X_FORWARDED_PROTO');
    }
}