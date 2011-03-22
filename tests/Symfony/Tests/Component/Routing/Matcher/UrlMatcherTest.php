<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Routing\Matcher;

use Symfony\Component\Routing\Matcher\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Matcher\Exception\NotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class UrlMatcherTest extends \PHPUnit_Framework_TestCase
{
    public function testNoMethodSoAllowed()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo'));

        $matcher = new UrlMatcher($coll, array('method' => 'get'));
        $matcher->match('/foo');
    }

    public function testMethodNotAllowed()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo', array(), array('_method' => 'post')));

        $matcher = new UrlMatcher($coll, array('method' => 'get'));

        try {
            $matcher->match('/foo');
            $this->fail();
        } catch (MethodNotAllowedException $e) {
            $this->assertEquals(array('post'), $e->getAllowedMethods());
        }
    }

    public function testMethodNotAllowedAggregatesAllowedMethods()
    {
        $coll = new RouteCollection();
        $coll->add('foo1', new Route('/foo', array(), array('_method' => 'post')));
        $coll->add('foo2', new Route('/foo', array(), array('_method' => 'put|delete')));

        $matcher = new UrlMatcher($coll, array('method' => 'get'));

        try {
            $matcher->match('/foo');
            $this->fail();
        } catch (MethodNotAllowedException $e) {
            $this->assertEquals(array('post', 'put', 'delete'), $e->getAllowedMethods());
        }
    }

    public function testMatch()
    {
        // test the patterns are matched are parameters are returned
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo/{bar}'));
        $matcher = new UrlMatcher($collection, array(), array());
        try {
            $matcher->match('/no-match');
            $this->fail();
        } catch (NotFoundException $e) {}
        $this->assertEquals(array('_route' => 'foo', 'bar' => 'baz'), $matcher->match('/foo/baz'));

        // test that defaults are merged
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo/{bar}', array('def' => 'test')));
        $matcher = new UrlMatcher($collection, array(), array());
        $this->assertEquals(array('_route' => 'foo', 'bar' => 'baz', 'def' => 'test'), $matcher->match('/foo/baz'));

        // test that route "method" is ignored if no method is given in the context
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo', array(), array('_method' => 'GET|head')));
        $matcher = new UrlMatcher($collection, array(), array());
        $this->assertInternalType('array', $matcher->match('/foo'));

        // route does not match with POST method context
        $matcher = new UrlMatcher($collection, array('method' => 'POST'), array());
        try {
            $matcher->match('/foo');
            $this->fail();
        } catch (MethodNotAllowedException $e) {}

        // route does match with GET or HEAD method context
        $matcher = new UrlMatcher($collection, array('method' => 'GET'), array());
        $this->assertInternalType('array', $matcher->match('/foo'));
        $matcher = new UrlMatcher($collection, array('method' => 'HEAD'), array());
        $this->assertInternalType('array', $matcher->match('/foo'));
    }
}
