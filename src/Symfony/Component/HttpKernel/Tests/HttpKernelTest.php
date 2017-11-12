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

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\EventDispatcher\EventDispatcher;

class HttpKernelTest extends TestCase
{
    /**
     * @expectedException \RuntimeException
     */
    public function testHandleWhenControllerThrowsAnExceptionAndCatchIsTrue(): void
    {
        $kernel = $this->getHttpKernel(new EventDispatcher(), function (): void { throw new \RuntimeException(); });

        $kernel->handle(new Request(), HttpKernelInterface::MASTER_REQUEST, true);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testHandleWhenControllerThrowsAnExceptionAndCatchIsFalseAndNoListenerIsRegistered(): void
    {
        $kernel = $this->getHttpKernel(new EventDispatcher(), function (): void { throw new \RuntimeException(); });

        $kernel->handle(new Request(), HttpKernelInterface::MASTER_REQUEST, false);
    }

    public function testHandleWhenControllerThrowsAnExceptionAndCatchIsTrueWithAHandlingListener(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::EXCEPTION, function ($event): void {
            $event->setResponse(new Response($event->getException()->getMessage()));
        });

        $kernel = $this->getHttpKernel($dispatcher, function (): void { throw new \RuntimeException('foo'); });
        $response = $kernel->handle(new Request(), HttpKernelInterface::MASTER_REQUEST, true);

        $this->assertEquals('500', $response->getStatusCode());
        $this->assertEquals('foo', $response->getContent());
    }

    public function testHandleWhenControllerThrowsAnExceptionAndCatchIsTrueWithANonHandlingListener(): void
    {
        $exception = new \RuntimeException();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::EXCEPTION, function ($event): void {
            // should set a response, but does not
        });

        $kernel = $this->getHttpKernel($dispatcher, function () use ($exception): void { throw $exception; });

