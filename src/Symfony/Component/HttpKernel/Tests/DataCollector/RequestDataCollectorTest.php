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
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Tests\Fixtures\DataCollector\DummyController;

class RequestDataCollectorTest extends TestCase
{
    public function testCollect()
    {
        $c = new RequestDataCollector();

        $c->collect($request = $this->createRequest(), $this->createResponse());
        $c->lateCollect();

        $attributes = $c->getRequestAttributes();

        $this->assertSame('request', $c->getName());
        $this->assertInstanceOf(ParameterBag::class, $c->getRequestHeaders());
        $this->assertInstanceOf(ParameterBag::class, $c->getRequestServer());
        $this->assertInstanceOf(ParameterBag::class, $c->getRequestCookies());
        $this->assertInstanceOf(ParameterBag::class, $attributes);
        $this->assertInstanceOf(ParameterBag::class, $c->getRequestRequest());
        $this->assertInstanceOf(ParameterBag::class, $c->getRequestQuery());
        $this->assertInstanceOf(ParameterBag::class, $c->getResponseCookies());
        $this->assertSame('html', $c->getFormat());
        $this->assertEquals('foobar', $c->getRoute());
        $this->assertEquals(['name' => 'foo'], $c->getRouteParams());
        $this->assertSame([], $c->getSessionAttributes());
        $this->assertSame('en', $c->getLocale());
        $this->assertContainsEquals(__FILE__, $attributes->get('resource'));
        $this->assertSame('stdClass', $attributes->get('object')->getType());

        $this->assertInstanceOf(ParameterBag::class, $c->getResponseHeaders());
        $this->assertSame('OK', $c->getStatusText());
        $this->assertSame(200, $c->getStatusCode());
        $this->assertSame('application/json', $c->getContentType());
    }

    public function testCollectWithoutRouteParams()
    {
        $request = $this->createRequest([]);

        $c = new RequestDataCollector();
        $c->collect($request, $this->createResponse());
        $c->lateCollect();

        $this->assertEquals([], $c->getRouteParams());
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

    public static function provideControllerCallables(): array
    {
        // make sure we always match the line number
        $controller = new DummyController();

        $r1 = new \ReflectionMethod($controller, 'regularCallable');
        $r2 = new \ReflectionMethod($controller, 'staticControllerMethod');
        $r3 = new \ReflectionClass($controller);

        // test name, callable, expected
        return [
            [
                '"Regular" callable',
                [$controller, 'regularCallable'],
                [
                    'class' => DummyController::class,
                    'method' => 'regularCallable',
                    'file' => $r1->getFileName(),
                    'line' => $r1->getStartLine(),
                ],
            ],

            [
                'Closure',
                fn () => 'foo',
                [
                    'class' => __NAMESPACE__.'\{closure}',
                    'method' => null,
                    'file' => __FILE__,
                    'line' => __LINE__ - 5,
                ],
            ],

            [
                'First-class callable closure',
                $controller->regularCallable(...),
                [
                    'class' => DummyController::class,
                    'method' => 'regularCallable',
                    'file' => $r1->getFileName(),
                    'line' => $r1->getStartLine(),
                ],
            ],

            [
                'Static callback as string',
                DummyController::class.'::staticControllerMethod',
                [
                    'class' => DummyController::class,
                    'method' => 'staticControllerMethod',
                    'file' => $r2->getFileName(),
                    'line' => $r2->getStartLine(),
                ],
            ],

            [
                'Static callable with instance',
                [$controller, 'staticControllerMethod'],
                [
                    'class' => DummyController::class,
                    'method' => 'staticControllerMethod',
                    'file' => $r2->getFileName(),
                    'line' => $r2->getStartLine(),
                ],
            ],

            [
                'Static callable with class name',
                [DummyController::class, 'staticControllerMethod'],
                [
                    'class' => DummyController::class,
                    'method' => 'staticControllerMethod',
                    'file' => $r2->getFileName(),
                    'line' => $r2->getStartLine(),
                ],
            ],

            [
                'Callable with instance depending on __call()',
                [$controller, 'magicMethod'],
                [
                    'class' => DummyController::class,
                    'method' => 'magicMethod',
                    'file' => 'n/a',
                    'line' => 'n/a',
                ],
            ],

            [
                'Callable with class name depending on __callStatic()',
                [DummyController::class, 'magicMethod'],
                [
                    'class' => DummyController::class,
                    'method' => 'magicMethod',
                    'file' => 'n/a',
                    'line' => 'n/a',
                ],
            ],

            [
                'Invokable controller',
                $controller,
                [
                    'class' => DummyController::class,
                    'method' => null,
                    'file' => $r3->getFileName(),
                    'line' => $r3->getStartLine(),
                ],
            ],
        ];
    }

    public function testItIgnoresInvalidCallables()
    {
        $request = $this->createRequestWithSession();
        $response = new RedirectResponse('/');

        $c = new RequestDataCollector();
        $c->collect($request, $response);

        $this->assertSame('n/a', $c->getController());
    }

    public function testItAddsRedirectedAttributesWhenRequestContainsSpecificCookie()
    {
        $request = $this->createRequest();
        $request->cookies->add([
            'sf_redirect' => '{}',
        ]);

        $kernel = $this->createMock(HttpKernelInterface::class);

        $c = new RequestDataCollector();
        $c->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $this->createResponse()));

