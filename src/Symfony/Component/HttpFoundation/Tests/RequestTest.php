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


use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;

class RequestTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Symfony\Component\HttpFoundation\Request::__construct
     */
    public function testConstructor()
    {
        $this->testInitialize();
    }

    /**
     * @covers Symfony\Component\HttpFoundation\Request::initialize
     */
    public function testInitialize()
    {
        $request = new Request();

        $request->initialize(array('foo' => 'bar'));
        $this->assertEquals('bar', $request->query->get('foo'), '->initialize() takes an array of query parameters as its first argument');

        $request->initialize(array(), array('foo' => 'bar'));
        $this->assertEquals('bar', $request->request->get('foo'), '->initialize() takes an array of request parameters as its second argument');

        $request->initialize(array(), array(), array('foo' => 'bar'));
        $this->assertEquals('bar', $request->attributes->get('foo'), '->initialize() takes an array of attributes as its third argument');

        $request->initialize(array(), array(), array(), array(), array(), array('HTTP_FOO' => 'bar'));
        $this->assertEquals('bar', $request->headers->get('FOO'), '->initialize() takes an array of HTTP headers as its fourth argument');
    }

    /**
     * @covers Symfony\Component\HttpFoundation\Request::create
     */
    public function testCreate()
    {
        $request = Request::create('http://test.com/foo?bar=baz');
        $this->assertEquals('http://test.com/foo?bar=baz', $request->getUri());
        $this->assertEquals('/foo', $request->getPathInfo());
        $this->assertEquals('bar=baz', $request->getQueryString());
        $this->assertEquals(80, $request->getPort());
        $this->assertEquals('test.com', $request->getHttpHost());
        $this->assertFalse($request->isSecure());

        $request = Request::create('http://test.com/foo', 'GET', array('bar' => 'baz'));
        $this->assertEquals('http://test.com/foo?bar=baz', $request->getUri());
        $this->assertEquals('/foo', $request->getPathInfo());
        $this->assertEquals('bar=baz', $request->getQueryString());
        $this->assertEquals(80, $request->getPort());
        $this->assertEquals('test.com', $request->getHttpHost());
        $this->assertFalse($request->isSecure());

        $request = Request::create('http://test.com/foo?bar=foo', 'GET', array('bar' => 'baz'));
        $this->assertEquals('http://test.com/foo?bar=baz', $request->getUri());
        $this->assertEquals('/foo', $request->getPathInfo());
        $this->assertEquals('bar=baz', $request->getQueryString());
        $this->assertEquals(80, $request->getPort());
        $this->assertEquals('test.com', $request->getHttpHost());
        $this->assertFalse($request->isSecure());

        $request = Request::create('https://test.com/foo?bar=baz');
        $this->assertEquals('https://test.com/foo?bar=baz', $request->getUri());
        $this->assertEquals('/foo', $request->getPathInfo());
        $this->assertEquals('bar=baz', $request->getQueryString());
        $this->assertEquals(443, $request->getPort());
        $this->assertEquals('test.com', $request->getHttpHost());
        $this->assertTrue($request->isSecure());

        $request = Request::create('test.com:90/foo');
        $this->assertEquals('http://test.com:90/foo', $request->getUri());
        $this->assertEquals('/foo', $request->getPathInfo());
        $this->assertEquals('test.com', $request->getHost());
        $this->assertEquals('test.com:90', $request->getHttpHost());
        $this->assertEquals(90, $request->getPort());
        $this->assertFalse($request->isSecure());

        $request = Request::create('https://test.com:90/foo');
        $this->assertEquals('https://test.com:90/foo', $request->getUri());
        $this->assertEquals('/foo', $request->getPathInfo());
        $this->assertEquals('test.com', $request->getHost());
        $this->assertEquals('test.com:90', $request->getHttpHost());
        $this->assertEquals(90, $request->getPort());
        $this->assertTrue($request->isSecure());

        $request = Request::create('https://127.0.0.1:90/foo');
        $this->assertEquals('https://127.0.0.1:90/foo', $request->getUri());
        $this->assertEquals('/foo', $request->getPathInfo());
        $this->assertEquals('127.0.0.1', $request->getHost());
        $this->assertEquals('127.0.0.1:90', $request->getHttpHost());
        $this->assertEquals(90, $request->getPort());
        $this->assertTrue($request->isSecure());

        $request = Request::create('https://[::1]:90/foo');
        $this->assertEquals('https://[::1]:90/foo', $request->getUri());
        $this->assertEquals('/foo', $request->getPathInfo());
        $this->assertEquals('[::1]', $request->getHost());
        $this->assertEquals('[::1]:90', $request->getHttpHost());
        $this->assertEquals(90, $request->getPort());
        $this->assertTrue($request->isSecure());

        $json = '{"jsonrpc":"2.0","method":"echo","id":7,"params":["Hello World"]}';
        $request = Request::create('http://example.com/jsonrpc', 'POST', array(), array(), array(), array(), $json);
        $this->assertEquals($json, $request->getContent());
        $this->assertFalse($request->isSecure());

        $request = Request::create('http://test.com');
        $this->assertEquals('http://test.com/', $request->getUri());
        $this->assertEquals('/', $request->getPathInfo());
        $this->assertEquals('', $request->getQueryString());
        $this->assertEquals(80, $request->getPort());
        $this->assertEquals('test.com', $request->getHttpHost());
        $this->assertFalse($request->isSecure());

        $request = Request::create('http://test.com:90/?test=1');
        $this->assertEquals('http://test.com:90/?test=1', $request->getUri());
        $this->assertEquals('/', $request->getPathInfo());
        $this->assertEquals('test=1', $request->getQueryString());
        $this->assertEquals(90, $request->getPort());
        $this->assertEquals('test.com:90', $request->getHttpHost());
        $this->assertFalse($request->isSecure());

        $request = Request::create('http://test:test@test.com');
        $this->assertEquals('http://test:test@test.com/', $request->getUri());
        $this->assertEquals('/', $request->getPathInfo());
        $this->assertEquals('', $request->getQueryString());
        $this->assertEquals(80, $request->getPort());
        $this->assertEquals('test.com', $request->getHttpHost());
        $this->assertEquals('test', $request->getUser());
        $this->assertEquals('test', $request->getPassword());
        $this->assertFalse($request->isSecure());

        $request = Request::create('http://testnopass@test.com');
        $this->assertEquals('http://testnopass@test.com/', $request->getUri());
        $this->assertEquals('/', $request->getPathInfo());
        $this->assertEquals('', $request->getQueryString());
        $this->assertEquals(80, $request->getPort());
        $this->assertEquals('test.com', $request->getHttpHost());
        $this->assertEquals('testnopass', $request->getUser());
        $this->assertNull($request->getPassword());
        $this->assertFalse($request->isSecure());
    }

    /**
     * @covers Symfony\Component\HttpFoundation\Request::duplicate
     */
    public function testDuplicate()
    {
        $request = new Request(array('foo' => 'bar'), array('foo' => 'bar'), array('foo' => 'bar'), array(), array(), array('HTTP_FOO' => 'bar'));
        $dup = $request->duplicate();

        $this->assertEquals($request->query->all(), $dup->query->all(), '->duplicate() duplicates a request an copy the current query parameters');
        $this->assertEquals($request->request->all(), $dup->request->all(), '->duplicate() duplicates a request an copy the current request parameters');
        $this->assertEquals($request->attributes->all(), $dup->attributes->all(), '->duplicate() duplicates a request an copy the current attributes');
        $this->assertEquals($request->headers->all(), $dup->headers->all(), '->duplicate() duplicates a request an copy the current HTTP headers');

        $dup = $request->duplicate(array('foo' => 'foobar'), array('foo' => 'foobar'), array('foo' => 'foobar'), array(), array(), array('HTTP_FOO' => 'foobar'));

        $this->assertEquals(array('foo' => 'foobar'), $dup->query->all(), '->duplicate() overrides the query parameters if provided');
        $this->assertEquals(array('foo' => 'foobar'), $dup->request->all(), '->duplicate() overrides the request parameters if provided');
        $this->assertEquals(array('foo' => 'foobar'), $dup->attributes->all(), '->duplicate() overrides the attributes if provided');
        $this->assertEquals(array('foo' => array('foobar')), $dup->headers->all(), '->duplicate() overrides the HTTP header if provided');
    }

    /**
     * @covers Symfony\Component\HttpFoundation\Request::getFormat
     * @covers Symfony\Component\HttpFoundation\Request::setFormat
     * @dataProvider getFormatToMimeTypeMapProvider
     */
    public function testGetFormatFromMimeType($format, $mimeTypes)
    {
        $request = new Request();
        foreach ($mimeTypes as $mime) {
            $this->assertEquals($format, $request->getFormat($mime));
        }
        $request->setFormat($format, $mimeTypes);
        foreach ($mimeTypes as $mime) {
            $this->assertEquals($format, $request->getFormat($mime));
        }
    }

    /**
     * @covers Symfony\Component\HttpFoundation\Request::getFormat
     */
    public function testGetFormatFromMimeTypeWithParameters()
    {
        $request = new Request();
        $this->assertEquals('json', $request->getFormat('application/json; charset=utf-8'));
    }

    /**
     * @covers Symfony\Component\HttpFoundation\Request::getMimeType
     * @dataProvider getFormatToMimeTypeMapProvider
     */
    public function testGetMimeTypeFromFormat($format, $mimeTypes)
    {
        if (null !== $format) {
            $request = new Request();
            $this->assertEquals($mimeTypes[0], $request->getMimeType($format));
        }
    }

    public function getFormatToMimeTypeMapProvider()
    {
        return array(
            array(null, array(null, 'unexistent-mime-type')),
            array('txt', array('text/plain')),
            array('js', array('application/javascript', 'application/x-javascript', 'text/javascript')),
            array('css', array('text/css')),
            array('json', array('application/json', 'application/x-json')),
            array('xml', array('text/xml', 'application/xml', 'application/x-xml')),
            array('rdf', array('application/rdf+xml')),
            array('atom',array('application/atom+xml')),
        );
    }

    /**
     * @covers Symfony\Component\HttpFoundation\Request::getUri
     */
    public function testGetUri()
    {
        $server = array();

        // Standard Request on non default PORT
        // http://hostname:8080/index.php/path/info?query=string

        $server['HTTP_HOST'] = 'hostname:8080';
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '8080';

        $server['QUERY_STRING'] = 'query=string';
        $server['REQUEST_URI'] = '/index.php/path/info?query=string';
        $server['SCRIPT_NAME'] = '/index.php';
        $server['PATH_INFO'] = '/path/info';
        $server['PATH_TRANSLATED'] = 'redirect:/index.php/path/info';
        $server['PHP_SELF'] = '/index_dev.php/path/info';
        $server['SCRIPT_FILENAME'] = '/some/where/index.php';

        $request = new Request();

        $request->initialize(array(), array(), array(), array(), array(), $server);

        $this->assertEquals('http://hostname:8080/index.php/path/info?query=string', $request->getUri(), '->getUri() with non default port');

        // Use std port number
        $server['HTTP_HOST'] = 'hostname';
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '80';

        $request->initialize(array(), array(), array(), array(), array(), $server);

        $this->assertEquals('http://hostname/index.php/path/info?query=string', $request->getUri(), '->getUri() with default port');

        // Without HOST HEADER
        unset($server['HTTP_HOST']);
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '80';

        $request->initialize(array(), array(), array(), array(), array(), $server);

        $this->assertEquals('http://servername/index.php/path/info?query=string', $request->getUri(), '->getUri() with default port without HOST_HEADER');

        // Request with URL REWRITING (hide index.php)
        //   RewriteCond %{REQUEST_FILENAME} !-f
        //   RewriteRule ^(.*)$ index.php [QSA,L]
        // http://hostname:8080/path/info?query=string
        $server = array();
        $server['HTTP_HOST'] = 'hostname:8080';
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '8080';

        $server['REDIRECT_QUERY_STRING'] = 'query=string';
        $server['REDIRECT_URL'] = '/path/info';
        $server['SCRIPT_NAME'] = '/index.php';
        $server['QUERY_STRING'] = 'query=string';
        $server['REQUEST_URI'] = '/path/info?toto=test&1=1';
        $server['SCRIPT_NAME'] = '/index.php';
        $server['PHP_SELF'] = '/index.php';
        $server['SCRIPT_FILENAME'] = '/some/where/index.php';

        $request->initialize(array(), array(), array(), array(), array(), $server);
        $this->assertEquals('http://hostname:8080/path/info?query=string', $request->getUri(), '->getUri() with rewrite');

        // Use std port number
        //  http://hostname/path/info?query=string
        $server['HTTP_HOST'] = 'hostname';
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '80';

        $request->initialize(array(), array(), array(), array(), array(), $server);

        $this->assertEquals('http://hostname/path/info?query=string', $request->getUri(), '->getUri() with rewrite and default port');

        // Without HOST HEADER
        unset($server['HTTP_HOST']);
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '80';

        $request->initialize(array(), array(), array(), array(), array(), $server);

        $this->assertEquals('http://servername/path/info?query=string', $request->getUri(), '->getUri() with rewrite, default port without HOST_HEADER');

        // With encoded characters

        $server = array(
            'HTTP_HOST'       => 'hostname:8080',
            'SERVER_NAME'     => 'servername',
            'SERVER_PORT'     => '8080',
            'QUERY_STRING'    => 'query=string',
            'REQUEST_URI'     => '/ba%20se/index_dev.php/foo%20bar/in+fo?query=string',
            'SCRIPT_NAME'     => '/ba se/index_dev.php',
            'PATH_TRANSLATED' => 'redirect:/index.php/foo bar/in+fo',
            'PHP_SELF'        => '/ba se/index_dev.php/path/info',
            'SCRIPT_FILENAME' => '/some/where/ba se/index_dev.php',
        );

        $request->initialize(array(), array(), array(), array(), array(), $server);

        $this->assertEquals(
            'http://hostname:8080/ba%20se/index_dev.php/foo%20bar/in+fo?query=string',
            $request->getUri()
        );
   }

    /**
     * @covers Symfony\Component\HttpFoundation\Request::getUriForPath
     */
    public function testGetUriForPath()
    {
        $request = Request::create('http://test.com/foo?bar=baz');
        $this->assertEquals('http://test.com/some/path', $request->getUriForPath('/some/path'));

        $request = Request::create('http://test.com:90/foo?bar=baz');
        $this->assertEquals('http://test.com:90/some/path', $request->getUriForPath('/some/path'));

        $request = Request::create('https://test.com/foo?bar=baz');
        $this->assertEquals('https://test.com/some/path', $request->getUriForPath('/some/path'));

        $request = Request::create('https://test.com:90/foo?bar=baz');
        $this->assertEquals('https://test.com:90/some/path', $request->getUriForPath('/some/path'));

        $server = array();

        // Standard Request on non default PORT
        // http://hostname:8080/index.php/path/info?query=string

        $server['HTTP_HOST'] = 'hostname:8080';
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '8080';

        $server['QUERY_STRING'] = 'query=string';
        $server['REQUEST_URI'] = '/index.php/path/info?query=string';
        $server['SCRIPT_NAME'] = '/index.php';
        $server['PATH_INFO'] = '/path/info';
        $server['PATH_TRANSLATED'] = 'redirect:/index.php/path/info';
        $server['PHP_SELF'] = '/index_dev.php/path/info';
        $server['SCRIPT_FILENAME'] = '/some/where/index.php';

        $request = new Request();

        $request->initialize(array(), array(), array(), array(), array(),$server);

        $this->assertEquals('http://hostname:8080/index.php/some/path', $request->getUriForPath('/some/path'), '->getUriForPath() with non default port');

        // Use std port number
        $server['HTTP_HOST'] = 'hostname';
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '80';

        $request->initialize(array(), array(), array(), array(), array(), $server);

        $this->assertEquals('http://hostname/index.php/some/path', $request->getUriForPath('/some/path'), '->getUriForPath() with default port');

        // Without HOST HEADER
        unset($server['HTTP_HOST']);
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '80';

        $request->initialize(array(), array(), array(), array(), array(), $server);

        $this->assertEquals('http://servername/index.php/some/path', $request->getUriForPath('/some/path'), '->getUriForPath() with default port without HOST_HEADER');

        // Request with URL REWRITING (hide index.php)
        //   RewriteCond %{REQUEST_FILENAME} !-f
        //   RewriteRule ^(.*)$ index.php [QSA,L]
        // http://hostname:8080/path/info?query=string
        $server = array();
        $server['HTTP_HOST'] = 'hostname:8080';
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '8080';

        $server['REDIRECT_QUERY_STRING'] = 'query=string';
        $server['REDIRECT_URL'] = '/path/info';
        $server['SCRIPT_NAME'] = '/index.php';
        $server['QUERY_STRING'] = 'query=string';
        $server['REQUEST_URI'] = '/path/info?toto=test&1=1';
        $server['SCRIPT_NAME'] = '/index.php';
        $server['PHP_SELF'] = '/index.php';
        $server['SCRIPT_FILENAME'] = '/some/where/index.php';

        $request->initialize(array(), array(), array(), array(), array(), $server);
        $this->assertEquals('http://hostname:8080/some/path', $request->getUriForPath('/some/path'), '->getUri() with rewrite');

        // Use std port number
        //  http://hostname/path/info?query=string
        $server['HTTP_HOST'] = 'hostname';
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '80';

        $request->initialize(array(), array(), array(), array(), array(), $server);

        $this->assertEquals('http://hostname/some/path', $request->getUriForPath('/some/path'), '->getUriForPath() with rewrite and default port');

        // Without HOST HEADER
        unset($server['HTTP_HOST']);
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '80';

        $request->initialize(array(), array(), array(), array(), array(), $server);

        $this->assertEquals('http://servername/some/path', $request->getUriForPath('/some/path'), '->getUriForPath() with rewrite, default port without HOST_HEADER');
        $this->assertEquals('servername', $request->getHttpHost());
    }

    /**
     * @covers Symfony\Component\HttpFoundation\Request::getQueryString
     */
    public function testGetQueryString()
    {
        $request = new Request();

        $request->server->set('QUERY_STRING', 'foo');
        $this->assertEquals('foo', $request->getQueryString(), '->getQueryString() works with valueless parameters');

        $request->server->set('QUERY_STRING', 'foo=');
        $this->assertEquals('foo=', $request->getQueryString(), '->getQueryString() includes a dangling equal sign');

        $request->server->set('QUERY_STRING', 'bar=&foo=bar');
        $this->assertEquals('bar=&foo=bar', $request->getQueryString(), '->getQueryString() works when empty parameters');

        $request->server->set('QUERY_STRING', 'foo=bar&bar=');
        $this->assertEquals('bar=&foo=bar', $request->getQueryString(), '->getQueryString() sorts keys alphabetically');

        $request->server->set('QUERY_STRING', 'him=John%20Doe&her=Jane+Doe');
        $this->assertEquals('her=Jane%2BDoe&him=John%20Doe', $request->getQueryString(), '->getQueryString() normalizes encoding');

        $request->server->set('QUERY_STRING', 'foo[]=1&foo[]=2');
        $this->assertEquals('foo%5B%5D=1&foo%5B%5D=2', $request->getQueryString(), '->getQueryString() allows array notation');

        $request->server->set('QUERY_STRING', 'foo=1&foo=2');
        $this->assertEquals('foo=1&foo=2', $request->getQueryString(), '->getQueryString() allows repeated parameters');
    }

    /**
     * @covers Symfony\Component\HttpFoundation\Request::getHost
     */
    public function testGetHost()
    {
        $request = new Request();

        $request->initialize(array('foo' => 'bar'));
        $this->assertEquals('', $request->getHost(), '->getHost() return empty string if not initialized');

        $request->initialize(array(), array(), array(), array(), array(), array('HTTP_HOST' => 'www.exemple.com'));
        $this->assertEquals('www.exemple.com', $request->getHost(), '->getHost() from Host Header');

        // Host header with port number.
        $request->initialize(array(), array(), array(), array(), array(), array('HTTP_HOST' => 'www.exemple.com:8080'));
        $this->assertEquals('www.exemple.com', $request->getHost(), '->getHost() from Host Header with port number');

        // Server values.
        $request->initialize(array(), array(), array(), array(), array(), array('SERVER_NAME' => 'www.exemple.com'));
        $this->assertEquals('www.exemple.com', $request->getHost(), '->getHost() from server name');

        $this->startTrustingProxyData();
        // X_FORWARDED_HOST.
        $request->initialize(array(), array(), array(), array(), array(), array('HTTP_X_FORWARDED_HOST' => 'www.exemple.com'));
        $this->assertEquals('www.exemple.com', $request->getHost(), '->getHost() from X_FORWARDED_HOST');

        // X_FORWARDED_HOST
        $request->initialize(array(), array(), array(), array(), array(), array('HTTP_X_FORWARDED_HOST' => 'www.exemple.com, www.second.com'));
        $this->assertEquals('www.second.com', $request->getHost(), '->getHost() value from X_FORWARDED_HOST use last value');

        // X_FORWARDED_HOST with port number
        $request->initialize(array(), array(), array(), array(), array(), array('HTTP_X_FORWARDED_HOST' => 'www.exemple.com, www.second.com:8080'));
        $this->assertEquals('www.second.com', $request->getHost(), '->getHost() value from X_FORWARDED_HOST with port number');

        $request->initialize(array(), array(), array(), array(), array(), array('HTTP_HOST' => 'www.exemple.com', 'HTTP_X_FORWARDED_HOST' => 'www.forward.com'));
        $this->assertEquals('www.forward.com', $request->getHost(), '->getHost() value from X_FORWARDED_HOST has priority over Host');

        $request->initialize(array(), array(), array(), array(), array(), array('SERVER_NAME' => 'www.exemple.com', 'HTTP_X_FORWARDED_HOST' => 'www.forward.com'));
        $this->assertEquals('www.forward.com', $request->getHost(), '->getHost() value from X_FORWARDED_HOST has priority over SERVER_NAME ');

        $request->initialize(array(), array(), array(), array(), array(), array('SERVER_NAME' => 'www.exemple.com', 'HTTP_HOST' => 'www.host.com'));
        $this->assertEquals('www.host.com', $request->getHost(), '->getHost() value from Host header has priority over SERVER_NAME ');
        $this->stopTrustingProxyData();
    }

    /**
     * @covers Symfony\Component\HttpFoundation\Request::setMethod
     * @covers Symfony\Component\HttpFoundation\Request::getMethod
     */
    public function testGetSetMethod()
    {
        $request = new Request();

        $this->assertEquals('GET', $request->getMethod(), '->getMethod() returns GET if no method is defined');

        $request->setMethod('get');
        $this->assertEquals('GET', $request->getMethod(), '->getMethod() returns an uppercased string');

        $request->setMethod('PURGE');
        $this->assertEquals('PURGE', $request->getMethod(), '->getMethod() returns the method even if it is not a standard one');

        $request->setMethod('POST');
        $this->assertEquals('POST', $request->getMethod(), '->getMethod() returns the method POST if no _method is defined');

        $request->setMethod('POST');
        $request->request->set('_method', 'purge');
        $this->assertEquals('PURGE', $request->getMethod(), '->getMethod() returns the method from _method if defined and POST');

        $request->setMethod('POST');
        $request->headers->set('X-HTTP-METHOD-OVERRIDE', 'delete');
        $this->assertEquals('DELETE', $request->getMethod(), '->getMethod() returns the method from X-HTTP-Method-Override even though _method is set if defined and POST');

        $request = new Request();
        $request->setMethod('POST');
        $request->headers->set('X-HTTP-METHOD-OVERRIDE', 'delete');
        $this->assertEquals('DELETE', $request->getMethod(), '->getMethod() returns the method from X-HTTP-Method-Override if defined and POST');
    }

    /**
     * @dataProvider testGetClientIpProvider
     */
    public function testGetClientIp($expected, $proxy, $remoteAddr, $httpClientIp, $httpForwardedFor)
    {
        $request = new Request();
        $this->assertEquals('', $request->getClientIp());
        $this->assertEquals('', $request->getClientIp(true));

        $server = array('REMOTE_ADDR' => $remoteAddr);
        if (null !== $httpClientIp) {
            $server['HTTP_CLIENT_IP'] = $httpClientIp;
        }
        if (null !== $httpForwardedFor) {
            $server['HTTP_X_FORWARDED_FOR'] = $httpForwardedFor;
        }

        $request->initialize(array(), array(), array(), array(), array(), $server);
        if ($proxy) {
            $this->startTrustingProxyData();
        }
        $this->assertEquals($expected, $request->getClientIp($proxy));
        if ($proxy) {
            $this->stopTrustingProxyData();
        }
    }

    public function testGetClientIpProvider()
    {
        return array(
            array('88.88.88.88', false, '88.88.88.88', null, null),
            array('127.0.0.1', false, '127.0.0.1', '88.88.88.88', null),
            array('88.88.88.88', true, '127.0.0.1', '88.88.88.88', null),
            array('127.0.0.1', false, '127.0.0.1', null, '88.88.88.88'),
            array('88.88.88.88', true, '127.0.0.1', null, '88.88.88.88'),
            array('::1', false, '::1', null, null),
            array('2620:0:1cfe:face:b00c::3', true, '::1', '2620:0:1cfe:face:b00c::3', null),
            array('2620:0:1cfe:face:b00c::3', true, '::1', null, '2620:0:1cfe:face:b00c::3, ::1'),
            array('88.88.88.88', true, '123.45.67.89', null, '88.88.88.88, 87.65.43.21, 127.0.0.1'),
        );
    }

    public function testGetContentWorksTwiceInDefaultMode()
    {
        $req = new Request;
        $this->assertEquals('', $req->getContent());
        $this->assertEquals('', $req->getContent());
    }

    public function testGetContentReturnsResource()
    {
        $req = new Request;
        $retval = $req->getContent(true);
        $this->assertInternalType('resource', $retval);
        $this->assertEquals("", fread($retval, 1));
        $this->assertTrue(feof($retval));
    }

    /**
     * @expectedException LogicException
     * @dataProvider getContentCantBeCalledTwiceWithResourcesProvider
     */
    public function testGetContentCantBeCalledTwiceWithResources($first, $second)
    {
        $req = new Request;
        $req->getContent($first);
        $req->getContent($second);
    }

    public function getContentCantBeCalledTwiceWithResourcesProvider()
    {
        return array(
            'Resource then fetch' => array(true, false),
            'Resource then resource' => array(true, true),
            'Fetch then resource' => array(false, true),
        );
    }

    public function provideOverloadedMethods()
    {
        return array(
            array('PUT'),
            array('DELETE'),
            array('PATCH'),
        );
    }

    /**
     * @dataProvider provideOverloadedMethods
     */
    public function testCreateFromGlobals($method)
    {
        $_GET['foo1']    = 'bar1';
        $_POST['foo2']   = 'bar2';
        $_COOKIE['foo3'] = 'bar3';
        $_FILES['foo4']  = array('bar4');
        $_SERVER['foo5'] = 'bar5';

        $request = Request::createFromGlobals();
        $this->assertEquals('bar1', $request->query->get('foo1'), '::fromGlobals() uses values from $_GET');
        $this->assertEquals('bar2', $request->request->get('foo2'), '::fromGlobals() uses values from $_POST');
        $this->assertEquals('bar3', $request->cookies->get('foo3'), '::fromGlobals() uses values from $_COOKIE');
        $this->assertEquals(array('bar4'), $request->files->get('foo4'), '::fromGlobals() uses values from $_FILES');
        $this->assertEquals('bar5', $request->server->get('foo5'), '::fromGlobals() uses values from $_SERVER');

        unset($_GET['foo1'], $_POST['foo2'], $_COOKIE['foo3'], $_FILES['foo4'], $_SERVER['foo5']);

        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $request = RequestContentProxy::createFromGlobals();
        $this->assertEquals($method, $request->getMethod());
        $this->assertEquals('mycontent', $request->request->get('content'));

        unset($_SERVER['REQUEST_METHOD'], $_SERVER['CONTENT_TYPE']);

        $_POST['_method']   = $method;
        $_POST['foo6']      = 'bar6';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $request = Request::createFromGlobals();
        $this->assertEquals($method, $request->getMethod());
        $this->assertEquals('bar6', $request->request->get('foo6'));

        unset($_POST['_method'], $_POST['foo6'], $_SERVER['REQUEST_METHOD']);
    }

    public function testOverrideGlobals()
    {
        $request = new Request();
        $request->initialize(array('foo' => 'bar'));

        // as the Request::overrideGlobals really work, it erase $_SERVER, so we must backup it
        $server = $_SERVER;

        $request->overrideGlobals();

        $this->assertEquals(array('foo' => 'bar'), $_GET);

        $request->initialize(array(), array('foo' => 'bar'));
        $request->overrideGlobals();

        $this->assertEquals(array('foo' => 'bar'), $_POST);

        $this->assertArrayNotHasKey('HTTP_X_FORWARDED_PROTO', $_SERVER);

        $this->startTrustingProxyData();
        $request->headers->set('X_FORWARDED_PROTO', 'https');

        $this->assertTrue($request->isSecure());
        $this->stopTrustingProxyData();

        $request->overrideGlobals();

        $this->assertArrayHasKey('HTTP_X_FORWARDED_PROTO', $_SERVER);

        // restore initial $_SERVER array
        $_SERVER = $server;
    }

    public function testGetScriptName()
    {
        $request = new Request();
        $this->assertEquals('', $request->getScriptName());

        $server = array();
        $server['SCRIPT_NAME'] = '/index.php';

        $request->initialize(array(), array(), array(), array(), array(), $server);

        $this->assertEquals('/index.php', $request->getScriptName());

        $server = array();
        $server['ORIG_SCRIPT_NAME'] = '/frontend.php';
        $request->initialize(array(), array(), array(), array(), array(), $server);

        $this->assertEquals('/frontend.php', $request->getScriptName());

        $server = array();
        $server['SCRIPT_NAME'] = '/index.php';
        $server['ORIG_SCRIPT_NAME'] = '/frontend.php';
        $request->initialize(array(), array(), array(), array(), array(), $server);

        $this->assertEquals('/index.php', $request->getScriptName());
    }

    public function testGetBasePath()
    {
        $request = new Request();
        $this->assertEquals('', $request->getBasePath());

        $server = array();
        $server['SCRIPT_FILENAME'] = '/some/where/index.php';
        $request->initialize(array(), array(), array(), array(), array(), $server);
        $this->assertEquals('', $request->getBasePath());

        $server = array();
        $server['SCRIPT_FILENAME'] = '/some/where/index.php';
        $server['SCRIPT_NAME'] = '/index.php';
        $request->initialize(array(), array(), array(), array(), array(), $server);

        $this->assertEquals('', $request->getBasePath());

        $server = array();
        $server['SCRIPT_FILENAME'] = '/some/where/index.php';
        $server['PHP_SELF'] = '/index.php';
        $request->initialize(array(), array(), array(), array(), array(), $server);

        $this->assertEquals('', $request->getBasePath());

        $server = array();
        $server['SCRIPT_FILENAME'] = '/some/where/index.php';
        $server['ORIG_SCRIPT_NAME'] = '/index.php';
        $request->initialize(array(), array(), array(), array(), array(), $server);

        $this->assertEquals('', $request->getBasePath());
    }

    public function testGetPathInfo()
    {
        $request = new Request();
        $this->assertEquals('/', $request->getPathInfo());

        $server = array();
        $server['REQUEST_URI'] = '/path/info';
        $request->initialize(array(), array(), array(), array(), array(), $server);

        $this->assertEquals('/path/info', $request->getPathInfo());

        $server = array();
        $server['REQUEST_URI'] = '/path%20test/info';
        $request->initialize(array(), array(), array(), array(), array(), $server);

        $this->assertEquals('/path%20test/info', $request->getPathInfo());
    }

    public function testGetPreferredLanguage()
    {
        $request = new Request();
        $this->assertNull($request->getPreferredLanguage());
        $this->assertNull($request->getPreferredLanguage(array()));
        $this->assertEquals('fr', $request->getPreferredLanguage(array('fr')));
        $this->assertEquals('fr', $request->getPreferredLanguage(array('fr', 'en')));
        $this->assertEquals('en', $request->getPreferredLanguage(array('en', 'fr')));
        $this->assertEquals('fr-ch', $request->getPreferredLanguage(array('fr-ch', 'fr-fr')));

        $request = new Request();
        $request->headers->set('Accept-language', 'zh, en-us; q=0.8, en; q=0.6');
        $this->assertEquals('en', $request->getPreferredLanguage(array('en', 'en-us')));

        $request = new Request();
        $request->headers->set('Accept-language', 'zh, en-us; q=0.8, en; q=0.6');
        $this->assertEquals('en', $request->getPreferredLanguage(array('fr', 'en')));
    }

    public function testIsXmlHttpRequest()
    {
        $request = new Request();
        $this->assertFalse($request->isXmlHttpRequest());

        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $this->assertTrue($request->isXmlHttpRequest());

        $request->headers->remove('X-Requested-With');
        $this->assertFalse($request->isXmlHttpRequest());
    }

    public function testGetCharsets()
    {
        $request = new Request();
        $this->assertEquals(array(), $request->getCharsets());
        $request->headers->set('Accept-Charset', 'ISO-8859-1, US-ASCII, UTF-8; q=0.8, ISO-10646-UCS-2; q=0.6');
        $this->assertEquals(array(), $request->getCharsets()); // testing caching

        $request = new Request();
        $request->headers->set('Accept-Charset', 'ISO-8859-1, US-ASCII, UTF-8; q=0.8, ISO-10646-UCS-2; q=0.6');
        $this->assertEquals(array('ISO-8859-1', 'US-ASCII', 'UTF-8', 'ISO-10646-UCS-2'), $request->getCharsets());

        $request = new Request();
        $request->headers->set('Accept-Charset', 'ISO-8859-1,utf-8;q=0.7,*;q=0.7');
        $this->assertEquals(array('ISO-8859-1', '*', 'utf-8'), $request->getCharsets());
    }

    public function testGetAcceptableContentTypes()
    {
        $request = new Request();
        $this->assertEquals(array(), $request->getAcceptableContentTypes());
        $request->headers->set('Accept', 'application/vnd.wap.wmlscriptc, text/vnd.wap.wml, application/vnd.wap.xhtml+xml, application/xhtml+xml, text/html, multipart/mixed, */*');
        $this->assertEquals(array(), $request->getAcceptableContentTypes()); // testing caching

        $request = new Request();
        $request->headers->set('Accept', 'application/vnd.wap.wmlscriptc, text/vnd.wap.wml, application/vnd.wap.xhtml+xml, application/xhtml+xml, text/html, multipart/mixed, */*');
        $this->assertEquals(array('multipart/mixed', '*/*', 'text/html', 'application/xhtml+xml', 'text/vnd.wap.wml', 'application/vnd.wap.xhtml+xml', 'application/vnd.wap.wmlscriptc'), $request->getAcceptableContentTypes());
    }

    public function testGetLanguages()
    {
        $request = new Request();
        $this->assertEquals(array(), $request->getLanguages());

        $request = new Request();
        $request->headers->set('Accept-language', 'zh, en-us; q=0.8, en; q=0.6');
        $this->assertEquals(array('zh', 'en_US', 'en'), $request->getLanguages());
        $this->assertEquals(array('zh', 'en_US', 'en'), $request->getLanguages());

        $request = new Request();
        $request->headers->set('Accept-language', 'zh, i-cherokee; q=0.6');
        $this->assertEquals(array('zh', 'cherokee'), $request->getLanguages());
    }

    public function testGetRequestFormat()
    {
        $request = new Request();
        $this->assertEquals('html', $request->getRequestFormat());

        $request = new Request();
        $this->assertNull($request->getRequestFormat(null));

        $request = new Request();
        $this->assertNull($request->setRequestFormat('foo'));
        $this->assertEquals('foo', $request->getRequestFormat(null));
    }

    public function testForwardedSecure()
    {
        $request = new Request();
        $request->headers->set('X-Forwarded-Proto', 'https');
        $request->headers->set('X-Forwarded-Port', 443);

        $this->startTrustingProxyData();
        $this->assertTrue($request->isSecure());
        $this->assertEquals(443, $request->getPort());
        $this->stopTrustingProxyData();
    }

    public function testHasSession()
    {
        $request = new Request();

        $this->assertFalse($request->hasSession());
        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->assertTrue($request->hasSession());
    }

    public function testHasPreviousSession()
    {
        $request = new Request();

        $this->assertFalse($request->hasPreviousSession());
        $request->cookies->set('MOCKSESSID', 'foo');
        $this->assertFalse($request->hasPreviousSession());
        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->assertTrue($request->hasPreviousSession());
    }

    public function testToString()
    {
        $request = new Request();

        $request->headers->set('Accept-language', 'zh, en-us; q=0.8, en; q=0.6');

        $this->assertContains('Accept-Language: zh, en-us; q=0.8, en; q=0.6', $request->__toString());
    }

    /**
     * @dataProvider splitHttpAcceptHeaderData
     */
    public function testSplitHttpAcceptHeader($acceptHeader, $expected)
    {
        $request = new Request();

        $this->assertEquals($expected, $request->splitHttpAcceptHeader($acceptHeader));
    }

    public function splitHttpAcceptHeaderData()
    {
        return array(
            array(null, array()),
            array('text/html;q=0.8', array('text/html' => 0.8)),
            array('text/html;foo=bar;q=0.8 ', array('text/html;foo=bar' => 0.8)),
            array('text/html;charset=utf-8; q=0.8', array('text/html;charset=utf-8' => 0.8)),
            array('text/html,application/xml;q=0.9,*/*;charset=utf-8; q=0.8', array('text/html' => 1, 'application/xml' => 0.9, '*/*;charset=utf-8' => 0.8)),
            array('text/html,application/xhtml+xml;q=0.9,*/*;q=0.8; foo=bar', array('text/html' => 1, 'application/xhtml+xml' => 0.9, '*/*' => 0.8)),
            array('text/html,application/xhtml+xml;charset=utf-8;q=0.9; foo=bar,*/*', array('text/html' => 1, '*/*' => 1, 'application/xhtml+xml;charset=utf-8' => 0.9)),
            array('text/html,application/xhtml+xml', array('application/xhtml+xml' => 1, 'text/html' => 1)),
        );
    }

    public function testIsProxyTrusted()
    {
        $this->startTrustingProxyData();
        $this->assertTrue(Request::isProxyTrusted());
        $this->stopTrustingProxyData();
        $this->assertFalse(Request::isProxyTrusted());
    }

    public function testIsMethod()
    {
        $request = new Request();
        $request->setMethod('POST');
        $this->assertTrue($request->isMethod('POST'));
        $this->assertTrue($request->isMethod('post'));
        $this->assertFalse($request->isMethod('GET'));
        $this->assertFalse($request->isMethod('get'));

        $request->setMethod('GET');
        $this->assertTrue($request->isMethod('GET'));
        $this->assertTrue($request->isMethod('get'));
        $this->assertFalse($request->isMethod('POST'));
        $this->assertFalse($request->isMethod('post'));
    }

    private function startTrustingProxyData()
    {
        Request::trustProxyData();
    }

    /**
     * @dataProvider getBaseUrlData
     */
    public function testGetBaseUrl($uri, $server, $expectedBaseUrl, $expectedPathInfo)
    {
        $request = Request::create($uri, 'GET', array(), array(), array(), $server);

        $this->assertSame($expectedBaseUrl, $request->getBaseUrl(), 'baseUrl');
        $this->assertSame($expectedPathInfo, $request->getPathInfo(), 'pathInfo');
    }

    public function getBaseUrlData()
    {
        return array(
            array(
                '/foo%20bar',
                array(
                    'SCRIPT_FILENAME' => '/home/John Doe/public_html/foo bar/app.php',
                    'SCRIPT_NAME'     => '/foo bar/app.php',
                    'PHP_SELF'        => '/foo bar/app.php',
                ),
                '/foo%20bar',
                '/',
            ),
            array(
                '/foo%20bar/home',
                array(
                    'SCRIPT_FILENAME' => '/home/John Doe/public_html/foo bar/app.php',
                    'SCRIPT_NAME'     => '/foo bar/app.php',
                    'PHP_SELF'        => '/foo bar/app.php',
                ),
                '/foo%20bar',
                '/home',
            ),
            array(
                '/foo%20bar/app.php/home',
                array(
                    'SCRIPT_FILENAME' => '/home/John Doe/public_html/foo bar/app.php',
                    'SCRIPT_NAME'     => '/foo bar/app.php',
                    'PHP_SELF'        => '/foo bar/app.php',
                ),
                '/foo%20bar/app.php',
                '/home',
            ),
            array(
                '/foo%20bar/app.php/home%3Dbaz',
                array(
                    'SCRIPT_FILENAME' => '/home/John Doe/public_html/foo bar/app.php',
                    'SCRIPT_NAME'     => '/foo bar/app.php',
                    'PHP_SELF'        => '/foo bar/app.php',
                ),
                '/foo%20bar/app.php',
                '/home%3Dbaz',
            ),
            array(
                '/foo/bar+baz',
                array(
                    'SCRIPT_FILENAME' => '/home/John Doe/public_html/foo/app.php',
                    'SCRIPT_NAME'     => '/foo/app.php',
                    'PHP_SELF'        => '/foo/app.php',
                ),
                '/foo',
                '/bar+baz',
            ),
        );
    }

    /**
     * @dataProvider urlencodedStringPrefixData
     */
    public function testUrlencodedStringPrefix($string, $prefix, $expect)
    {
        $request = new Request;

        $me = new \ReflectionMethod($request, 'getUrlencodedPrefix');
        $me->setAccessible(true);

        $this->assertSame($expect, $me->invoke($request, $string, $prefix));
    }

    public function urlencodedStringPrefixData()
    {
        return array(
            array('foo', 'foo', 'foo'),
            array('fo%6f', 'foo', 'fo%6f'),
            array('foo/bar', 'foo', 'foo'),
            array('fo%6f/bar', 'foo', 'fo%6f'),
            array('f%6f%6f/bar', 'foo', 'f%6f%6f'),
            array('%66%6F%6F/bar', 'foo', '%66%6F%6F'),
            array('fo+o/bar', 'fo+o', 'fo+o'),
            array('fo%2Bo/bar', 'fo+o', 'fo%2Bo'),
        );
    }
    
    private function stopTrustingProxyData()
    {
        $class = new \ReflectionClass('Symfony\\Component\\HttpFoundation\\Request');
        $property = $class->getProperty('trustProxy');
        $property->setAccessible(true);
        $property->setValue(false);
    }
}

class RequestContentProxy extends Request
{
    public function getContent($asResource = false)
    {
        return http_build_query(array('_method' => 'PUT', 'content' => 'mycontent'));
    }
}
