<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class RoutingExtensionTest extends \PHPUnit_Framework_TestCase
{
    public function testUrlGeneration()
    {
        $routes = new RouteCollection();
        $routes->add('dir', new Route('/{dir}/'));
        $routes->add('page', new Route('/{dir}/{page}.{_format}', array('_format' => 'html')));
        $routes->add('comments', new Route('/{dir}/{page}/comments'));

        $request = Request::create('http://example.com/dir/page?foo=bar&test=test');
        $request->attributes->set('_route', 'page');
        $request->attributes->set('_route_params', array('dir' => 'dir', 'page' => 'page', '_format' => 'html'));

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $context = new RequestContext();
        $context->fromRequest($request);
        $generator = new UrlGenerator($routes, $context);

        $extension = new RoutingExtension($generator, $requestStack);

        $this->assertSame('http://example.com/dir/page/comments', $extension->getUrl('comments', array('dir' => 'dir', 'page' => 'page')));
        $this->assertSame('//example.com/dir/page/comments', $extension->getUrl('comments', array('dir' => 'dir', 'page' => 'page'), true));

        $this->assertSame('/dir/page/comments', $extension->getPath('comments', array('dir' => 'dir', 'page' => 'page')));
        $this->assertSame('page/comments', $extension->getPath('comments', array('dir' => 'dir', 'page' => 'page'), true));

        $this->assertSame('page.pdf', $extension->getSubPath('', array('_format' => 'pdf')));
        $this->assertSame('?test=test', $extension->getSubPath('', array('test' => 'test'), false));
        $this->assertSame('?foo=bar&test=test&extra=extra', $extension->getSubPath('', array('extra' => 'extra'), true));
        $this->assertSame('?foo=bar&extra=extra', $extension->getSubPath('', array('extra' => 'extra', 'test' => null), true));
        $this->assertSame('otherpage.json?foo=bar&test=test&extra=extra', $extension->getSubPath('', array('extra' => 'extra', 'page' => 'otherpage', '_format' => 'json'), true));
        $this->assertSame('page/comments', $extension->getSubPath('comments', array('_format' => null)));
        $this->assertSame('./', $extension->getSubPath('dir', array('page' => null, '_format' => null)));
        $this->assertSame('./?foo=bar', $extension->getSubPath('dir', array('page' => null, '_format' => null, 'test' => null), true));
        $this->assertSame('../otherdir/page.xml', $extension->getSubPath('page', array('dir' => 'otherdir', '_format' => 'xml')));

        // we remove the request query string, so the resulting empty relative reference is actually correct for the current url and includeQuery=false
        $context->setQueryString('');
        $this->assertSame('', $extension->getSubPath());
    }

    public function testPlaceholdersHaveHigherPriorityThanQueryInSubPath()
    {
        $routes = new RouteCollection();
        $routes->add('page', new Route('/{page}'));

        $request = Request::create('http://example.com/mypage?page=querypage&bar=test');
        $request->attributes->set('_route', 'page');
        $request->attributes->set('_route_params', array('page' => 'mypage'));

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $context = new RequestContext();
        $context->fromRequest($request);
        $generator = new UrlGenerator($routes, $context);

        $extension = new RoutingExtension($generator, $requestStack);

        $this->assertStringStartsNotWith('querypage', $extension->getSubPath('', array(), true),
            'when the request query string has a parameter with the same name as a placeholder, the query param is ignored when includeQuery=true'
        );
    }

    /**
     * @dataProvider getEscapingTemplates
     */
    public function testEscaping($template, $mustBeEscaped)
    {
        $twig = new \Twig_Environment(null, array('debug' => true, 'cache' => false, 'autoescape' => true, 'optimizations' => 0));
        $twig->addExtension(new RoutingExtension($this->getMock('Symfony\Component\Routing\Generator\UrlGeneratorInterface')));

        $nodes = $twig->parse($twig->tokenize($template));

        $this->assertSame($mustBeEscaped, $nodes->getNode('body')->getNode(0)->getNode('expr') instanceof \Twig_Node_Expression_Filter);
    }

    public function getEscapingTemplates()
    {
        return array(
            array('{{ path("foo") }}', false),
            array('{{ path("foo", {}) }}', false),
            array('{{ path("foo", { foo: "foo" }) }}', false),
            array('{{ path("foo", foo) }}', true),
            array('{{ path("foo", { foo: foo }) }}', true),
            array('{{ path("foo", { foo: ["foo", "bar"] }) }}', true),
            array('{{ path("foo", { foo: "foo", bar: "bar" }) }}', true),

            array('{{ path(name = "foo", parameters = {}) }}', false),
            array('{{ path(name = "foo", parameters = { foo: "foo" }) }}', false),
            array('{{ path(name = "foo", parameters = foo) }}', true),
            array('{{ path(name = "foo", parameters = { foo: ["foo", "bar"] }) }}', true),
            array('{{ path(name = "foo", parameters = { foo: foo }) }}', true),
            array('{{ path(name = "foo", parameters = { foo: "foo", bar: "bar" }) }}', true),

            array('{{ subpath("foo") }}', true),
        );
    }
}
