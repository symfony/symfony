<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpFoundation;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;

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
        $this->assertEquals('bar', $request->attributes->get('foo'), '->initialize() takes an array of attributes as its thrid argument');

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

        $request = Request::create('https://test.com/foo?bar=baz');
        $this->assertEquals('https://test.com/foo?bar=baz', $request->getUri());
        $this->assertEquals('/foo', $request->getPathInfo());
        $this->assertEquals('bar=baz', $request->getQueryString());
        $this->assertEquals(443, $request->getPort());

        $request = Request::create('test.com:90/foo');
        $this->assertEquals('http://test.com:90/foo', $request->getUri());
        $this->assertEquals('/foo', $request->getPathInfo());
        $this->assertEquals('test.com', $request->getHost());
        $this->assertEquals(90, $request->getPort());

        $request = Request::create('https://test.com:90/foo');
        $this->assertEquals('https://test.com:90/foo', $request->getUri());
        $this->assertEquals('/foo', $request->getPathInfo());
        $this->assertEquals('test.com', $request->getHost());
        $this->assertEquals(90, $request->getPort());
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
     * @dataProvider getFormatToMimeTypeMapProvider
     */
    public function testGetFormatFromMimeType($format, $mimeTypes)
    {
        $request = new Request();
        foreach ($mimeTypes as $mime) {            
            $this->assertEquals($format, $request->getFormat($mime));
        }

    }

    /**
     * @covers Symfony\Component\HttpFoundation\Request::getMimeType
     * @dataProvider getFormatToMimeTypeMapProvider
     */
    public function testGetMimeTypeFromFormat($format, $mimeTypes)
    {
        if (!is_null($format)) {
            $request = new Request();
            $this->assertEquals($mimeTypes[0], $request->getMimeType($format));
        }
    }

    public function getFormatToMimeTypeMapProvider()
    {
        return array(
            array(null, array(null, 'unexistant-mime-type')),
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
        $server['SERVER_NAME'] = 'hostname';
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
        $server['SERVER_NAME'] = 'hostname';
        $server['SERVER_PORT'] = '80';

        $request->initialize(array(), array(), array(), array(), array(), $server);

        $this->assertEquals('http://hostname/index.php/path/info?query=string', $request->getUri(), '->getUri() with default port');

        // Without HOST HEADER
        unset($server['HTTP_HOST']);
        $server['SERVER_NAME'] = 'hostname';
        $server['SERVER_PORT'] = '80';

        $request->initialize(array(), array(), array(), array(), array(), $server);

        $this->assertEquals('http://hostname/index.php/path/info?query=string', $request->getUri(), '->getUri() with default port without HOST_HEADER');

        // Request with URL REWRITING (hide index.php)
        //   RewriteCond %{REQUEST_FILENAME} !-f
        //   RewriteRule ^(.*)$ index.php [QSA,L]
        // http://hostname:8080/path/info?query=string
        $server = array();
        $server['HTTP_HOST'] = 'hostname:8080';
        $server['SERVER_NAME'] = 'hostname';
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
        $server['SERVER_NAME'] = 'hostname';
        $server['SERVER_PORT'] = '80';

        $request->initialize(array(), array(), array(), array(), array(), $server);

        $this->assertEquals('http://hostname/path/info?query=string', $request->getUri(), '->getUri() with rewrite and default port');

        // Without HOST HEADER
        unset($server['HTTP_HOST']);
        $server['SERVER_NAME'] = 'hostname';
        $server['SERVER_PORT'] = '80';

        $request->initialize(array(), array(), array(), array(), array(), $server);

        $this->assertEquals('http://hostname/path/info?query=string', $request->getUri(), '->getUri() with rewrite, default port without HOST_HEADER');
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
        $server['SERVER_NAME'] = 'hostname';
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
        $server['SERVER_NAME'] = 'hostname';
        $server['SERVER_PORT'] = '80';

        $request->initialize(array(), array(), array(), array(), array(), $server);

        $this->assertEquals('http://hostname/index.php/some/path', $request->getUriForPath('/some/path'), '->getUriForPath() with default port');

        // Without HOST HEADER
        unset($server['HTTP_HOST']);
        $server['SERVER_NAME'] = 'hostname';
        $server['SERVER_PORT'] = '80';

        $request->initialize(array(), array(), array(), array(), array(), $server);

        $this->assertEquals('http://hostname/index.php/some/path', $request->getUriForPath('/some/path'), '->getUriForPath() with default port without HOST_HEADER');

        // Request with URL REWRITING (hide index.php)
        //   RewriteCond %{REQUEST_FILENAME} !-f
        //   RewriteRule ^(.*)$ index.php [QSA,L]
        // http://hostname:8080/path/info?query=string
        $server = array();
        $server['HTTP_HOST'] = 'hostname:8080';
        $server['SERVER_NAME'] = 'hostname';
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
        $server['SERVER_NAME'] = 'hostname';
        $server['SERVER_PORT'] = '80';

        $request->initialize(array(), array(), array(), array(), array(), $server);

        $this->assertEquals('http://hostname/some/path', $request->getUriForPath('/some/path'), '->getUriForPath() with rewrite and default port');

        // Without HOST HEADER
        unset($server['HTTP_HOST']);
        $server['SERVER_NAME'] = 'hostname';
        $server['SERVER_PORT'] = '80';

        $request->initialize(array(), array(), array(), array(), array(), $server);

        $this->assertEquals('http://hostname/some/path', $request->getUriForPath('/some/path'), '->getUriForPath() with rewrite, default port without HOST_HEADER');
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
        $this->assertEquals('her=Jane+Doe&him=John+Doe', $request->getQueryString(), '->getQueryString() normalizes encoding');

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

    public function testCreateFromGlobals()
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
    }
}
