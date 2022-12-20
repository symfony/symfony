<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\EventListener\ErrorListener;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\HttpKernel\EventListener\ValidateRequestListener;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\NoConfigurationException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RequestContext;

class RouterListenerTest extends TestCase
{
    private $requestStack;

    protected function setUp(): void
    {
        $this->requestStack = self::createMock(RequestStack::class);
    }

    /**
     * @dataProvider getPortData
     */
    public function testPort($defaultHttpPort, $defaultHttpsPort, $uri, $expectedHttpPort, $expectedHttpsPort)
    {
        $urlMatcher = self::createMock(UrlMatcherInterface::class);

        $context = new RequestContext();
        $context->setHttpPort($defaultHttpPort);
        $context->setHttpsPort($defaultHttpsPort);
        $urlMatcher->expects(self::any())
            ->method('getContext')
            ->willReturn($context);

        $listener = new RouterListener($urlMatcher, $this->requestStack);
        $event = $this->createRequestEventForUri($uri);
        $listener->onKernelRequest($event);

        self::assertEquals($expectedHttpPort, $context->getHttpPort());
        self::assertEquals($expectedHttpsPort, $context->getHttpsPort());
        self::assertEquals(str_starts_with($uri, 'https') ? 'https' : 'http', $context->getScheme());
    }

    public function getPortData()
    {
        return [
            [80, 443, 'http://localhost/', 80, 443],
            [80, 443, 'http://localhost:90/', 90, 443],
            [80, 443, 'https://localhost/', 80, 443],
            [80, 443, 'https://localhost:90/', 80, 90],
        ];
    }

    private function createRequestEventForUri(string $uri): RequestEvent
    {
        $kernel = self::createMock(HttpKernelInterface::class);
        $request = Request::create($uri);
        $request->attributes->set('_controller', null); // Prevents going in to routing process

        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }

    public function testInvalidMatcher()
    {
        self::expectException(\InvalidArgumentException::class);
        new RouterListener(new \stdClass(), $this->requestStack);
    }

    public function testRequestMatcher()
    {
        $kernel = self::createMock(HttpKernelInterface::class);
        $request = Request::create('http://localhost/');
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $requestMatcher = self::createMock(RequestMatcherInterface::class);
        $requestMatcher->expects(self::once())
                       ->method('matchRequest')
                       ->with(self::isInstanceOf(Request::class))
                       ->willReturn([]);

        $listener = new RouterListener($requestMatcher, $this->requestStack, new RequestContext());
        $listener->onKernelRequest($event);
    }

    public function testSubRequestWithDifferentMethod()
    {
        $kernel = self::createMock(HttpKernelInterface::class);
        $request = Request::create('http://localhost/', 'post');
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $requestMatcher = self::createMock(RequestMatcherInterface::class);
        $requestMatcher->expects(self::any())
                       ->method('matchRequest')
                       ->with(self::isInstanceOf(Request::class))
                       ->willReturn([]);

        $context = new RequestContext();

        $listener = new RouterListener($requestMatcher, $this->requestStack, new RequestContext());
        $listener->onKernelRequest($event);

        // sub-request with another HTTP method
        $kernel = self::createMock(HttpKernelInterface::class);
        $request = Request::create('http://localhost/', 'get');
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);

        $listener->onKernelRequest($event);

