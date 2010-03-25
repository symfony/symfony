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

require_once __DIR__.'/../../bootstrap.php';

use Symfony\Components\RequestHandler\Request;

class RequestTest extends \PHPUnit_Framework_TestCase
{
  /**
   * @covers Request::__construct()
   */
  public function testConstructor()
  {
    $this->testSetParameters();
  }

  /**
   * @covers Request::setParameters()
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
   * @covers Request::duplicate()
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
}
