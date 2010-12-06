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

        $request->initialize(null, array('foo' => 'bar'));
        $this->assertEquals('bar', $request->request->get('foo'), '->initialize() takes an array of request parameters as its second argument');

        $request->initialize(null, null, array('foo' => 'bar'));
        $this->assertEquals('bar', $request->attributes->get('foo'), '->initialize() takes an array of attributes as its thrid argument');

        $request->initialize(null, null, null, null, null, array('HTTP_FOO' => 'bar'));
        $this->assertEquals('bar', $request->headers->get('FOO'), '->initialize() takes an array of HTTP headers as its fourth argument');
    }

    /**
     * @covers Symfony\Component\HttpFoundation\Request::create
     */
    public function testCreate()
    {
        $request = Request::create('http://test.com/foo?bar=baz');
        $this->assertEquals('http://test.com:80/foo?bar=baz', $request->getUri());
        $this->assertEquals('/foo', $request->getPathInfo());
        $this->assertEquals('bar=baz', $request->getQueryString());

        $request = Request::create('test.com:90/foo');
        $this->assertEquals('http://test.com:90/foo', $request->getUri());
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
     */
    public function testGetFormat()
    {
        $request = new Request();

        $this->assertNull($request->getFormat(null), '->getFormat() returns null when mime-type is null');
        $this->assertNull($request->getFormat('unexistant-mime-type'), '->getFormat() returns null when mime-type is unknown');
        $this->assertEquals('txt', $request->getFormat('text/plain'), '->getFormat() returns correct format when mime-type have one format only');
        $this->assertEquals('js', $request->getFormat('application/javascript'), '->getFormat() returns correct format when format have multiple mime-type (first)');
        $this->assertEquals('js', $request->getFormat('application/x-javascript'), '->getFormat() returns correct format when format have multiple mime-type');
        $this->assertEquals('js', $request->getFormat('text/javascript'), '->getFormat() returns correct format when format have multiple mime-type (last)');
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

        $request->initialize(null, null, null, null, null, array('HTTP_HOST' => 'www.exemple.com'));
        $this->assertEquals('www.exemple.com', $request->getHost(), '->getHost() from Host Header');

        // Host header with port number.
        $request->initialize(null, null, null, null, null, array('HTTP_HOST' => 'www.exemple.com:8080'));
        $this->assertEquals('www.exemple.com', $request->getHost(), '->getHost() from Host Header with port number');

        // Server values.
        $request->initialize(null, null, null, null, null, array('SERVER_NAME' => 'www.exemple.com'));
        $this->assertEquals('www.exemple.com', $request->getHost(), '->getHost() from server name');

        // X_FORWARDED_HOST.
        $request->initialize(null, null, null, null, null, array('HTTP_X_FORWARDED_HOST' => 'www.exemple.com'));
        $this->assertEquals('www.exemple.com', $request->getHost(), '->getHost() from X_FORWARDED_HOST');

        // X_FORWARDED_HOST
        $request->initialize(null, null, null, null, null, array('HTTP_X_FORWARDED_HOST' => 'www.exemple.com, www.second.com'));
        $this->assertEquals('www.second.com', $request->getHost(), '->getHost() value from X_FORWARDED_HOST use last value');

        // X_FORWARDED_HOST with port number
        $request->initialize(null, null, null, null, null, array('HTTP_X_FORWARDED_HOST' => 'www.exemple.com, www.second.com:8080'));
        $this->assertEquals('www.second.com', $request->getHost(), '->getHost() value from X_FORWARDED_HOST with port number');

        $request->initialize(null, null, null, null, null, array('HTTP_HOST' => 'www.exemple.com', 'HTTP_X_FORWARDED_HOST' => 'www.forward.com'));
        $this->assertEquals('www.forward.com', $request->getHost(), '->getHost() value from X_FORWARDED_HOST has priority over Host');

        $request->initialize(null, null, null, null, null, array('SERVER_NAME' => 'www.exemple.com', 'HTTP_X_FORWARDED_HOST' => 'www.forward.com'));
        $this->assertEquals('www.forward.com', $request->getHost(), '->getHost() value from X_FORWARDED_HOST has priority over SERVER_NAME ');

        $request->initialize(null, null, null, null, null, array('SERVER_NAME' => 'www.exemple.com', 'HTTP_HOST' => 'www.host.com'));
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

    public function testInitializeConvertsUploadedFiles()
    {
        $tmpFile = $this->createTempFile();
        $file = new UploadedFile($tmpFile, basename($tmpFile), 'text/plain', 100, 0);

        $request = Request::create('', 'get', array(), array(), array('file' => array(
            'name' => basename($tmpFile),
            'type' => 'text/plain',
            'tmp_name' => $tmpFile,
            'error' => 0,
            'size' => 100
        )));

        $this->assertEquals($file, $request->files->get('file'));
    }

    public function testInitializeDoesNotConvertEmptyUploadedFiles()
    {
        $request = Request::create('', 'get', array(), array(), array('file' => array(
            'name' => '',
            'type' => '',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_NO_FILE,
            'size' => 0
        )));

        $this->assertEquals(null, $request->files->get('file'));
    }

    public function testInitializeConvertsUploadedFilesWithPhpBug()
    {
        $tmpFile = $this->createTempFile();
        $file = new UploadedFile($tmpFile, basename($tmpFile), 'text/plain', 100, 0);

        $request = Request::create('', 'get', array(), array(), array(
            'child' => array(
                'name' => array(
                    'file' => basename($tmpFile),
                ),
                'type' => array(
                    'file' => 'text/plain',
                ),
                'tmp_name' => array(
                    'file' => $tmpFile,
                ),
                'error' => array(
                    'file' => 0,
                ),
                'size' => array(
                    'file' => 100,
                ),
            )
        ));

        $files = $request->files->all();
        $this->assertEquals($file, $files['child']['file']);
    }

    public function testInitializeConvertsNestedUploadedFilesWithPhpBug()
    {
        $tmpFile = $this->createTempFile();
        $file = new UploadedFile($tmpFile, basename($tmpFile), 'text/plain', 100, 0);

        $request = Request::create('', 'get', array(), array(), array(
            'child' => array(
                'name' => array(
                    'sub' => array('file' => basename($tmpFile))
                ),
                'type' => array(
                    'sub' => array('file' => 'text/plain')
                ),
                'tmp_name' => array(
                    'sub' => array('file' => $tmpFile)
                ),
                'error' => array(
                    'sub' => array('file' => 0)
                ),
                'size' => array(
                    'sub' => array('file' => 100)
                ),
            )
        ));

        $files = $request->files->all();
        $this->assertEquals($file, $files['child']['sub']['file']);
    }

    protected function createTempFile()
    {
        return tempnam(sys_get_temp_dir(), 'FormTest');
    }
}
