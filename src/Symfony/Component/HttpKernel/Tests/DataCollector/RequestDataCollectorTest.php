<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\EventDispatcher\EventDispatcher;

class RequestDataCollectorTest extends TestCase
{
    public function testCollect()
    {
        $c = new RequestDataCollector();

        $c->collect($request = $this->createRequest(), $this->createResponse());
        $c->lateCollect();

        $attributes = $c->getRequestAttributes();

        $this->assertSame('request', $c->getName());
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\ParameterBag', $c->getRequestHeaders());
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\ParameterBag', $c->getRequestServer());
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\ParameterBag', $c->getRequestCookies());
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\ParameterBag', $attributes);
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\ParameterBag', $c->getRequestRequest());
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\ParameterBag', $c->getRequestQuery());
        $this->assertInstanceOf(ParameterBag::class, $c->getResponseCookies());
        $this->assertSame('html', $c->getFormat());
        $this->assertEquals('foobar', $c->getRoute());
        $this->assertEquals(array('name' => 'foo'), $c->getRouteParams());
        $this->assertSame(array(), $c->getSessionAttributes());
        $this->assertSame('en', $c->getLocale());
        $this->assertContains(__FILE__, $attributes->get('resource'));
        $this->assertSame('stdClass', $attributes->get('object')->getType());

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\ParameterBag', $c->getResponseHeaders());
        $this->assertSame('OK', $c->getStatusText());
        $this->assertSame(200, $c->getStatusCode());
        $this->assertSame('application/json', $c->getContentType());
    }

    public function testCollectWithoutRouteParams()
    {
        $request = $this->createRequest(array());

        $c = new RequestDataCollector();
        $c->collect($request, $this->createResponse());
        $c->lateCollect();

        $this->assertEquals(array(), $c->getRouteParams());
    }

    /**
     * @dataProvider provideControllerCallables
     */
    public function testControllerInspection($name, $callable, $expected)
    {
        $c = new RequestDataCollector();
        $request = $this->createRequest();
        $response = $this->createResponse();
        $this->injectController($c, $callable, $request);
        $c->collect($request, $response);
        $c->lateCollect();

        $this->assertSame($expected, $c->getController()->getValue(true), sprintf('Testing: %s', $name));
    }

    public function provideControllerCallables()
    {
        // make sure we always match the line number
        $r1 = new \ReflectionMethod($this, 'testControllerInspection');
        $r2 = new \ReflectionMethod($this, 'staticControllerMethod');
        $r3 = new \ReflectionClass($this);

        // test name, callable, expected
        return array(
            array(
                '"Regular" callable',
                array($this, 'testControllerInspection'),
                array(
                    'class' => __NAMESPACE__.'\RequestDataCollectorTest',
                    'method' => 'testControllerInspection',
                    'file' => __FILE__,
                    'line' => $r1->getStartLine(),
                ),
            ),

            array(
                'Closure',
                function () { return 'foo'; },
                array(
                    'class' => __NAMESPACE__.'\{closure}',
                    'method' => null,
                    'file' => __FILE__,
                    'line' => __LINE__ - 5,
                ),
            ),

            array(
                'Static callback as string',
                __NAMESPACE__.'\RequestDataCollectorTest::staticControllerMethod',
                array(
                    'class' => 'Symfony\Component\HttpKernel\Tests\DataCollector\RequestDataCollectorTest',
                    'method' => 'staticControllerMethod',
                    'file' => __FILE__,
                    'line' => $r2->getStartLine(),
                ),
            ),

            array(
                'Static callable with instance',
                array($this, 'staticControllerMethod'),
                array(
                    'class' => 'Symfony\Component\HttpKernel\Tests\DataCollector\RequestDataCollectorTest',
                    'method' => 'staticControllerMethod',
                    'file' => __FILE__,
                    'line' => $r2->getStartLine(),
                ),
            ),

            array(
                'Static callable with class name',
                array('Symfony\Component\HttpKernel\Tests\DataCollector\RequestDataCollectorTest', 'staticControllerMethod'),
                array(
                    'class' => 'Symfony\Component\HttpKernel\Tests\DataCollector\RequestDataCollectorTest',
                    'method' => 'staticControllerMethod',
                    'file' => __FILE__,
                    'line' => $r2->getStartLine(),
                ),
            ),

            array(
                'Callable with instance depending on __call()',
                array($this, 'magicMethod'),
                array(
                    'class' => 'Symfony\Component\HttpKernel\Tests\DataCollector\RequestDataCollectorTest',
                    'method' => 'magicMethod',
                    'file' => 'n/a',
                    'line' => 'n/a',
                ),
            ),

            array(
                'Callable with class name depending on __callStatic()',
                array('Symfony\Component\HttpKernel\Tests\DataCollector\RequestDataCollectorTest', 'magicMethod'),
                array(
                    'class' => 'Symfony\Component\HttpKernel\Tests\DataCollector\RequestDataCollectorTest',
                    'method' => 'magicMethod',
                    'file' => 'n/a',
                    'line' => 'n/a',
                ),
            ),

            array(
                'Invokable controller',
                $this,
                array(
                    'class' => 'Symfony\Component\HttpKernel\Tests\DataCollector\RequestDataCollectorTest',
                    'method' => null,
                    'file' => __FILE__,
                    'line' => $r3->getStartLine(),
                ),
            ),
        );
    }

