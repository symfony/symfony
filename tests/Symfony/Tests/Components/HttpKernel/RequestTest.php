<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\HttpKernel;

use Symfony\Components\HttpKernel\Request;

class RequestTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Symfony\Components\HttpKernel\Request::__construct
     */
    public function testConstructor()
    {
        $this->testInitialize();
    }

    /**
     * @covers Symfony\Components\HttpKernel\Request::initialize
     */
    public function testInitialize()
    {
        $request = new Request();

        $request->initialize(array('foo' => 'bar'));
        $this->assertEquals('bar', $request->query->get('foo'), '->initialize() takes an array of query parameters as its first argument');

        $request->initialize(null, array('foo' => 'bar'));
        $this->assertEquals('bar', $request->request->get('foo'), '->initialize() takes an array of request parameters as its second argument');

        $request->initialize(null, null, array('foo' => 'bar'));
        $this->assertEquals('bar', $request->path->get('foo'), '->initialize() takes an array of path parameters as its thrid argument');

        $request->initialize(null, null, null, null, null, array('HTTP_FOO' => 'bar'));
        $this->assertEquals('bar', $request->headers->get('FOO'), '->initialize() takes an array of HTTP headers as its fourth argument');
    }

    /**
     * @covers Symfony\Components\HttpKernel\Request::duplicate
     */
    public function testDuplicate()
    {
        $request = new Request(array('foo' => 'bar'), array('foo' => 'bar'), array('foo' => 'bar'), array(), array(), array('HTTP_FOO' => 'bar'));
        $dup = $request->duplicate();

        $this->assertEquals($request->query->all(), $dup->query->all(), '->duplicate() duplicates a request an copy the current query parameters');
        $this->assertEquals($request->request->all(), $dup->request->all(), '->duplicate() duplicates a request an copy the current request parameters');
        $this->assertEquals($request->path->all(), $dup->path->all(), '->duplicate() duplicates a request an copy the current path parameters');
        $this->assertEquals($request->headers->all(), $dup->headers->all(), '->duplicate() duplicates a request an copy the current HTTP headers');

        $dup = $request->duplicate(array('foo' => 'foobar'), array('foo' => 'foobar'), array('foo' => 'foobar'), array(), array(), array('HTTP_FOO' => 'foobar'));

        $this->assertEquals(array('foo' => 'foobar'), $dup->query->all(), '->duplicate() overrides the query parameters if provided');
        $this->assertEquals(array('foo' => 'foobar'), $dup->request->all(), '->duplicate() overrides the request parameters if provided');
        $this->assertEquals(array('foo' => 'foobar'), $dup->path->all(), '->duplicate() overrides the path parameters if provided');
        $this->assertEquals(array('foo' => array('foobar')), $dup->headers->all(), '->duplicate() overrides the HTTP header if provided');
    }

    /**
     * @covers Symfony\Components\HttpKernel\Request::getFormat
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
}