        $this->assertTrue($request->attributes->get('_redirected'));
    }

    public function testItSetsARedirectCookieIfTheResponseIsARedirection()
    {
        $c = new RequestDataCollector();

        $response = $this->createResponse();
        $response->setStatusCode(302);
        $response->headers->set('Location', '/somewhere-else');

        $c->collect($request = $this->createRequest(), $response);
        $c->lateCollect();

        $cookie = $this->getCookieByName($response, 'sf_redirect');

        $this->assertNotEmpty($cookie->getValue());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertFalse($cookie->isSecure());
    }

    public function testItCollectsTheRedirectionAndClearTheCookie()
    {
        $c = new RequestDataCollector();

        $request = $this->createRequest();
        $request->attributes->set('_redirected', true);
        $request->cookies->add([
            'sf_redirect' => '{"method": "POST"}',
        ]);

        $c->collect($request, $response = $this->createResponse());
        $c->lateCollect();

        $this->assertEquals('POST', $c->getRedirect()['method']);

        $cookie = $this->getCookieByName($response, 'sf_redirect');
        $this->assertNull($cookie->getValue());
    }

    public function testItCollectsTheSessionTraceProperly()
    {
        $collector = new RequestDataCollector();
        $request = $this->createRequest();

        // RequestDataCollectorTest doesn't implement SessionInterface or SessionBagInterface, therefore should do nothing.
        $collector->collectSessionUsage();

        $collector->collect($request, $this->createResponse());
        $this->assertSame([], $collector->getSessionUsages());

        $collector->reset();

        $session = $this->createMock(SessionInterface::class);
        $session->method('getMetadataBag')->willReturnCallback(static function () use ($collector) {
            $collector->collectSessionUsage();

            return new MetadataBag();
        });
        $session->getMetadataBag();

        $collector->collect($request, $this->createResponse());
        $collector->lateCollect();

        $usages = $collector->getSessionUsages();

        $this->assertCount(1, $usages);
        $this->assertSame(__FILE__, $usages[0]['file']);
        $this->assertSame(__LINE__ - 9, $line = $usages[0]['line']);

        $trace = $usages[0]['trace'];
        $this->assertSame('getMetadataBag', $trace[0]['function']);
        $this->assertSame(self::class, $class = $trace[1]['class']);

        $this->assertSame(sprintf('%s:%s', $class, $line), $usages[0]['name']);
    }

    public function testStatelessCheck()
    {
        $requestStack = new RequestStack();
        $request = $this->createRequest();
        $requestStack->push($request);

        $collector = new RequestDataCollector($requestStack);
        $collector->collect($request, $response = $this->createResponse());
        $collector->lateCollect();

        $this->assertFalse($collector->getStatelessCheck());

        $requestStack = new RequestStack();
        $request = $this->createRequest();
        $request->attributes->set('_stateless', true);
        $requestStack->push($request);

        $collector = new RequestDataCollector($requestStack);
        $collector->collect($request, $response = $this->createResponse());
        $collector->lateCollect();

        $this->assertTrue($collector->getStatelessCheck());

        $requestStack = new RequestStack();
        $request = $this->createRequest();

        $collector = new RequestDataCollector($requestStack);
        $collector->collect($request, $response = $this->createResponse());
        $collector->lateCollect();

        $this->assertFalse($collector->getStatelessCheck());
    }

    public function testItHidesPassword()
    {
        $c = new RequestDataCollector();

        $request = Request::create(
            'http://test.com/login',
            'POST',
            ['_password' => ' _password@123'],
            [],
            [],
            [],
            '_password=%20_password%40123'
        );

        $c->collect($request, $this->createResponse());
        $c->lateCollect();

        $this->assertEquals('******', $c->getRequestRequest()->get('_password'));
        $this->assertEquals('_password=******', $c->getContent());
    }

    protected function createRequest($routeParams = ['name' => 'foo'])
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
        $response->headers->setCookie(new Cookie('foo', 'bar', 1, '/foo', 'localhost', true, true, false, null));
        $response->headers->setCookie(new Cookie('bar', 'foo', new \DateTimeImmutable('@946684800'), '/', null, false, true, false, null));
        $response->headers->setCookie(new Cookie('bazz', 'foo', '2000-12-12', '/', null, false, true, false, null));

        return $response;
    }

    /**
     * Inject the given controller callable into the data collector.
     */
    protected function injectController($collector, $controller, $request)
    {
        $resolver = $this->createMock(ControllerResolverInterface::class);
        $httpKernel = new HttpKernel(new EventDispatcher(), $resolver, null, $this->createMock(ArgumentResolverInterface::class));
        $event = new ControllerEvent($httpKernel, $controller, $request, HttpKernelInterface::MAIN_REQUEST);
        $collector->onKernelController($event);
    }

    private function getCookieByName(Response $response, $name)
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() == $name) {
                return $cookie;
            }
        }

        throw new \InvalidArgumentException(sprintf('Cookie named "%s" is not in response', $name));
    }

    /**
     * @dataProvider provideJsonContentTypes
     */
    public function testIsJson($contentType, $expected)
    {
        $response = $this->createResponse();
        $request = $this->createRequest();
        $request->headers->set('Content-Type', $contentType);

        $c = new RequestDataCollector();
        $c->collect($request, $response);

        $this->assertSame($expected, $c->isJsonRequest());
    }

    public static function provideJsonContentTypes(): array
    {
        return [
            ['text/csv', false],
            ['application/json', true],
            ['application/JSON', true],
            ['application/hal+json', true],
            ['application/xml+json', true],
            ['application/xml', false],
            ['', false],
        ];
    }

    /**
     * @dataProvider providePrettyJson
     */
    public function testGetPrettyJsonValidity($content, $expected)
    {
        $response = $this->createResponse();
        $request = Request::create('/', 'POST', [], [], [], [], $content);

        $c = new RequestDataCollector();
        $c->collect($request, $response);

        $this->assertSame($expected, $c->getPrettyJson());
    }

    public static function providePrettyJson(): array
    {
        return [
            ['null', 'null'],
            ['{ "foo": "bar" }', '{
    "foo": "bar"
}'],
            ['{ "abc" }', null],
            ['', null],
        ];
    }
}
