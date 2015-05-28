<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Profiler\Tests\DataCollector;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Profiler\DataCollector\RequestDataCollector;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\EventDispatcher\EventDispatcher;

class RequestDataCollectorTest extends \PHPUnit_Framework_TestCase
{
    public function testCollect()
    {
        $requestStack = new RequestStack();
        $c = new RequestDataCollector($requestStack);
        $requestStack->push($this->createRequest());

        $c->onKernelResponse(
            new FilterResponseEvent(
                $this->getKernel(), $requestStack->getMasterRequest(), HttpKernelInterface::MASTER_REQUEST, $this->createResponse()
            )
        );
        $data = $c->collect();

        $attributes = $data->getRequestAttributes();

        $this->assertSame('request', $data->getName());
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\HeaderBag', $data->getRequestHeaders());
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\ParameterBag', $data->getRequestServer());
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\ParameterBag', $data->getRequestCookies());
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\ParameterBag', $attributes);
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\ParameterBag', $data->getRequestRequest());
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\ParameterBag', $data->getRequestQuery());
        $this->assertSame('html', $data->getFormat());
        $this->assertSame('foobar', $data->getRoute());
        $this->assertSame(array('name' => 'foo'), $data->getRouteParams());
        $this->assertSame(array(), $data->getSessionAttributes());
        $this->assertSame('en', $data->getLocale());
        $this->assertRegExp('/Resource\(stream#\d+\)/', $attributes->get('resource'));
        $this->assertSame('Object(stdClass)', $attributes->get('object'));

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\HeaderBag', $data->getResponseHeaders());
        $this->assertSame('OK', $data->getStatusText());
        $this->assertSame(200, $data->getStatusCode());
        $this->assertSame('application/json', $data->getContentType());
    }

    /**
     * Test various types of controller callables.
     */
    public function testControllerInspection()
    {
        // make sure we always match the line number
        $r1 = new \ReflectionMethod($this, 'testControllerInspection');
        $r2 = new \ReflectionMethod($this, 'staticControllerMethod');
        $r3 = new \ReflectionClass($this);
        // test name, callable, expected
        $controllerTests = array(
            array(
                '"Regular" callable',
                array($this, 'testControllerInspection'),
                array(
                    'class' => 'Symfony\Component\Profiler\Tests\DataCollector\RequestDataCollectorTest',
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
                'Symfony\Component\Profiler\Tests\DataCollector\RequestDataCollectorTest::staticControllerMethod',
                'Symfony\Component\Profiler\Tests\DataCollector\RequestDataCollectorTest::staticControllerMethod',
            ),

            array(
                'Static callable with instance',
                array($this, 'staticControllerMethod'),
                array(
                    'class' => 'Symfony\Component\Profiler\Tests\DataCollector\RequestDataCollectorTest',
                    'method' => 'staticControllerMethod',
                    'file' => __FILE__,
                    'line' => $r2->getStartLine(),
                ),
            ),

            array(
                'Static callable with class name',
                array('Symfony\Component\Profiler\Tests\DataCollector\RequestDataCollectorTest', 'staticControllerMethod'),
                array(
                    'class' => 'Symfony\Component\Profiler\Tests\DataCollector\RequestDataCollectorTest',
                    'method' => 'staticControllerMethod',
                    'file' => __FILE__,
                    'line' => $r2->getStartLine(),
                ),
            ),

            array(
                'Callable with instance depending on __call()',
                array($this, 'magicMethod'),
                array(
                    'class' => 'Symfony\Component\Profiler\Tests\DataCollector\RequestDataCollectorTest',
                    'method' => 'magicMethod',
                    'file' => 'n/a',
                    'line' => 'n/a',
                ),
            ),

            array(
                'Callable with class name depending on __callStatic()',
                array('Symfony\Component\Profiler\Tests\DataCollector\RequestDataCollectorTest', 'magicMethod'),
                array(
                    'class' => 'Symfony\Component\Profiler\Tests\DataCollector\RequestDataCollectorTest',
                    'method' => 'magicMethod',
                    'file' => 'n/a',
                    'line' => 'n/a',
                ),
            ),

            array(
                'Invokable controller',
                $this,
                array(
                    'class' => 'Symfony\Component\Profiler\Tests\DataCollector\RequestDataCollectorTest',
                    'method' => null,
                    'file' => __FILE__,
                    'line' => $r3->getStartLine(),
                ),
            ),
        );

        $requestStack = new RequestStack();
        $c = new RequestDataCollector($requestStack);
        $request = $this->createRequest();
        $requestStack->push($request);
        $c->onKernelResponse(
            new FilterResponseEvent(
                $this->getKernel(), $requestStack->getMasterRequest(), HttpKernelInterface::MASTER_REQUEST, $this->createResponse()
            )
        );
        foreach ($controllerTests as $controllerTest) {
            $this->injectController($c, $controllerTest[1], $request);
            $data = $c->collect();
            $this->assertSame($controllerTest[2], $data->getController(), sprintf('Testing: %s', $controllerTest[0]));
        }
    }

    protected function createRequest()
    {
        $request = Request::create('http://test.com/foo?bar=baz');
        $request->attributes->set('foo', 'bar');
        $request->attributes->set('_route', 'foobar');
        $request->attributes->set('_route_params', array('name' => 'foo'));
        $request->attributes->set('resource', fopen(__FILE__, 'r'));
        $request->attributes->set('object', new \stdClass());

        return $request;
    }

    protected function createResponse()
    {
        $response = new Response();
        $response->setStatusCode(200);
        $response->headers->set('Content-Type', 'application/json');
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
        $resolver = $this->getMock('Symfony\\Component\\HttpKernel\\Controller\\ControllerResolverInterface');
        $httpKernel = new HttpKernel(new EventDispatcher(), $resolver);
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

    protected function getKernel()
    {
        return $this->getMock('Symfony\Component\HttpKernel\KernelInterface');
    }
}
