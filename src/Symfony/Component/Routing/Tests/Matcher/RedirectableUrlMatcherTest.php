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

use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class RedirectableUrlMatcherTest extends UrlMatcherTest
{
    public function testMissingTrailingSlash()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/'));

        $matcher = $this->getUrlMatcher($coll);
        $matcher->expects($this->once())->method('redirect')->will($this->returnValue(array()));
        $matcher->match('/foo');
    }

    /**
     * @expectedException \Symfony\Component\Routing\Exception\ResourceNotFoundException
     */
    public function testRedirectWhenNoSlashForNonSafeMethod()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/'));

        $context = new RequestContext();
        $context->setMethod('POST');
        $matcher = $this->getUrlMatcher($coll, $context);
        $matcher->match('/foo');
    }

    public function testSchemeRedirectRedirectsToFirstScheme()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo', array(), array(), array(), '', array('FTP', 'HTTPS')));

        $matcher = $this->getUrlMatcher($coll);
        $matcher
            ->expects($this->once())
            ->method('redirect')
            ->with('/foo', 'foo', 'ftp')
            ->will($this->returnValue(array('_route' => 'foo')))
        ;
        $matcher->match('/foo');
    }

    public function testNoSchemaRedirectIfOneOfMultipleSchemesMatches()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo', array(), array(), array(), '', array('https', 'http')));

        $matcher = $this->getUrlMatcher($coll);
        $matcher
            ->expects($this->never())
            ->method('redirect');
        $matcher->match('/foo');
    }

    public function testSchemeRedirectWithParams()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/{bar}', array(), array(), array(), '', array('https')));

        $matcher = $this->getUrlMatcher($coll);
        $matcher
            ->expects($this->once())
            ->method('redirect')
            ->with('/foo/baz', 'foo', 'https')
            ->will($this->returnValue(array('redirect' => 'value')))
        ;
        $this->assertEquals(array('_route' => 'foo', 'bar' => 'baz', 'redirect' => 'value'), $matcher->match('/foo/baz'));
    }

    public function testSlashRedirectWithParams()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/{bar}/'));

        $matcher = $this->getUrlMatcher($coll);
        $matcher
            ->expects($this->once())
            ->method('redirect')
            ->with('/foo/baz/', 'foo', null)
            ->will($this->returnValue(array('redirect' => 'value')))
        ;
        $this->assertEquals(array('_route' => 'foo', 'bar' => 'baz', 'redirect' => 'value'), $matcher->match('/foo/baz'));
    }

    public function testRedirectPreservesUrlEncoding()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo:bar/'));

        $matcher = $this->getUrlMatcher($coll);
        $matcher->expects($this->once())->method('redirect')->with('/foo%3Abar/')->willReturn(array());
        $matcher->match('/foo%3Abar');
    }

    public function testSchemeRequirement()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo', array(), array(), array(), '', array('https')));
        $matcher = $this->getUrlMatcher($coll, new RequestContext());
        $matcher->expects($this->once())->method('redirect')->with('/foo', 'foo', 'https')->willReturn(array());
        $this->assertSame(array('_route' => 'foo'), $matcher->match('/foo'));
    }

    public function testFallbackPage()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/'));
        $coll->add('bar', new Route('/{name}'));

        $matcher = $this->getUrlMatcher($coll);
        $matcher->expects($this->once())->method('redirect')->with('/foo/')->will($this->returnValue(array('_route' => 'foo')));
        $this->assertSame(array('_route' => 'foo'), $matcher->match('/foo'));
    }

    public function testSlashAndVerbPrecedenceWithRedirection()
    {
        $coll = new RouteCollection();
        $coll->add('a', new Route('/api/customers/{customerId}/contactpersons', array(), array(), array(), '', array(), array('post')));
        $coll->add('b', new Route('/api/customers/{customerId}/contactpersons/', array(), array(), array(), '', array(), array('get')));

        $matcher = $this->getUrlMatcher($coll);
        $expected = array(
            '_route' => 'b',
            'customerId' => '123',
        );
        $this->assertEquals($expected, $matcher->match('/api/customers/123/contactpersons/'));

        $matcher->expects($this->once())->method('redirect')->with('/api/customers/123/contactpersons/')->willReturn(array());
        $this->assertEquals($expected, $matcher->match('/api/customers/123/contactpersons'));
    }

    protected function getUrlMatcher(RouteCollection $routes, RequestContext $context = null)
    {
        return $this->getMockForAbstractClass('Symfony\Component\Routing\Matcher\RedirectableUrlMatcher', array($routes, $context ?: new RequestContext()));
    }
}
