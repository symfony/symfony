<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerArgumentsEvent;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\EventDispatcher\EventDispatcher;

class HttpKernelTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \RuntimeException
     */
    public function testHandleWhenControllerThrowsAnExceptionAndCatchIsTrue()
    {
        $kernel = $this->getHttpKernel(new EventDispatcher(), function () { throw new \RuntimeException(); });

        $kernel->handle(new Request(), HttpKernelInterface::MASTER_REQUEST, true);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testHandleWhenControllerThrowsAnExceptionAndCatchIsFalseAndNoListenerIsRegistered()
    {
        $kernel = $this->getHttpKernel(new EventDispatcher(), function () { throw new \RuntimeException(); });

        $kernel->handle(new Request(), HttpKernelInterface::MASTER_REQUEST, false);
    }

    public function testHandleWhenControllerThrowsAnExceptionAndCatchIsTrueWithAHandlingListener()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::EXCEPTION, function ($event) {
            $event->setResponse(new Response($event->getException()->getMessage()));
        });

        $kernel = $this->getHttpKernel($dispatcher, function () { throw new \RuntimeException('foo'); });
        $response = $kernel->handle(new Request(), HttpKernelInterface::MASTER_REQUEST, true);

        $this->assertEquals('500', $response->getStatusCode());
        $this->assertEquals('foo', $response->getContent());
    }

    public function testHandleWhenControllerThrowsAnExceptionAndCatchIsTrueWithANonHandlingListener()
    {
        $exception = new \RuntimeException();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::EXCEPTION, function ($event) {
            // should set a response, but does not
        });

        $kernel = $this->getHttpKernel($dispatcher, function () use ($exception) { throw $exception; });

        try {
            $kernel->handle(new Request(), HttpKernelInterface::MASTER_REQUEST, true);
            $this->fail('LogicException expected');
        } catch (\RuntimeException $e) {
            $this->assertSame($exception, $e);
        }
    }

    public function testHandleExceptionWithARedirectionResponse()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::EXCEPTION, function ($event) {
            $event->setResponse(new RedirectResponse('/login', 301));
        });

        $kernel = $this->getHttpKernel($dispatcher, function () { throw new AccessDeniedHttpException(); });
        $response = $kernel->handle(new Request());

        $this->assertEquals('301', $response->getStatusCode());
        $this->assertEquals('/login', $response->headers->get('Location'));
    }

    public function testHandleHttpException()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::EXCEPTION, function ($event) {
            $event->setResponse(new Response($event->getException()->getMessage()));
        });

        $kernel = $this->getHttpKernel($dispatcher, function () { throw new MethodNotAllowedHttpException(array('POST')); });
        $response = $kernel->handle(new Request());

        $this->assertEquals('405', $response->getStatusCode());
        $this->assertEquals('POST', $response->headers->get('Allow'));
    }

    /**
     * @dataProvider getStatusCodes
     */
    public function testHandleWhenAnExceptionIsHandledWithASpecificStatusCode($responseStatusCode, $expectedStatusCode)
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::EXCEPTION, function ($event) use ($responseStatusCode, $expectedStatusCode) {
            $event->setResponse(new Response('', $responseStatusCode, array('X-Status-Code' => $expectedStatusCode)));
        });

        $kernel = $this->getHttpKernel($dispatcher, function () { throw new \RuntimeException(); });
        $response = $kernel->handle(new Request());

        $this->assertEquals($expectedStatusCode, $response->getStatusCode());
        $this->assertFalse($response->headers->has('X-Status-Code'));
    }

    public function getStatusCodes()
    {
        return array(
            array(200, 404),
            array(404, 200),
            array(301, 200),
            array(500, 200),
        );
    }

    public function testHandleWhenAListenerReturnsAResponse()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::REQUEST, function ($event) {
            $event->setResponse(new Response('hello'));
        });

        $kernel = $this->getHttpKernel($dispatcher);

        $this->assertEquals('hello', $kernel->handle(new Request())->getContent());
    }

    /**
     * @expectedException \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function testHandleWhenNoControllerIsFound()
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher, false);

        $kernel->handle(new Request());
    }

    public function testHandleWhenTheControllerIsAClosure()
    {
        $response = new Response('foo');
        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher, function () use ($response) { return $response; });

        $this->assertSame($response, $kernel->handle(new Request()));
    }

    public function testHandleWhenTheControllerIsAnObjectWithInvoke()
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher, new Controller());

        $this->assertResponseEquals(new Response('foo'), $kernel->handle(new Request()));
    }

    public function testHandleWhenTheControllerIsAFunction()
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher, 'Symfony\Component\HttpKernel\Tests\controller_func');

        $this->assertResponseEquals(new Response('foo'), $kernel->handle(new Request()));
    }

    public function testHandleWhenTheControllerIsAnArray()
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher, array(new Controller(), 'controller'));

        $this->assertResponseEquals(new Response('foo'), $kernel->handle(new Request()));
    }

    public function testHandleWhenTheControllerIsAStaticArray()
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher, array('Symfony\Component\HttpKernel\Tests\Controller', 'staticcontroller'));

        $this->assertResponseEquals(new Response('foo'), $kernel->handle(new Request()));
    }

    /**
     * @expectedException \LogicException
     */
    public function testHandleWhenTheControllerDoesNotReturnAResponse()
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher, function () { return 'foo'; });

        $kernel->handle(new Request());
    }

    public function testHandleWhenTheControllerDoesNotReturnAResponseButAViewIsRegistered()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::VIEW, function ($event) {
            $event->setResponse(new Response($event->getControllerResult()));
        });

        $kernel = $this->getHttpKernel($dispatcher, function () { return 'foo'; });

        $this->assertEquals('foo', $kernel->handle(new Request())->getContent());
    }

    public function testHandleWithAResponseListener()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::RESPONSE, function ($event) {
            $event->setResponse(new Response('foo'));
        });
        $kernel = $this->getHttpKernel($dispatcher);

        $this->assertEquals('foo', $kernel->handle(new Request())->getContent());
    }

    public function testHandleAllowChangingControllerArguments()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::CONTROLLER_ARGUMENTS, function (FilterControllerArgumentsEvent $event) {
            $event->setArguments(array('foo'));
        });

        $kernel = $this->getHttpKernel($dispatcher, function ($content) { return new Response($content); });

        $this->assertResponseEquals(new Response('foo'), $kernel->handle(new Request()));
    }

    public function testHandleAllowChangingControllerAndArguments()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::CONTROLLER_ARGUMENTS, function (FilterControllerArgumentsEvent $event) {
            $oldController = $event->getController();
            $oldArguments = $event->getArguments();

            $newController = function ($id) use ($oldController, $oldArguments) {
                $response = call_user_func_array($oldController, $oldArguments);

                $response->headers->set('X-Id', $id);

                return $response;
            };

            $event->setController($newController);
            $event->setArguments(array('bar'));
        });

        $kernel = $this->getHttpKernel($dispatcher, function ($content) { return new Response($content); }, null, array('foo'));

        $this->assertResponseEquals(new Response('foo', 200, array('X-Id' => 'bar')), $kernel->handle(new Request()));
    }

    public function testTerminate()
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher);
        $dispatcher->addListener(KernelEvents::TERMINATE, function ($event) use (&$called, &$capturedKernel, &$capturedRequest, &$capturedResponse) {
            $called = true;
            $capturedKernel = $event->getKernel();
            $capturedRequest = $event->getRequest();
            $capturedResponse = $event->getResponse();
        });

        $kernel->terminate($request = Request::create('/'), $response = new Response());
        $this->assertTrue($called);
        $this->assertEquals($kernel, $capturedKernel);
        $this->assertEquals($request, $capturedRequest);
        $this->assertEquals($response, $capturedResponse);
    }

    public function testVerifyRequestStackPushPopDuringHandle()
    {
        $request = new Request();

        $stack = $this->getMock('Symfony\Component\HttpFoundation\RequestStack', array('push', 'pop'));
        $stack->expects($this->at(0))->method('push')->with($this->equalTo($request));
        $stack->expects($this->at(1))->method('pop');

        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher, null, $stack);

        $kernel->handle($request, HttpKernelInterface::MASTER_REQUEST);
    }

    /**
     * @expectedException Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     */
    public function testInconsistentClientIpsOnMasterRequests()
    {
        $request = new Request();
        $request->setTrustedProxies(array('1.1.1.1'));
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        $request->headers->set('FORWARDED', '2.2.2.2');
        $request->headers->set('X_FORWARDED_FOR', '3.3.3.3');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::REQUEST, function ($event) {
            $event->getRequest()->getClientIp();
        });

        $kernel = $this->getHttpKernel($dispatcher);
        $kernel->handle($request, $kernel::MASTER_REQUEST, false);
    }

    private function getHttpKernel(EventDispatcherInterface $eventDispatcher, $controller = null, RequestStack $requestStack = null, array $arguments = array())
    {
        if (null === $controller) {
            $controller = function () { return new Response('Hello'); };
        }

        $controllerResolver = $this->getMock(ControllerResolverInterface::class);
        $controllerResolver
            ->expects($this->any())
            ->method('getController')
            ->will($this->returnValue($controller));

        $argumentResolver = $this->getMock(ArgumentResolverInterface::class);
        $argumentResolver
            ->expects($this->any())
            ->method('getArguments')
            ->will($this->returnValue($arguments));

        return new HttpKernel($eventDispatcher, $controllerResolver, $requestStack, $argumentResolver);
    }

    private function assertResponseEquals(Response $expected, Response $actual)
    {
        $expected->setDate($actual->getDate());
        $this->assertEquals($expected, $actual);
    }
}

class Controller
{
    public function __invoke()
    {
        return new Response('foo');
    }

    public function controller()
    {
        return new Response('foo');
    }

    public static function staticController()
    {
        return new Response('foo');
    }
}

function controller_func()
{
    return new Response('foo');
}