        self::assertEquals('GET', $context->getMethod());
    }

    /**
     * @dataProvider getLoggingParameterData
     */
    public function testLoggingParameter($parameter, $log, $parameters)
    {
        $requestMatcher = self::createMock(RequestMatcherInterface::class);
        $requestMatcher->expects(self::once())
            ->method('matchRequest')
            ->willReturn($parameter);

        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with(self::equalTo($log), self::equalTo($parameters));

        $kernel = self::createMock(HttpKernelInterface::class);
        $request = Request::create('http://localhost/');

        $listener = new RouterListener($requestMatcher, $this->requestStack, new RequestContext(), $logger);
        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
    }

    public function getLoggingParameterData()
    {
        return [
            [['_route' => 'foo'], 'Matched route "{route}".', ['route' => 'foo', 'route_parameters' => ['_route' => 'foo'], 'request_uri' => 'http://localhost/', 'method' => 'GET']],
            [[], 'Matched route "{route}".', ['route' => 'n/a', 'route_parameters' => [], 'request_uri' => 'http://localhost/', 'method' => 'GET']],
        ];
    }

    public function testWithBadRequest()
    {
        $requestStack = new RequestStack();

        $requestMatcher = self::createMock(RequestMatcherInterface::class);
        $requestMatcher->expects(self::never())->method('matchRequest');

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new ValidateRequestListener());
        $dispatcher->addSubscriber(new RouterListener($requestMatcher, $requestStack, new RequestContext()));
        $dispatcher->addSubscriber(new ErrorListener(function () {
            return new Response('Exception handled', 400);
        }));

        $kernel = new HttpKernel($dispatcher, new ControllerResolver(), $requestStack, new ArgumentResolver());

        $request = Request::create('http://localhost/');
        $request->headers->set('host', '###');
        $response = $kernel->handle($request);
        self::assertSame(400, $response->getStatusCode());
    }

    public function testNoRoutingConfigurationResponse()
    {
        $requestStack = new RequestStack();

        $requestMatcher = self::createMock(RequestMatcherInterface::class);
        $requestMatcher
            ->expects(self::once())
            ->method('matchRequest')
            ->willThrowException(new NoConfigurationException())
        ;

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new RouterListener($requestMatcher, $requestStack, new RequestContext()));

        $kernel = new HttpKernel($dispatcher, new ControllerResolver(), $requestStack, new ArgumentResolver());

        $request = Request::create('http://localhost/');
        $response = $kernel->handle($request);
        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('Welcome', $response->getContent());
    }

    public function testRequestWithBadHost()
    {
        self::expectException(BadRequestHttpException::class);
        $kernel = self::createMock(HttpKernelInterface::class);
        $request = Request::create('http://bad host %22/');
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $requestMatcher = self::createMock(RequestMatcherInterface::class);

        $listener = new RouterListener($requestMatcher, $this->requestStack, new RequestContext());
        $listener->onKernelRequest($event);
    }

    public function testResourceNotFoundException()
    {
        self::expectException(NotFoundHttpException::class);
        self::expectExceptionMessage('No route found for "GET https://www.symfony.com/path" (from "https://www.google.com")');

        $context = new RequestContext();

        $urlMatcher = self::createMock(UrlMatcherInterface::class);

        $urlMatcher->expects(self::any())
            ->method('getContext')
            ->willReturn($context);

        $urlMatcher->expects(self::any())
            ->method('match')
            ->willThrowException(new ResourceNotFoundException());

        $kernel = self::createMock(HttpKernelInterface::class);
        $request = Request::create('https://www.symfony.com/path');
        $request->headers->set('referer', 'https://www.google.com');

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener = new RouterListener($urlMatcher, $this->requestStack);
        $listener->onKernelRequest($event);
    }

    public function testMethodNotAllowedException()
    {
        self::expectException(MethodNotAllowedHttpException::class);
        self::expectExceptionMessage('No route found for "GET https://www.symfony.com/path": Method Not Allowed (Allow: POST)');

        $context = new RequestContext();

        $urlMatcher = self::createMock(UrlMatcherInterface::class);

        $urlMatcher->expects(self::any())
            ->method('getContext')
            ->willReturn($context);

        $urlMatcher->expects(self::any())
            ->method('match')
            ->willThrowException(new MethodNotAllowedException(['POST']));

        $kernel = self::createMock(HttpKernelInterface::class);
        $request = Request::create('https://www.symfony.com/path');

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener = new RouterListener($urlMatcher, $this->requestStack);
        $listener->onKernelRequest($event);
    }
}