        try {
            $kernel->handle(new Request(), HttpKernelInterface::MASTER_REQUEST, true);
            $this->fail('LogicException expected');
        } catch (\RuntimeException $e) {
            $this->assertSame($exception, $e);
        }
    }

    public function testHandleExceptionWithARedirectionResponse(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::EXCEPTION, function ($event): void {
            $event->setResponse(new RedirectResponse('/login', 301));
        });

        $kernel = $this->getHttpKernel($dispatcher, function (): void { throw new AccessDeniedHttpException(); });
        $response = $kernel->handle(new Request());

        $this->assertEquals('301', $response->getStatusCode());
        $this->assertEquals('/login', $response->headers->get('Location'));
    }

    public function testHandleHttpException(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::EXCEPTION, function ($event): void {
            $event->setResponse(new Response($event->getException()->getMessage()));
        });

        $kernel = $this->getHttpKernel($dispatcher, function (): void { throw new MethodNotAllowedHttpException(array('POST')); });
        $response = $kernel->handle(new Request());

        $this->assertEquals('405', $response->getStatusCode());
        $this->assertEquals('POST', $response->headers->get('Allow'));
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

    /**
     * @dataProvider getSpecificStatusCodes
     */
    public function testHandleWhenAnExceptionIsHandledWithASpecificStatusCode($expectedStatusCode): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::EXCEPTION, function (GetResponseForExceptionEvent $event) use ($expectedStatusCode): void {
            $event->allowCustomResponseCode();
            $event->setResponse(new Response('', $expectedStatusCode));
        });

        $kernel = $this->getHttpKernel($dispatcher, function (): void { throw new \RuntimeException(); });
        $response = $kernel->handle(new Request());

        $this->assertEquals($expectedStatusCode, $response->getStatusCode());
    }

    public function getSpecificStatusCodes()
    {
        return array(
            array(200),
            array(302),
            array(403),
        );
    }

    public function testHandleWhenAListenerReturnsAResponse(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::REQUEST, function ($event): void {
            $event->setResponse(new Response('hello'));
        });

        $kernel = $this->getHttpKernel($dispatcher);

        $this->assertEquals('hello', $kernel->handle(new Request())->getContent());
    }

    /**
     * @expectedException \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function testHandleWhenNoControllerIsFound(): void
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher, false);

        $kernel->handle(new Request());
    }

    public function testHandleWhenTheControllerIsAClosure(): void
    {
        $response = new Response('foo');
        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher, function () use ($response) { return $response; });

        $this->assertSame($response, $kernel->handle(new Request()));
    }

    public function testHandleWhenTheControllerIsAnObjectWithInvoke(): void
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher, new Controller());

        $this->assertResponseEquals(new Response('foo'), $kernel->handle(new Request()));
    }

    public function testHandleWhenTheControllerIsAFunction(): void
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher, 'Symfony\Component\HttpKernel\Tests\controller_func');

        $this->assertResponseEquals(new Response('foo'), $kernel->handle(new Request()));
    }

    public function testHandleWhenTheControllerIsAnArray(): void
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher, array(new Controller(), 'controller'));

        $this->assertResponseEquals(new Response('foo'), $kernel->handle(new Request()));
    }

    public function testHandleWhenTheControllerIsAStaticArray(): void
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher, array('Symfony\Component\HttpKernel\Tests\Controller', 'staticcontroller'));

        $this->assertResponseEquals(new Response('foo'), $kernel->handle(new Request()));
    }

    /**
     * @expectedException \LogicException
     */
    public function testHandleWhenTheControllerDoesNotReturnAResponse(): void
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher, function () { return 'foo'; });

        $kernel->handle(new Request());
    }

    public function testHandleWhenTheControllerDoesNotReturnAResponseButAViewIsRegistered(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::VIEW, function ($event): void {
            $event->setResponse(new Response($event->getControllerResult()));
        });

        $kernel = $this->getHttpKernel($dispatcher, function () { return 'foo'; });

        $this->assertEquals('foo', $kernel->handle(new Request())->getContent());
    }

    public function testHandleWithAResponseListener(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::RESPONSE, function ($event): void {
            $event->setResponse(new Response('foo'));
        });
        $kernel = $this->getHttpKernel($dispatcher);

        $this->assertEquals('foo', $kernel->handle(new Request())->getContent());
    }

    public function testHandleAllowChangingControllerArguments(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::CONTROLLER_ARGUMENTS, function (FilterControllerArgumentsEvent $event): void {
            $event->setArguments(array('foo'));
        });

        $kernel = $this->getHttpKernel($dispatcher, function ($content) { return new Response($content); });

        $this->assertResponseEquals(new Response('foo'), $kernel->handle(new Request()));
    }

    public function testHandleAllowChangingControllerAndArguments(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::CONTROLLER_ARGUMENTS, function (FilterControllerArgumentsEvent $event): void {
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

    public function testTerminate(): void
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher);
        $dispatcher->addListener(KernelEvents::TERMINATE, function ($event) use (&$called, &$capturedKernel, &$capturedRequest, &$capturedResponse): void {
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

    public function testVerifyRequestStackPushPopDuringHandle(): void
    {
        $request = new Request();

        $stack = $this->getMockBuilder('Symfony\Component\HttpFoundation\RequestStack')->setMethods(array('push', 'pop'))->getMock();
        $stack->expects($this->at(0))->method('push')->with($this->equalTo($request));
        $stack->expects($this->at(1))->method('pop');

        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher, null, $stack);

        $kernel->handle($request, HttpKernelInterface::MASTER_REQUEST);
    }

    /**
     * @expectedException \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     */
    public function testInconsistentClientIpsOnMasterRequests(): void
    {
        $request = new Request();
        $request->setTrustedProxies(array('1.1.1.1'), Request::HEADER_X_FORWARDED_FOR | Request::HEADER_FORWARDED);
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        $request->headers->set('FORWARDED', 'for=2.2.2.2');
        $request->headers->set('X_FORWARDED_FOR', '3.3.3.3');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::REQUEST, function ($event): void {
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

        $controllerResolver = $this->getMockBuilder(ControllerResolverInterface::class)->getMock();
        $controllerResolver
            ->expects($this->any())
            ->method('getController')
            ->will($this->returnValue($controller));

        $argumentResolver = $this->getMockBuilder(ArgumentResolverInterface::class)->getMock();
        $argumentResolver
            ->expects($this->any())
            ->method('getArguments')
            ->will($this->returnValue($arguments));

        return new HttpKernel($eventDispatcher, $controllerResolver, $requestStack, $argumentResolver);
    }

    private function assertResponseEquals(Response $expected, Response $actual): void
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
