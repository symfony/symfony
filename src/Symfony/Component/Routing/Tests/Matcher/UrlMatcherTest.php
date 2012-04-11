<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Matcher;

use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RequestContext;

class UrlMatcherTest extends \PHPUnit_Framework_TestCase
{
    public function testNoMethodSoAllowed()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo'));

        $matcher = new UrlMatcher($coll, new RequestContext());
        $matcher->match('/foo');
    }

    public function testMethodNotAllowed()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo', array(), array('_method' => 'post')));

        $matcher = new UrlMatcher($coll, new RequestContext());

        try {
            $matcher->match('/foo');
            $this->fail();
        } catch (MethodNotAllowedException $e) {
            $this->assertEquals(array('POST'), $e->getAllowedMethods());
        }
    }

    public function testHeadAllowedWhenRequirementContainsGet()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo', array(), array('_method' => 'get')));

        $matcher = new UrlMatcher($coll, new RequestContext('', 'head'));
        $matcher->match('/foo');
    }

    public function testMethodNotAllowedAggregatesAllowedMethods()
    {
        $coll = new RouteCollection();
        $coll->add('foo1', new Route('/foo', array(), array('_method' => 'post')));
        $coll->add('foo2', new Route('/foo', array(), array('_method' => 'put|delete')));

        $matcher = new UrlMatcher($coll, new RequestContext());

        try {
            $matcher->match('/foo');
            $this->fail();
        } catch (MethodNotAllowedException $e) {
            $this->assertEquals(array('POST', 'PUT', 'DELETE'), $e->getAllowedMethods());
        }
    }

    public function testMatch()
    {
        // test the patterns are matched and parameters are returned
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo/{bar}'));
        $matcher = new UrlMatcher($collection, new RequestContext(), array());
        try {
            $matcher->match('/no-match');
            $this->fail();
        } catch (ResourceNotFoundException $e) {}
        $this->assertEquals(array('_route' => 'foo', 'bar' => 'baz'), $matcher->match('/foo/baz'));

        // test that defaults are merged
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo/{bar}', array('def' => 'test')));
        $matcher = new UrlMatcher($collection, new RequestContext(), array());
        $this->assertEquals(array('_route' => 'foo', 'bar' => 'baz', 'def' => 'test'), $matcher->match('/foo/baz'));

        // test that route "method" is ignored if no method is given in the context
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo', array(), array('_method' => 'GET|head')));
        $matcher = new UrlMatcher($collection, new RequestContext(), array());
        $this->assertInternalType('array', $matcher->match('/foo'));

        // route does not match with POST method context
        $matcher = new UrlMatcher($collection, new RequestContext('', 'post'), array());
        try {
            $matcher->match('/foo');
            $this->fail();
        } catch (MethodNotAllowedException $e) {}

        // route does match with GET or HEAD method context
        $matcher = new UrlMatcher($collection, new RequestContext(), array());
        $this->assertInternalType('array', $matcher->match('/foo'));
        $matcher = new UrlMatcher($collection, new RequestContext('', 'head'), array());
        $this->assertInternalType('array', $matcher->match('/foo'));

        // route with an optional variable as the first segment
        $collection = new RouteCollection();
        $collection->add('bar', new Route('/{bar}/foo', array('bar' => 'bar'), array('bar' => 'foo|bar')));
        $matcher = new UrlMatcher($collection, new RequestContext(), array());
        $this->assertEquals(array('_route' => 'bar', 'bar' => 'bar'), $matcher->match('/bar/foo'));
        $this->assertEquals(array('_route' => 'bar', 'bar' => 'foo'), $matcher->match('/foo/foo'));

        $collection = new RouteCollection();
        $collection->add('bar', new Route('/{bar}', array('bar' => 'bar'), array('bar' => 'foo|bar')));
        $matcher = new UrlMatcher($collection, new RequestContext(), array());
        $this->assertEquals(array('_route' => 'bar', 'bar' => 'foo'), $matcher->match('/foo'));
        $this->assertEquals(array('_route' => 'bar', 'bar' => 'bar'), $matcher->match('/'));
    }

    public function testMatchWithPrefixes()
    {
        $collection1 = new RouteCollection();
        $collection1->add('foo', new Route('/{foo}'));

        $collection2 = new RouteCollection();
        $collection2->addCollection($collection1, '/b');

        $collection = new RouteCollection();
        $collection->addCollection($collection2, '/a');

        $matcher = new UrlMatcher($collection, new RequestContext(), array());
        $this->assertEquals(array('_route' => 'foo', 'foo' => 'foo'), $matcher->match('/a/b/foo'));
    }

    public function testMatchWithDynamicPrefix()
    {
        $collection1 = new RouteCollection();
        $collection1->add('foo', new Route('/{foo}'));

        $collection2 = new RouteCollection();
        $collection2->addCollection($collection1, '/b');

        $collection = new RouteCollection();
        $collection->addCollection($collection2, '/{_locale}');

        $matcher = new UrlMatcher($collection, new RequestContext(), array());
        $this->assertEquals(array('_locale' => 'fr', '_route' => 'foo', 'foo' => 'foo'), $matcher->match('/fr/b/foo'));
    }

    public function testMatchNonAlpha()
    {
        $collection = new RouteCollection();
        $chars = '!"$%éà &\'()*+,./:;<=>@ABCDEFGHIJKLMNOPQRSTUVWXYZ\\[]^_`abcdefghijklmnopqrstuvwxyz{|}~-';
        $collection->add('foo', new Route('/{foo}/bar', array(), array('foo' => '['.preg_quote($chars).']+')));

        $matcher = new UrlMatcher($collection, new RequestContext(), array());
        $this->assertEquals(array('_route' => 'foo', 'foo' => $chars), $matcher->match('/'.rawurlencode($chars).'/bar'));
        $this->assertEquals(array('_route' => 'foo', 'foo' => $chars), $matcher->match('/'.strtr($chars, array('%' => '%25')).'/bar'));
    }

    public function testMatchWithDotMetacharacterInRequirements()
    {
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/{foo}/bar', array(), array('foo' => '.+')));

        $matcher = new UrlMatcher($collection, new RequestContext(), array());
        $this->assertEquals(array('_route' => 'foo', 'foo' => "\n"), $matcher->match('/'.urlencode("\n").'/bar'), 'linefeed character is matched');
    }

    public function testMatchOverridenRoute()
    {
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo'));

        $collection1 = new RouteCollection();
        $collection1->add('foo', new Route('/foo1'));

        $collection->addCollection($collection1);

        $matcher = new UrlMatcher($collection, new RequestContext(), array());

        $this->assertEquals(array('_route' => 'foo'), $matcher->match('/foo1'));
        $this->setExpectedException('Symfony\Component\Routing\Exception\ResourceNotFoundException');
        $this->assertEquals(array(), $matcher->match('/foo'));
    }

    public function testMatchRegression()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/{foo}'));
        $coll->add('bar', new Route('/foo/bar/{foo}'));

        $matcher = new UrlMatcher($coll, new RequestContext());
        $this->assertEquals(array('foo' => 'bar', '_route' => 'bar'), $matcher->match('/foo/bar/bar'));

        $collection = new RouteCollection();
        $collection->add('foo', new Route('/{bar}'));
        $matcher = new UrlMatcher($collection, new RequestContext(), array());
        try {
            $matcher->match('/');
            $this->fail();
        } catch (ResourceNotFoundException $e) {
        }
    }

    /**
     * @expectedException Symfony\Component\Routing\Exception\ResourceNotFoundException
     */
    public function testSchemeRequirement()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo', array(), array('_scheme' => 'https')));
        $matcher = new UrlMatcher($coll, new RequestContext());
        $matcher->match('/foo');
    }

    public function testDecodeOnce()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/{foo}'));

        $matcher = new UrlMatcher($coll, new RequestContext());
        $this->assertEquals(array('foo' => 'bar%23', '_route' => 'foo'), $matcher->match('/foo/bar%2523'));
    }
}
