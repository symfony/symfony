<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Routing\Matcher;

use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;

class UrlMatcherTest extends \PHPUnit_Framework_TestCase
{
    public function testNormalizeUrl()
    {
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/{foo}'));

        $matcher = new UrlMatcherForTests($collection, array(), array());

        $this->assertEquals('/foo', $matcher->normalizeUrl('/foo?foo=bar'), '->normalizeUrl() removes the query string');
        $this->assertEquals('/foo/bar', $matcher->normalizeUrl('/foo//bar'), '->normalizeUrl() removes duplicated /');
    }

    public function testMatch()
    {
      // test the patterns are matched are parameters are returned
      $collection = new RouteCollection();
      $collection->add('foo', new Route('/foo/{bar}'));
      $matcher = new UrlMatcher($collection, array(), array());
      $this->assertEquals(false, $matcher->match('/no-match'));
      $this->assertEquals(array('_route' => 'foo', 'bar' => 'baz'), $matcher->match('/foo/baz'));

      // test that defaults are merged
      $collection = new RouteCollection();
      $collection->add('foo', new Route('/foo/{bar}', array('def' => 'test')));
      $matcher = new UrlMatcher($collection, array(), array());
      $this->assertEquals(array('_route' => 'foo', 'bar' => 'baz', 'def' => 'test'), $matcher->match('/foo/baz'));

      // test that route "method" is ignore if no method is given in the context
      $collection = new RouteCollection();
      $collection->add('foo', new Route('/foo', array(), array('_method' => 'GET|head')));

      // route matches with no context
      $matcher = new UrlMatcher($collection, array(), array());
      $this->assertNotEquals(false, $matcher->match('/foo'));

      // route does not match with POST method context
      $matcher = new UrlMatcher($collection, array('method' => 'POST'), array());
      $this->assertEquals(false, $matcher->match('/foo'));

      // route does match with GET or HEAD method context
      $matcher = new UrlMatcher($collection, array('method' => 'GET'), array());
      $this->assertNotEquals(false, $matcher->match('/foo'));
      $matcher = new UrlMatcher($collection, array('method' => 'HEAD'), array());
      $this->assertNotEquals(false, $matcher->match('/foo'));
    }
}

class UrlMatcherForTests extends UrlMatcher
{
    public function normalizeUrl($url)
    {
        return parent::normalizeUrl($url);
    }
}
