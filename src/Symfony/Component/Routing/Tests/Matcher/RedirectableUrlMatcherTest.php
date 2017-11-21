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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RequestContext;

class RedirectableUrlMatcherTest extends TestCase
{
    public function testRedirectWhenNoSlash()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/'));

        $matcher = $this->getMockForAbstractClass('Symfony\Component\Routing\Matcher\RedirectableUrlMatcher', array($coll, new RequestContext()));
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
        $matcher = $this->getMockForAbstractClass('Symfony\Component\Routing\Matcher\RedirectableUrlMatcher', array($coll, $context));
        $matcher->match('/foo');
    }

    public function testSchemeRedirectRedirectsToFirstScheme()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo', array(), array(), array(), '', array('FTP', 'HTTPS')));

        $matcher = $this->getMockForAbstractClass('Symfony\Component\Routing\Matcher\RedirectableUrlMatcher', array($coll, new RequestContext()));
        $matcher
            ->expects($this->once())
            ->method('redirect')
            ->with('/foo', 'foo', 'ftp')
            ->will($this->returnValue(array('_route' => 'foo')))
        ;
        $matcher->match('/foo');
    }

    public function testNoSchemaRedirectIfOnOfMultipleSchemesMatches()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo', array(), array(), array(), '', array('https', 'http')));

        $matcher = $this->getMockForAbstractClass('Symfony\Component\Routing\Matcher\RedirectableUrlMatcher', array($coll, new RequestContext()));
        $matcher
            ->expects($this->never())
            ->method('redirect');
        $matcher->match('/foo');
    }

    public function testSchemeRedirectWithParams()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/{bar}', array(), array(), array(), '', array('https')));

        $matcher = $this->getMockForAbstractClass('Symfony\Component\Routing\Matcher\RedirectableUrlMatcher', array($coll, new RequestContext()));
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

        $matcher = $this->getMockForAbstractClass('Symfony\Component\Routing\Matcher\RedirectableUrlMatcher', array($coll, new RequestContext()));
        $matcher
            ->expects($this->once())
            ->method('redirect')
            ->with('/foo/baz/', 'foo', null)
            ->will($this->returnValue(array('redirect' => 'value')))
        ;
        $this->assertEquals(array('_route' => 'foo', 'bar' => 'baz', 'redirect' => 'value'), $matcher->match('/foo/baz'));
    }
}
