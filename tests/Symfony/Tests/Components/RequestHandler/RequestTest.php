<?php

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\RequestHandler;

use Symfony\Components\RequestHandler\Request;

class RequestTest extends \PHPUnit_Framework_TestCase
{
  /**
   * @covers Symfony\Components\RequestHandler\Request::__construct
   */
  public function testConstructor()
  {
    $this->testSetParameters();
  }

  /**
   * @covers Symfony\Components\RequestHandler\Request::setParameters
   */
  public function testSetParameters()
  {
    $request = new Request();

    $request->setParameters(array('foo' => 'bar'));
    $this->assertEquals('bar', $request->getQueryParameter('foo'), '->setParameter() takes an array of query parameters as its first argument');

    $request->setParameters(null, array('foo' => 'bar'));
    $this->assertEquals('bar', $request->getRequestParameter('foo'), '->setParameter() takes an array of request parameters as its second argument');

    $request->setParameters(null, null, array('foo' => 'bar'));
    $this->assertEquals('bar', $request->getPathParameter('foo'), '->setParameter() takes an array of path parameters as its thrid argument');

    $request->setParameters(null, null, null, array('HTTP_FOO' => 'bar'));
    $this->assertEquals('bar', $request->getHttpHeader('foo'), '->setParameter() takes an array of HTTP headers as its fourth argument');
  }

  /**
   * @covers Symfony\Components\RequestHandler\Request::duplicate
   */
  public function testDuplicate()
  {
    $request = new Request(array('foo' => 'bar'), array('foo' => 'bar'), array('foo' => 'bar'), array('HTTP_FOO' => 'bar'));
    $dup = $request->duplicate();

    $this->assertEquals($request->getQueryParameters(), $dup->getQueryParameters(), '->duplicate() duplicates a request an copy the current query parameters');
    $this->assertEquals($request->getRequestParameters(), $dup->getRequestParameters(), '->duplicate() duplicates a request an copy the current request parameters');
    $this->assertEquals($request->getPathParameters(), $dup->getPathParameters(), '->duplicate() duplicates a request an copy the current path parameters');
    $this->assertEquals($request->getHttpHeader('foo'), $dup->getHttpHeader('foo'), '->duplicate() duplicates a request an copy the current HTTP headers');

    $dup = $request->duplicate(array('foo' => 'foobar'), array('foo' => 'foobar'), array('foo' => 'foobar'), array('HTTP_FOO' => 'foobar'));

    $this->assertEquals(array('foo' => 'foobar'), $dup->getQueryParameters(), '->duplicate() overrides the query parameters if provided');
    $this->assertEquals(array('foo' => 'foobar'), $dup->getRequestParameters(), '->duplicate() overrides the request parameters if provided');
    $this->assertEquals(array('foo' => 'foobar'), $dup->getPathParameters(), '->duplicate() overrides the path parameters if provided');
    $this->assertEquals('foobar', $dup->getHttpHeader('foo'), '->duplicate() overrides the HTTP header if provided');
  }

  /**
   * @covers Symfony\Components\RequestHandler\Request::getFormat
   */
  public function testGetFormat()
  {
    $request = new Request();

    $this->assertEquals(null, $request->getFormat(null), '->getFormat() returns null when mime-type is null');
    $this->assertEquals(null, $request->getFormat('unexistant-mime-type'), '->getFormat() returns null when mime-type is unknown');
    $this->assertEquals('txt', $request->getFormat('text/plain'), '->getFormat() returns correct format when mime-type have one format only');
    $this->assertEquals('js', $request->getFormat('application/javascript'), '->getFormat() returns correct format when format have multiple mime-type (first)');
    $this->assertEquals('js', $request->getFormat('application/x-javascript'), '->getFormat() returns correct format when format have multiple mime-type');
    $this->assertEquals('js', $request->getFormat('text/javascript'), '->getFormat() returns correct format when format have multiple mime-type (last)');
  }
}
