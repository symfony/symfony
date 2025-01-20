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
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ControllerDoesNotReturnResponseException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class HttpKernelTest extends TestCase
{
    /**
     * Catch exceptions: true
     * Throwable type: RuntimeException
     * Listener: false.
     */
    public function testHandleWhenControllerThrowsAnExceptionAndCatchIsTrue()
    {
        $this->expectException(\RuntimeException::class);
        $kernel = $this->getHttpKernel(new EventDispatcher(), static fn () => throw new \RuntimeException());

        $kernel->handle(new Request(), HttpKernelInterface::MAIN_REQUEST, true);
    }

    public function testRequestStackIsNotBrokenWhenControllerThrowsAnExceptionAndCatchIsTrue()
    {
        $requestStack = new RequestStack();
        $kernel = $this->getHttpKernel(new EventDispatcher(), static fn () => throw new \RuntimeException(), $requestStack);

        try {
            $kernel->handle(new Request(), HttpKernelInterface::MAIN_REQUEST, true);
        } catch (\Throwable $exception) {
        }

        self::assertNull($requestStack->getCurrentRequest());
    }

    public function testRequestStackIsNotBrokenWhenControllerThrowsAnExceptionAndCatchIsFalse()
    {
        $requestStack = new RequestStack();
        $kernel = $this->getHttpKernel(new EventDispatcher(), static fn () => throw new \RuntimeException(), $requestStack);

        try {
            $kernel->handle(new Request(), HttpKernelInterface::MAIN_REQUEST, false);
        } catch (\Throwable $exception) {
        }

        self::assertNull($requestStack->getCurrentRequest());
    }

    public function testRequestStackIsNotBrokenWhenControllerThrowsAnThrowable()
    {
        $requestStack = new RequestStack();
        $kernel = $this->getHttpKernel(new EventDispatcher(), static fn () => throw new \Error(), $requestStack);

        try {
            $kernel->handle(new Request(), HttpKernelInterface::MAIN_REQUEST, true);
        } catch (\Throwable $exception) {
        }

        self::assertNull($requestStack->getCurrentRequest());
    }

    /**
     * Catch exceptions: false
     * Throwable type: RuntimeException
     * Listener: false.
     */
    public function testHandleWhenControllerThrowsAnExceptionAndCatchIsFalseAndNoListenerIsRegistered()
    {
        $this->expectException(\RuntimeException::class);
        $kernel = $this->getHttpKernel(new EventDispatcher(), static fn () => throw new \RuntimeException());

        $kernel->handle(new Request(), HttpKernelInterface::MAIN_REQUEST, false);
    }

    /**
     * Catch exceptions: true
     * Throwable type: RuntimeException
     * Listener: true.
     */
    public function testHandleWhenControllerThrowsAnExceptionAndCatchIsTrueWithAHandlingListener()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::EXCEPTION, function ($event) {
            $event->setResponse(new Response($event->getThrowable()->getMessage()));
        });

        $kernel = $this->getHttpKernel($dispatcher, static fn () => throw new \RuntimeException('foo'));
        $response = $kernel->handle(new Request(), HttpKernelInterface::MAIN_REQUEST, true);

        $this->assertEquals('500', $response->getStatusCode());
        $this->assertEquals('foo', $response->getContent());
    }

    /**
     * Catch exceptions: true
     * Throwable type: TypeError
     * Listener: true.
     */
    public function testHandleWhenControllerThrowsAThrowableAndCatchIsTrueWithAHandlingListener()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::EXCEPTION, function ($event) {
            $event->setResponse(new Response($event->getThrowable()->getMessage()));
        });

        $kernel = $this->getHttpKernel($dispatcher, static fn () => throw new \TypeError('foo'), handleAllThrowables: true);
        $response = $kernel->handle(new Request(), HttpKernelInterface::MAIN_REQUEST, true);

        $this->assertEquals('500', $response->getStatusCode());
        $this->assertEquals('foo', $response->getContent());
    }

    /**
     * Catch exceptions: false
     * Throwable type: TypeError
     * Listener: true.
     */
    public function testHandleWhenControllerThrowsAThrowableAndCatchIsFalseWithAHandlingListener()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::EXCEPTION, function ($event) {
            $event->setResponse(new Response($event->getThrowable()->getMessage()));
        });

        $kernel = $this->getHttpKernel($dispatcher, static fn () => throw new \TypeError('foo'), handleAllThrowables: true);
        $this->expectException(\TypeError::class);
        $kernel->handle(new Request(), HttpKernelInterface::MAIN_REQUEST, false);
    }

    /**
     * Catch exceptions: true
     * Throwable type: TypeError
     * Listener: true.
     */
    public function testHandleWhenControllerThrowsAThrowableAndCatchIsTrueNotHandlingThrowables()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::EXCEPTION, function ($event) {
            $event->setResponse(new Response($event->getThrowable()->getMessage()));
        });

        $controllerResolver = $this->createMock(ControllerResolverInterface::class);
        $controllerResolver
            ->expects($this->any())
            ->method('getController')
            ->willReturn(static fn () => throw new \TypeError('foo'));

        $argumentResolver = $this->createMock(ArgumentResolverInterface::class);
        $argumentResolver
            ->expects($this->any())
            ->method('getArguments')
            ->willReturn([]);

        $kernel = new HttpKernel($dispatcher, $controllerResolver, null, $argumentResolver);

        $this->expectException(\TypeError::class);
        $kernel->handle(new Request(), HttpKernelInterface::MAIN_REQUEST, true);
    }

    /**
     * Catch exceptions: true
     * Throwable type: RuntimeException
     * Listener: true.
     */
    public function testHandleWhenControllerThrowsAnExceptionAndCatchIsTrueWithANonHandlingListener()
    {
        $exception = new \RuntimeException();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::EXCEPTION, function ($event) {
            // should set a response, but does not
        });

        $kernel = $this->getHttpKernel($dispatcher, static fn () => throw $exception);

        try {
            $kernel->handle(new Request(), HttpKernelInterface::MAIN_REQUEST, true);
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

        $kernel = $this->getHttpKernel($dispatcher, static fn () => throw new AccessDeniedHttpException());
        $response = $kernel->handle(new Request());

        $this->assertEquals('301', $response->getStatusCode());
        $this->assertEquals('/login', $response->headers->get('Location'));
    }

    public function testHandleHttpException()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::EXCEPTION, function ($event) {
            $event->setResponse(new Response($event->getThrowable()->getMessage()));
        });

        $kernel = $this->getHttpKernel($dispatcher, static fn () => throw new MethodNotAllowedHttpException(['POST']));
        $response = $kernel->handle(new Request());

        $this->assertEquals('405', $response->getStatusCode());
        $this->assertEquals('POST', $response->headers->get('Allow'));
    }

    public function getStatusCodes()
    {
        return [
            [200, 404],
            [404, 200],
            [301, 200],
            [500, 200],
        ];
    }

    /**
     * @dataProvider getSpecificStatusCodes
     */
    public function testHandleWhenAnExceptionIsHandledWithASpecificStatusCode($expectedStatusCode)
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::EXCEPTION, function (ExceptionEvent $event) use ($expectedStatusCode) {
            $event->allowCustomResponseCode();
            $event->setResponse(new Response('', $expectedStatusCode));
        });

        $kernel = $this->getHttpKernel($dispatcher, static fn () => throw new \RuntimeException());
        $response = $kernel->handle(new Request());

        $this->assertEquals($expectedStatusCode, $response->getStatusCode());
    }

    public static function getSpecificStatusCodes()
    {
        return [
            [200],
            [302],
            [403],
        ];
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

    public function testHandleWhenNoControllerIsFound()
    {
        $this->expectException(NotFoundHttpException::class);
        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher, false);

        $kernel->handle(new Request());
    }

    public function testHandleWhenTheControllerIsAClosure()
    {
        $response = new Response('foo');
        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher, fn () => $response);

        $this->assertSame($response, $kernel->handle(new Request()));
    }

    public function testHandleWhenTheControllerIsAnObjectWithInvoke()
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher, new TestController());

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
        $kernel = $this->getHttpKernel($dispatcher, [new TestController(), 'controller']);

        $this->assertResponseEquals(new Response('foo'), $kernel->handle(new Request()));
    }

    public function testHandleWhenTheControllerIsAStaticArray()
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher, ['Symfony\Component\HttpKernel\Tests\TestController', 'staticcontroller']);

        $this->assertResponseEquals(new Response('foo'), $kernel->handle(new Request()));
    }

    public function testHandleWhenTheControllerDoesNotReturnAResponse()
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher, static fn () => null);

        try {
            $kernel->handle(new Request());

            $this->fail('The kernel should throw an exception.');
        } catch (ControllerDoesNotReturnResponseException $e) {
            $first = $e->getTrace()[0];

            // `file` index the array starting at 0, and __FILE__ starts at 1
            $line = file($first['file'])[$first['line'] - 2];
            $this->assertStringContainsString('// call controller', $line);
        }
    }

    public function testHandleWhenTheControllerDoesNotReturnAResponseButAViewIsRegistered()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::VIEW, function ($event) {
            $event->setResponse(new Response($event->getControllerResult()));
        });

        $kernel = $this->getHttpKernel($dispatcher, fn () => 'foo');

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
        $dispatcher->addListener(KernelEvents::CONTROLLER_ARGUMENTS, function ($event) {
            $event->setArguments(['foo']);
        });

        $kernel = $this->getHttpKernel($dispatcher, fn ($content) => new Response($content));

        $this->assertResponseEquals(new Response('foo'), $kernel->handle(new Request()));
    }

    public function testHandleAllowChangingControllerAndArguments()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::CONTROLLER_ARGUMENTS, function ($event) {
            $oldController = $event->getController();
            $oldArguments = $event->getArguments();

            $newController = function ($id) use ($oldController, $oldArguments) {
                $response = $oldController(...$oldArguments);

                $response->headers->set('X-Id', $id);

                return $response;
            };

            $event->setController($newController);
            $event->setArguments(['bar']);
        });

        $kernel = $this->getHttpKernel($dispatcher, fn ($content) => new Response($content), null, ['foo']);

        $this->assertResponseEquals(new Response('foo', 200, ['X-Id' => 'bar']), $kernel->handle(new Request()));
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

    public function testTerminateWithException()
    {
        $dispatcher = new EventDispatcher();
        $requestStack = new RequestStack();
        $kernel = $this->getHttpKernel($dispatcher, null, $requestStack);

        $dispatcher->addListener(KernelEvents::EXCEPTION, function (ExceptionEvent $event) use (&$capturedRequest, $requestStack) {
            $capturedRequest = $requestStack->getCurrentRequest();
            $event->setResponse(new Response());
        });

        $kernel->terminateWithException(new \Exception('boo'), $request = Request::create('/'));
        $this->assertSame($request, $capturedRequest);
        $this->assertNull($requestStack->getCurrentRequest());
    }

    public function testVerifyRequestStackPushPopDuringHandle()
    {
        $request = new Request();

        $stack = $this->getMockBuilder(RequestStack::class)->onlyMethods(['push', 'pop'])->getMock();
        $stack->expects($this->once())->method('push')->with($this->equalTo($request));
        $stack->expects($this->once())->method('pop');

        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher, null, $stack);

        $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST);
    }

    public function testVerifyRequestStackPushPopWithStreamedResponse()
    {
        $request = new Request();
        $stack = new RequestStack();
        $dispatcher = new EventDispatcher();
        $kernel = $this->getHttpKernel($dispatcher, fn () => new StreamedResponse(function () use ($stack) {
            echo $stack->getMainRequest()::class;
        }), $stack);

        $response = $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST);
        self::assertNull($stack->getMainRequest());
        ob_start();
        $response->send();
        self::assertSame(Request::class, ob_get_clean());
        self::assertNull($stack->getMainRequest());
    }

    public function testInconsistentClientIpsOnMainRequests()
    {
        $this->expectException(BadRequestHttpException::class);
        $request = new Request();
        $request->setTrustedProxies(['1.1.1.1'], Request::HEADER_X_FORWARDED_FOR | Request::HEADER_FORWARDED);
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        $request->headers->set('FORWARDED', 'for=2.2.2.2');
        $request->headers->set('X_FORWARDED_FOR', '3.3.3.3');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::REQUEST, function ($event) {
            $event->getRequest()->getClientIp();
        });

        $kernel = $this->getHttpKernel($dispatcher);
        $kernel->handle($request, $kernel::MAIN_REQUEST, false);

        Request::setTrustedProxies([], -1);
    }

    private function getHttpKernel(EventDispatcherInterface $eventDispatcher, $controller = null, ?RequestStack $requestStack = null, array $arguments = [], bool $handleAllThrowables = false)
    {
        $controller ??= fn () => new Response('Hello');

        $controllerResolver = $this->createMock(ControllerResolverInterface::class);
        $controllerResolver
            ->expects($this->any())
            ->method('getController')
            ->willReturn($controller);

        $argumentResolver = $this->createMock(ArgumentResolverInterface::class);
        $argumentResolver
            ->expects($this->any())
            ->method('getArguments')
            ->willReturn($arguments);

        return new HttpKernel($eventDispatcher, $controllerResolver, $requestStack, $argumentResolver, $handleAllThrowables);
    }

    private function assertResponseEquals(Response $expected, Response $actual)
    {
        $expected->setDate($actual->getDate());
        $this->assertEquals($expected, $actual);
    }
}

class TestController
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
