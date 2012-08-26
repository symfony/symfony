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

class UrlMatcherCollectionTest extends \PHPUnit_Framework_TestCase
{
    public function testMatchAll()
    {
        // test the patterns are matched and a collection is returned
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo/{bar}'));
        $matcher = new TestUrlMatcher($collection, new RequestContext());
        $ret = $matcher->matchCollection('/foo/baz', $collection, false);
        $this->assertInstanceOf('Symfony\\Component\\Routing\\RouteCollection', $ret);
        $this->assertCount(1, $ret);

        // test the patterns are matched and more than one route is returned
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo/{bar}'));
        $collection->add('foobaz', new Route('/foo/baz'));
        $matcher = new TestUrlMatcher($collection, new RequestContext());
        $ret = $matcher->matchCollection('/foo/baz', $collection, false);
        $this->assertInstanceOf('Symfony\\Component\\Routing\\RouteCollection', $ret);
        $this->assertCount(2, $ret);
    }

    public function testMatchAllWithPrefixes()
    {
        $collection1 = new RouteCollection();
        $collection1->add('foo', new Route('/{foo}'));

        $collection2 = new RouteCollection();
        $collection2->addCollection($collection1, '/b');

        $collection = new RouteCollection();
        $collection->addCollection($collection2, '/a');
        $collection->add('abfoo', new Route('/a/b/foo'));
        $collection->add('bfoo', new Route('/b/foo'));

        $matcher = new TestUrlMatcher($collection, new RequestContext());
        $ret = $matcher->matchCollection('/a/b/foo', $collection, false);
        $this->assertInstanceOf('Symfony\\Component\\Routing\\RouteCollection', $ret);
        $this->assertCount(2, $ret);
    }
}

class TestUrlMatcher extends UrlMatcher
{
    /**
     * {@inheritDoc}
     *
     * Make this method public so it can be called in the test
     */
    public function matchCollection($pathinfo, RouteCollection $routes, $returnFirst = true)
    {
        return parent::matchCollection($pathinfo, $routes, $returnFirst);
    }
}