    public function testItIgnoresInvalidCallables()
    {
        $request = $this->createRequestWithSession();
        $response = new RedirectResponse('/');

        $c = new RequestDataCollector();
        $c->collect($request, $response);

        $this->assertSame('n/a', $c->getController());
    }

    protected function createRequest($routeParams = array('name' => 'foo'))
    {
        $request = Request::create('http://test.com/foo?bar=baz');
        $request->attributes->set('foo', 'bar');
        $request->attributes->set('_route', 'foobar');
        $request->attributes->set('_route_params', $routeParams);
        $request->attributes->set('resource', fopen(__FILE__, 'r'));
        $request->attributes->set('object', new \stdClass());

        return $request;
    }

    private function createRequestWithSession()
    {
        $request = $this->createRequest();
        $request->attributes->set('_controller', 'Foo::bar');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->getSession()->start();

        return $request;
    }

    protected function createResponse()
    {
        $response = new Response();
        $response->setStatusCode(200);
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('X-Foo-Bar', null);
        $response->headers->setCookie(new Cookie('foo', 'bar', 1, '/foo', 'localhost', true, true));
        $response->headers->setCookie(new Cookie('bar', 'foo', new \DateTime('@946684800')));
        $response->headers->setCookie(new Cookie('bazz', 'foo', '2000-12-12'));

        return $response;
    }

    /**
     * Inject the given controller callable into the data collector.
     */
    protected function injectController($collector, $controller, $request)
    {
        $resolver = $this->getMockBuilder('Symfony\\Component\\HttpKernel\\Controller\\ControllerResolverInterface')->getMock();
        $httpKernel = new HttpKernel(new EventDispatcher(), $resolver, null, $this->getMockBuilder(ArgumentResolverInterface::class)->getMock());
        $event = new FilterControllerEvent($httpKernel, $controller, $request, HttpKernelInterface::MASTER_REQUEST);
        $collector->onKernelController($event);
    }

    /**
     * Dummy method used as controller callable.
     */
    public static function staticControllerMethod()
    {
        throw new \LogicException('Unexpected method call');
    }

    /**
     * Magic method to allow non existing methods to be called and delegated.
     */
    public function __call($method, $args)
    {
        throw new \LogicException('Unexpected method call');
    }

    /**
     * Magic method to allow non existing methods to be called and delegated.
     */
    public static function __callStatic($method, $args)
    {
        throw new \LogicException('Unexpected method call');
    }

    public function __invoke()
    {
        throw new \LogicException('Unexpected method call');
    }
}
