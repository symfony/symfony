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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\HttpKernel\Attribute\RateLimit;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerAttributeEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\CacheAttributeListener;
use Symfony\Component\HttpKernel\EventListener\ControllerAttributesListener;
use Symfony\Component\HttpKernel\EventListener\ErrorListener;
use Symfony\Component\HttpKernel\EventListener\RateLimitAttributeListener;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpCache\HttpCache;
use Symfony\Component\HttpKernel\HttpCache\Store;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Tests\HttpCache\HttpCacheTestCase;
use Symfony\Component\RateLimiter\Event\RateLimitExceededEvent;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\Policy\NoLimiter;
use Symfony\Component\RateLimiter\RateLimit as RateLimitResult;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Contracts\Service\ServiceProviderInterface;

class RateLimitAttributeListenerTest extends TestCase
{
    private function makeListener(bool $accept = true): RateLimitAttributeListener
    {
        $result = new RateLimitResult($accept ? 4 : 0, new \DateTimeImmutable($accept ? 'now' : '+1 minute'), $accept, 5, new \DateTimeImmutable('+1 minute'));

        $limiter = $this->createStub(LimiterInterface::class);
        $limiter->method('consume')->willReturn($result);

        $factory = $this->createStub(RateLimiterFactoryInterface::class);
        $factory->method('create')->willReturn($limiter);

        $locator = $this->createStub(ServiceProviderInterface::class);
        $locator->method('has')->willReturn(true);
        $locator->method('get')->willReturn($factory);
        $locator->method('getProvidedServices')->willReturn(['api' => RateLimiterFactoryInterface::class]);

        return new RateLimitAttributeListener($locator);
    }

    private function makeListenerCapturingKey(?string &$usedKey): RateLimitAttributeListener
    {
        $result = new RateLimitResult(4, new \DateTimeImmutable('now'), true, 5, new \DateTimeImmutable('+1 minute'));

        $limiter = $this->createStub(LimiterInterface::class);
        $limiter->method('consume')->willReturn($result);

        $factory = $this->createStub(RateLimiterFactoryInterface::class);
        $factory->method('create')->willReturnCallback(static function (string $key) use ($limiter, &$usedKey) {
            $usedKey = $key;

            return $limiter;
        });

        $locator = $this->createStub(ServiceProviderInterface::class);
        $locator->method('has')->willReturn(true);
        $locator->method('get')->willReturn($factory);
        $locator->method('getProvidedServices')->willReturn(['api' => RateLimiterFactoryInterface::class]);

        return new RateLimitAttributeListener($locator);
    }

    private function makeEvent(RateLimit $attribute, Request $request, ?ExpressionLanguage $el = null): ControllerAttributeEvent
    {
        return new ControllerAttributeEvent($attribute, new ControllerArgumentsEvent(
            $this->createStub(HttpKernelInterface::class),
            static fn () => null,
            [],
            $request,
            null,
        ), $el);
    }

    private function makeResponseEvent(Request $request, Response $response, int $requestType = HttpKernelInterface::MAIN_REQUEST): ResponseEvent
    {
        return new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, $requestType, $response);
    }

    public function testAccepted()
    {
        $this->makeListener()->onKernelControllerAttribute($this->makeEvent(new RateLimit('api'), Request::create('/')));
        $this->addToAssertionCount(1);
    }

    public function testAcceptedSetsRateLimitHeadersOnResponse()
    {
        $listener = $this->makeListener();
        $request = Request::create('/');

        $listener->onKernelControllerAttribute($this->makeEvent(new RateLimit('api', exposeHeaders: true), $request));

        $response = new Response();
        $listener->onKernelResponse($this->makeResponseEvent($request, $response));

        $this->assertSame('5', $response->headers->get('X-RateLimit-Limit'));
        $this->assertSame('4', $response->headers->get('X-RateLimit-Remaining'));
        $this->assertThat((int) $response->headers->get('X-RateLimit-Reset'), $this->logicalAnd($this->greaterThan(time() + 50), $this->lessThanOrEqual(time() + 60)));
    }

    public function testAddingHeadersMarksResponsePrivate()
    {
        $listener = $this->makeListener();
        $request = Request::create('/');

        $listener->onKernelControllerAttribute($this->makeEvent(new RateLimit('api', exposeHeaders: true), $request));

        $response = new Response();
        $response->setPublic();
        $response->setMaxAge(3600);

        $listener->onKernelResponse($this->makeResponseEvent($request, $response));

        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertFalse($response->headers->hasCacheControlDirective('public'));
    }

    public function testResponseWithoutHeadersKeepsItsCacheControl()
    {
        $listener = $this->makeListener();
        $response = new Response();
        $response->setPublic();

        $listener->onKernelResponse($this->makeResponseEvent(Request::create('/'), $response));

        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
    }

    public function testRealFixedWindowLimiterHeaderSequenceAcrossCalls()
    {
        $factory = new RateLimiterFactory([
            'id' => 'api',
            'policy' => 'fixed_window',
            'limit' => 5,
            'interval' => '1 minute',
        ], new InMemoryStorage());

        $locator = $this->createStub(ServiceProviderInterface::class);
        $locator->method('has')->willReturn(true);
        $locator->method('get')->willReturn($factory);
        $locator->method('getProvidedServices')->willReturn(['api' => RateLimiterFactoryInterface::class]);

        $listener = new RateLimitAttributeListener($locator);
        $resetAt = null;

        foreach ([4, 3, 2, 1, 0] as $remaining) {
            $request = Request::create('/', server: ['REMOTE_ADDR' => '1.2.3.4']);
            $listener->onKernelControllerAttribute($this->makeEvent(new RateLimit('api', exposeHeaders: true), $request));

            $response = new Response();
            $listener->onKernelResponse($this->makeResponseEvent($request, $response));

            // the reset is the window's end, then stays constant for every call within the window
            $resetAt ??= (int) $response->headers->get('X-RateLimit-Reset');
            $this->assertEqualsWithDelta(time() + 60, $resetAt, 1);

            $this->assertSame('5', $response->headers->get('X-RateLimit-Limit'));
            $this->assertSame((string) $remaining, $response->headers->get('X-RateLimit-Remaining'));
            $this->assertSame($resetAt, (int) $response->headers->get('X-RateLimit-Reset'));
        }

        $request = Request::create('/', server: ['REMOTE_ADDR' => '1.2.3.4']);
        try {
            $listener->onKernelControllerAttribute($this->makeEvent(new RateLimit('api', exposeHeaders: true), $request));
            $this->fail('Expected TooManyRequestsHttpException');
        } catch (TooManyRequestsHttpException) {
        }

        $response = new Response('', 429);
        $listener->onKernelResponse($this->makeResponseEvent($request, $response));

        $this->assertSame('0', $response->headers->get('X-RateLimit-Remaining'));
        $this->assertSame($resetAt, (int) $response->headers->get('X-RateLimit-Reset'));
    }

    public function testNoLimitStackedWithBoundedLimiterOnlyBoundedOneIsReported()
    {
        $request = Request::create('/');

        $bounded = $this->makeListenerWithResult('bounded', new RateLimitResult(2, new \DateTimeImmutable('now'), true, 5, new \DateTimeImmutable('+1 minute')));
        $bounded->onKernelControllerAttribute($this->makeEvent(new RateLimit('bounded', exposeHeaders: true), $request));

        $unboundedFactory = $this->createStub(RateLimiterFactoryInterface::class);
        $unboundedFactory->method('create')->willReturn(new NoLimiter());
        $unboundedLocator = $this->createStub(ServiceProviderInterface::class);
        $unboundedLocator->method('has')->willReturn(true);
        $unboundedLocator->method('get')->willReturn($unboundedFactory);
        $unboundedLocator->method('getProvidedServices')->willReturn(['unbounded' => RateLimiterFactoryInterface::class]);
        (new RateLimitAttributeListener($unboundedLocator))->onKernelControllerAttribute($this->makeEvent(new RateLimit('unbounded', exposeHeaders: true), $request));

        $response = new Response();
        $bounded->onKernelResponse($this->makeResponseEvent($request, $response));

        $this->assertSame('5', $response->headers->get('X-RateLimit-Limit'));
        $this->assertSame('2', $response->headers->get('X-RateLimit-Remaining'));
    }

    private function makeListenerWithResult(string $limiterName, RateLimitResult $result): RateLimitAttributeListener
    {
        $limiter = $this->createStub(LimiterInterface::class);
        $limiter->method('consume')->willReturn($result);

        $factory = $this->createStub(RateLimiterFactoryInterface::class);
        $factory->method('create')->willReturn($limiter);

        $locator = $this->createStub(ServiceProviderInterface::class);
        $locator->method('has')->willReturn(true);
        $locator->method('get')->willReturn($factory);
        $locator->method('getProvidedServices')->willReturn([$limiterName => RateLimiterFactoryInterface::class]);

        return new RateLimitAttributeListener($locator);
    }

    public function testRejectedLimiterOverridesAcceptedEvenWithHigherRemainingCalls()
    {
        $request = Request::create('/');

        $accepted = $this->makeListenerWithResult('api', new RateLimitResult(1, new \DateTimeImmutable('now'), true, 5, new \DateTimeImmutable('+1 minute')));
        $accepted->onKernelControllerAttribute($this->makeEvent(new RateLimit('api', exposeHeaders: true), $request));

        // rejected despite a higher remaining count: it didn't have enough tokens for this call
        $rejected = $this->makeListenerWithResult('other', new RateLimitResult(3, new \DateTimeImmutable('+2 minutes'), false, 5, new \DateTimeImmutable('+2 minutes')));

        try {
            $rejected->onKernelControllerAttribute($this->makeEvent(new RateLimit('other', exposeHeaders: true), $request));
            $this->fail('Expected TooManyRequestsHttpException');
        } catch (TooManyRequestsHttpException) {
        }

        $response = new Response('', 429);
        $accepted->onKernelResponse($this->makeResponseEvent($request, $response));

        $this->assertSame('5', $response->headers->get('X-RateLimit-Limit'));
        $this->assertSame('3', $response->headers->get('X-RateLimit-Remaining'));
    }

    public function testHeadersFalseSuppressesHeaders()
    {
        $listener = $this->makeListener();
        $request = Request::create('/');

        $listener->onKernelControllerAttribute($this->makeEvent(new RateLimit('api', exposeHeaders: false), $request));

        $response = new Response();
        $listener->onKernelResponse($this->makeResponseEvent($request, $response));

        $this->assertFalse($response->headers->has('X-RateLimit-Limit'));
    }

    public function testHeadersFalseExcludesLimiterEvenWhenMoreRestrictive()
    {
        $request = Request::create('/');

        // tighter, but opted out of exposing its state
        $sensitive = $this->makeListenerWithResult('login', new RateLimitResult(1, new \DateTimeImmutable('now'), true, 5, new \DateTimeImmutable('+1 minute')));
        $sensitive->onKernelControllerAttribute($this->makeEvent(new RateLimit('login', exposeHeaders: false), $request));

        $generic = $this->makeListenerWithResult('api', new RateLimitResult(90, new \DateTimeImmutable('now'), true, 100, new \DateTimeImmutable('+1 minute')));
        $generic->onKernelControllerAttribute($this->makeEvent(new RateLimit('api', exposeHeaders: true), $request));

        $response = new Response();
        $generic->onKernelResponse($this->makeResponseEvent($request, $response));

        $this->assertSame('100', $response->headers->get('X-RateLimit-Limit'));
        $this->assertSame('90', $response->headers->get('X-RateLimit-Remaining'));
    }

    public function testHeadersFalseOnRejectingLimiterStillThrowsWithRetryAfterButNoRateLimitHeaders()
    {
        $request = Request::create('/');

        $sensitive = $this->makeListenerWithResult('login', new RateLimitResult(0, new \DateTimeImmutable('+1 minute'), false, 5));

        try {
            $sensitive->onKernelControllerAttribute($this->makeEvent(new RateLimit('login', exposeHeaders: false), $request));
            $this->fail('Expected TooManyRequestsHttpException');
        } catch (TooManyRequestsHttpException $e) {
            $this->assertArrayHasKey('Retry-After', $e->getHeaders());
        }

        $response = new Response('', 429);
        $sensitive->onKernelResponse($this->makeResponseEvent($request, $response));

        $this->assertFalse($response->headers->has('X-RateLimit-Limit'));
    }

    public function testAppPresetHeaderKeepsTheWholeTripletUntouched()
    {
        $listener = $this->makeListener();
        $request = Request::create('/');

        $listener->onKernelControllerAttribute($this->makeEvent(new RateLimit('api', exposeHeaders: true), $request));

        $response = new Response();
        $response->setPublic();
        $response->headers->set('X-RateLimit-Limit', 'custom');
        $listener->onKernelResponse($this->makeResponseEvent($request, $response));

        $this->assertSame('custom', $response->headers->get('X-RateLimit-Limit'));
        $this->assertFalse($response->headers->has('X-RateLimit-Remaining'));
        $this->assertFalse($response->headers->has('X-RateLimit-Reset'));
        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
    }

    public function testUnrelatedRequestAttributeIsIgnored()
    {
        $listener = $this->makeListener();
        $request = Request::create('/');
        $request->attributes->set('_rate_limit', 'a route default');

        $listener->onKernelControllerAttribute($this->makeEvent(new RateLimit('api', exposeHeaders: true), $request));

        $response = new Response();
        $listener->onKernelResponse($this->makeResponseEvent($request, $response));

        // the route default is replaced, not dereferenced
        $this->assertSame('5', $response->headers->get('X-RateLimit-Limit'));
        $this->assertSame('4', $response->headers->get('X-RateLimit-Remaining'));
    }

    public function testRejectingLimiterWithoutResetTimeSuppressesAnotherLimitersHeaders()
    {
        $request = Request::create('/');

        $accepted = $this->makeListenerWithResult('api', new RateLimitResult(90, new \DateTimeImmutable('now'), true, 100, new \DateTimeImmutable('+1 minute')));
        $accepted->onKernelControllerAttribute($this->makeEvent(new RateLimit('api', exposeHeaders: true), $request));

        // a limiter with no reset time never becomes the candidate, so it must clear the accepted one
        $rejected = $this->makeListenerWithResult('other', new RateLimitResult(0, new \DateTimeImmutable('+1 minute'), false, 5));

        try {
            $rejected->onKernelControllerAttribute($this->makeEvent(new RateLimit('other', exposeHeaders: true), $request));
            $this->fail('Expected TooManyRequestsHttpException');
        } catch (TooManyRequestsHttpException) {
        }

        $response = new Response('', 429);
        $accepted->onKernelResponse($this->makeResponseEvent($request, $response));

        $this->assertFalse($response->headers->has('X-RateLimit-Limit'));
    }

    private function makeRealKernel(array $config, bool $withErrorHandling = false): HttpKernel
    {
        return $this->makeRealKernelWithLimiters(['api' => $config], $withErrorHandling);
    }

    /**
     * @param array<string, array<string, mixed>> $configByName
     */
    private function makeRealKernelWithLimiters(array $configByName, bool $withErrorHandling = false): HttpKernel
    {
        $factories = [];
        foreach ($configByName as $name => $config) {
            $factories[$name] = new RateLimiterFactory(array_replace($config, ['id' => $name]), new InMemoryStorage());
        }

        $locator = $this->createStub(ServiceProviderInterface::class);
        $locator->method('has')->willReturnCallback(static fn (string $id): bool => isset($factories[$id]));
        $locator->method('get')->willReturnCallback(static fn (string $id): RateLimiterFactory => $factories[$id]);
        $locator->method('getProvidedServices')->willReturn(array_map(static fn (): string => RateLimiterFactoryInterface::class, $factories));

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new ControllerAttributesListener([
            KernelEvents::CONTROLLER_ARGUMENTS => [RateLimit::class => true],
        ]));
        $dispatcher->addSubscriber(new RateLimitAttributeListener($locator));

        if ($withErrorHandling) {
            // a non-error response makes handleThrowable() stamp the 429 and Retry-After itself
            $dispatcher->addSubscriber(new ErrorListener(static fn () => new Response('error')));
        }

        return new HttpKernel($dispatcher, new ControllerResolver(), null, new ArgumentResolver());
    }

    private function handleThrough(HttpKernel $kernel, object $controller): Response
    {
        $request = Request::create('/', server: ['REMOTE_ADDR' => '1.2.3.4']);
        $request->attributes->set('_controller', $controller);

        return $kernel->handle($request);
    }

    public static function policyProvider(): iterable
    {
        yield 'fixed_window' => [['policy' => 'fixed_window', 'limit' => 5, 'interval' => '1 minute']];
        yield 'sliding_window' => [['policy' => 'sliding_window', 'limit' => 5, 'interval' => '1 minute']];
        yield 'token_bucket' => [['policy' => 'token_bucket', 'limit' => 5, 'rate' => ['interval' => '1 minute', 'amount' => 1]]];
    }

    #[DataProvider('policyProvider')]
    public function testHeadersReachTheResponseThroughARealKernelDispatch(array $config)
    {
        $kernel = $this->makeRealKernel($config);

        foreach ([4, 3] as $remaining) {
            $response = $this->handleThrough($kernel, new RateLimitedController());

            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame('5', $response->headers->get('X-RateLimit-Limit'));
            $this->assertSame((string) $remaining, $response->headers->get('X-RateLimit-Remaining'));
            $reset = (int) $response->headers->get('X-RateLimit-Reset') - time();
            $this->assertGreaterThan(0, $reset);
            $this->assertLessThanOrEqual(120, $reset);
        }
    }

    #[DataProvider('policyProvider')]
    public function testSharedCacheDoesNotServeOneClientsQuotaToAnother(array $config)
    {
        $kernel = $this->makeRealKernel(array_replace($config, ['limit' => 100]));
        $storeDir = sys_get_temp_dir().'/sf_ratelimit_cache_'.bin2hex(random_bytes(6));
        $cache = new HttpCache($kernel, new Store($storeDir));

        $makeRequest = static function (string $ip) {
            $r = Request::create('/', server: ['REMOTE_ADDR' => $ip]);
            $r->attributes->set('_controller', new PubliclyCacheableRateLimitedController());

            return $r;
        };

        try {
            // a literal key puts both clients on one counter, so a leak shows as a stale number
            $a = $cache->handle($makeRequest('1.1.1.1'));
            $b = $cache->handle($makeRequest('2.2.2.2'));
        } finally {
            HttpCacheTestCase::clearDirectory($storeDir);
            @rmdir($storeDir);
        }

        $this->assertSame('99', $a->headers->get('X-RateLimit-Remaining'));
        $this->assertSame('98', $b->headers->get('X-RateLimit-Remaining'), 'client B must consume its own call, not be served A\'s cached counters');
        $this->assertTrue($a->headers->hasCacheControlDirective('private'));
        $this->assertFalse($a->headers->hasCacheControlDirective('public'));
    }

    public function testCacheAttributeCannotReShareThePerClientHeaders()
    {
        $factory = new RateLimiterFactory(['id' => 'api', 'policy' => 'fixed_window', 'limit' => 5, 'interval' => '1 minute'], new InMemoryStorage());

        $locator = $this->createStub(ServiceProviderInterface::class);
        $locator->method('has')->willReturn(true);
        $locator->method('get')->willReturn($factory);
        $locator->method('getProvidedServices')->willReturn(['api' => RateLimiterFactoryInterface::class]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new ControllerAttributesListener([
            KernelEvents::CONTROLLER_ARGUMENTS => [RateLimit::class => true, Cache::class => true],
            KernelEvents::RESPONSE => [Cache::class => true],
        ]));
        $dispatcher->addSubscriber(new CacheAttributeListener());
        $dispatcher->addSubscriber(new RateLimitAttributeListener($locator));

        $kernel = new HttpKernel($dispatcher, new ControllerResolver(), null, new ArgumentResolver());
        $response = $this->handleThrough($kernel, new CachedRateLimitedController());

        // #[Cache] processes the response at priority 10000, so the listener runs after it and privacy wins
        $this->assertSame('4', $response->headers->get('X-RateLimit-Remaining'));
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertFalse($response->headers->hasCacheControlDirective('public'));
    }

    #[DataProvider('policyProvider')]
    public function testRejectionThroughARealKernelKeepsTheHeadersOnThe429(array $config)
    {
        // limit 1 so the second call is always the rejection, whatever the policy
        $kernel = $this->makeRealKernel(array_replace($config, ['limit' => 1]), true);

        $accepted = $this->handleThrough($kernel, new RateLimitedController());
        $rejected = $this->handleThrough($kernel, new RateLimitedController());

        $this->assertSame(200, $accepted->getStatusCode());
        $this->assertSame(429, $rejected->getStatusCode());

        // Retry-After is a plain delta in seconds, never an epoch
        $this->assertLessThanOrEqual(3600, (int) $rejected->headers->get('Retry-After'));
        $this->assertGreaterThan(0, (int) $rejected->headers->get('Retry-After'));

        $this->assertSame('1', $rejected->headers->get('X-RateLimit-Limit'));
        $this->assertSame('0', $rejected->headers->get('X-RateLimit-Remaining'));
        $this->assertGreaterThan(time(), (int) $rejected->headers->get('X-RateLimit-Reset'));
        $this->assertTrue($rejected->headers->hasCacheControlDirective('private'));
    }

    public function testStackedLimitersThroughARealKernelReportTheTightestOne()
    {
        $kernel = $this->makeRealKernelWithLimiters([
            'generous' => ['policy' => 'fixed_window', 'limit' => 100, 'interval' => '1 minute'],
            'tight' => ['policy' => 'fixed_window', 'limit' => 5, 'interval' => '1 minute'],
        ]);

        $first = $this->handleThrough($kernel, new StackedRateLimitController());
        $this->assertSame('5', $first->headers->get('X-RateLimit-Limit'), 'the tight limiter is reported, not the generous one');
        $this->assertSame('4', $first->headers->get('X-RateLimit-Remaining'));

        $second = $this->handleThrough($kernel, new StackedRateLimitController());
        $this->assertSame('3', $second->headers->get('X-RateLimit-Remaining'));
    }

    public function testStackedLimitersThroughARealKernelNormalizeByTokenCost()
    {
        $kernel = $this->makeRealKernelWithLimiters([
            'cheap' => ['policy' => 'fixed_window', 'limit' => 1000, 'interval' => '1 minute'],
            // 10 calls left at 10 tokens each, so this is the tighter one despite the bigger limit
            'pricey' => ['policy' => 'fixed_window', 'limit' => 100, 'interval' => '1 minute'],
        ]);

        $response = $this->handleThrough($kernel, new StackedTokenCostController());

        $this->assertSame('10', $response->headers->get('X-RateLimit-Limit'));
        $this->assertSame('9', $response->headers->get('X-RateLimit-Remaining'));
    }

    public function testStackedRejectingLimiterSpeaksForThe429ThroughARealKernel()
    {
        $kernel = $this->makeRealKernelWithLimiters([
            'generous' => ['policy' => 'fixed_window', 'limit' => 100, 'interval' => '1 minute'],
            'tight' => ['policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 minute'],
        ], true);

        $this->handleThrough($kernel, new StackedRateLimitController());
        $rejected = $this->handleThrough($kernel, new StackedRateLimitController());

        $this->assertSame(429, $rejected->getStatusCode());
        $this->assertSame('1', $rejected->headers->get('X-RateLimit-Limit'), 'the rejecting limiter, not the generous one');
        $this->assertSame('0', $rejected->headers->get('X-RateLimit-Remaining'));

        // the reset and Retry-After describe the same limiter, so they must not contradict
        $resetIn = (int) $rejected->headers->get('X-RateLimit-Reset') - time();
        $this->assertGreaterThanOrEqual((int) $rejected->headers->get('Retry-After') - 1, $resetIn);
    }

    public function testStackedRejectingOptedOutLimiterLeaksNothingThroughARealKernel()
    {
        $kernel = $this->makeRealKernelWithLimiters([
            'generous' => ['policy' => 'fixed_window', 'limit' => 100, 'interval' => '1 minute'],
            'tight' => ['policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 minute'],
        ], true);

        $this->handleThrough($kernel, new StackedRejectingOptedOutController());
        $rejected = $this->handleThrough($kernel, new StackedRejectingOptedOutController());

        $this->assertSame(429, $rejected->getStatusCode());
        $this->assertNotNull($rejected->headers->get('Retry-After'));
        $this->assertFalse($rejected->headers->has('X-RateLimit-Limit'), 'an opted-out limiter must not leak through its sibling');
        $this->assertFalse($rejected->headers->has('X-RateLimit-Remaining'));
        $this->assertFalse($rejected->headers->has('X-RateLimit-Reset'));
    }

    public function testOptInDefaultHoldsThroughARealKernel()
    {
        $kernel = $this->makeRealKernel(['policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 minute'], true);

        $accepted = $this->handleThrough($kernel, new BareRateLimitController());

        $this->assertSame(200, $accepted->getStatusCode());
        $this->assertFalse($accepted->headers->has('X-RateLimit-Limit'));
        $this->assertFalse($accepted->headers->has('X-RateLimit-Remaining'));
        $this->assertFalse($accepted->headers->has('X-RateLimit-Reset'));
        $this->assertTrue($accepted->headers->hasCacheControlDirective('public'), 'an opted-out limiter must not force the response private');

        $rejected = $this->handleThrough($kernel, new BareRateLimitController());
        $this->assertSame(429, $rejected->getStatusCode());
        $this->assertNotNull($rejected->headers->get('Retry-After'));
    }

    public function testNoLimitPolicyThroughARealKernelEmitsNothingAndNoWarning()
    {
        $failOnWarning = static function (int $severity, string $message): bool {
            throw new \RuntimeException(\sprintf('Unexpected PHP warning: "%s".', $message));
        };

        foreach ([new RateLimitedController(), new TokenCostRateLimitController()] as $controller) {
            $kernel = $this->makeRealKernel(['policy' => 'no_limit']);

            set_error_handler($failOnWarning, \E_WARNING | \E_USER_WARNING);
            try {
                $response = $this->handleThrough($kernel, $controller);
            } finally {
                restore_error_handler();
            }

            $this->assertSame(200, $response->getStatusCode());
            $this->assertFalse($response->headers->has('X-RateLimit-Limit'), $controller::class);
            $this->assertFalse($response->headers->has('X-RateLimit-Remaining'));
            $this->assertFalse($response->headers->has('X-RateLimit-Reset'));
        }
    }

    public function testSubRequestResponseIsLeftAloneThroughARealKernel()
    {
        $kernel = $this->makeRealKernel(['policy' => 'fixed_window', 'limit' => 5, 'interval' => '1 minute']);

        // same client as handleThrough(), so both hit the same limiter key
        $request = Request::create('/', server: ['REMOTE_ADDR' => '1.2.3.4']);
        $request->attributes->set('_controller', new RateLimitedController());
        $subResponse = $kernel->handle($request, HttpKernelInterface::SUB_REQUEST);

        $this->assertFalse($subResponse->headers->has('X-RateLimit-Limit'));
        $this->assertFalse($subResponse->headers->has('X-RateLimit-Remaining'));
        $this->assertFalse($subResponse->headers->has('X-RateLimit-Reset'));

        // the limiter still ran for the fragment, only the header emission was skipped
        $mainResponse = $this->handleThrough($kernel, new RateLimitedController());
        $this->assertSame('3', $mainResponse->headers->get('X-RateLimit-Remaining'), 'the sub-request consumed one, the main request the next');
    }

    public function testRejectedThrowsWith429AndRetryAfter()
    {
        try {
            $this->makeListener(false)->onKernelControllerAttribute($this->makeEvent(new RateLimit('api'), Request::create('/')));
            $this->fail('Expected TooManyRequestsHttpException');
        } catch (TooManyRequestsHttpException $e) {
            $this->assertArrayHasKey('Retry-After', $e->getHeaders());
        }
    }

    public function testExpressionKey()
    {
        $listener = $this->makeListenerCapturingKey($usedKey);
        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '1.2.3.4');

        $listener->onKernelControllerAttribute($this->makeEvent(
            new RateLimit('api', key: new Expression('request.getClientIp()')),
            $request,
            new ExpressionLanguage(),
        ));

        $this->assertSame('1.2.3.4', $usedKey);
    }

    public function testClosureKey()
    {
        $listener = $this->makeListenerCapturingKey($usedKey);
        $listener->onKernelControllerAttribute($this->makeEvent(
            new RateLimit('api', key: static fn ($args, Request $request) => $request->getClientIp()),
            Request::create('/', server: ['REMOTE_ADDR' => '5.6.7.8']),
        ));

        $this->assertSame('5.6.7.8', $usedKey);
    }

    public function testNonStringKeyThrows()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('must evaluate to a string, "int" given');

        $this->makeListener()->onKernelControllerAttribute($this->makeEvent(
            new RateLimit('api', key: static fn () => 42),
            Request::create('/'),
        ));
    }

    public function testRejectedDispatchesRateLimitExceededEvent()
    {
        if (!class_exists(RateLimitExceededEvent::class)) {
            $this->markTestSkipped('The installed "symfony/rate-limiter" does not provide RateLimitExceededEvent.');
        }

        $result = new RateLimitResult(0, new \DateTimeImmutable('+1 minute'), false, 5);

        $limiter = $this->createStub(LimiterInterface::class);
        $limiter->method('consume')->willReturn($result);

        $factory = $this->createStub(RateLimiterFactoryInterface::class);
        $factory->method('create')->willReturn($limiter);

        $locator = $this->createStub(ServiceProviderInterface::class);
        $locator->method('has')->willReturn(true);
        $locator->method('get')->willReturn($factory);
        $locator->method('getProvidedServices')->willReturn(['api' => RateLimiterFactoryInterface::class]);

        $dispatcher = new EventDispatcher();
        $dispatchedEvent = null;
        $dispatcher->addListener(RateLimitExceededEvent::class, static function (RateLimitExceededEvent $event) use (&$dispatchedEvent) {
            $dispatchedEvent = $event;
        });

        $listener = new RateLimitAttributeListener($locator);
        $request = Request::create('/', server: ['REMOTE_ADDR' => '9.9.9.9']);

        try {
            $listener->onKernelControllerAttribute($this->makeEvent(new RateLimit('api'), $request), null, $dispatcher);
            $this->fail('Expected TooManyRequestsHttpException');
        } catch (TooManyRequestsHttpException) {
        }

        $this->assertInstanceOf(RateLimitExceededEvent::class, $dispatchedEvent);
        $this->assertSame($result, $dispatchedEvent->getRateLimit());
        $this->assertSame('api', $dispatchedEvent->getLimiterName());
        $this->assertSame('9.9.9.9~GET~/', $dispatchedEvent->getKey());
    }

    public function testAcceptedDoesNotDispatchEvent()
    {
        $dispatched = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(RateLimitExceededEvent::class, static function ($event) use (&$dispatched) {
            $dispatched[] = $event;
        });

        $result = new RateLimitResult(4, new \DateTimeImmutable('now'), true, 5, new \DateTimeImmutable('+1 minute'));

        $limiter = $this->createStub(LimiterInterface::class);
        $limiter->method('consume')->willReturn($result);

        $factory = $this->createStub(RateLimiterFactoryInterface::class);
        $factory->method('create')->willReturn($limiter);

        $locator = $this->createStub(ServiceProviderInterface::class);
        $locator->method('has')->willReturn(true);
        $locator->method('get')->willReturn($factory);
        $locator->method('getProvidedServices')->willReturn(['api' => RateLimiterFactoryInterface::class]);

        $listener = new RateLimitAttributeListener($locator);
        $listener->onKernelControllerAttribute($this->makeEvent(new RateLimit('api'), Request::create('/')), null, $dispatcher);

        $this->assertSame([], $dispatched);
    }

    public function testMethodFilterSkipsNonMatchingMethod()
    {
        $factory = $this->createMock(RateLimiterFactoryInterface::class);
        $factory->expects($this->never())->method('create');

        $locator = $this->createStub(ServiceProviderInterface::class);
        $locator->method('has')->willReturn(true);
        $locator->method('get')->willReturn($factory);
        $locator->method('getProvidedServices')->willReturn(['api' => RateLimiterFactoryInterface::class]);

        $listener = new RateLimitAttributeListener($locator);
        $listener->onKernelControllerAttribute($this->makeEvent(new RateLimit('api', methods: ['POST']), Request::create('/', 'GET')));
    }
}

class RateLimitedController
{
    #[RateLimit('api', exposeHeaders: true)]
    public function __invoke(): Response
    {
        return new Response('ok');
    }
}

class CachedRateLimitedController
{
    #[Cache(smaxage: 3600)]
    #[RateLimit('api', exposeHeaders: true)]
    public function __invoke(): Response
    {
        return new Response('ok');
    }
}

class PubliclyCacheableRateLimitedController
{
    #[RateLimit('api', key: 'shared', exposeHeaders: true)]
    public function __invoke(): Response
    {
        $response = new Response('ok');
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }
}

class TokenCostRateLimitController
{
    #[RateLimit('api', tokens: 3, exposeHeaders: true)]
    public function __invoke(): Response
    {
        return new Response('ok');
    }
}

class BareRateLimitController
{
    #[RateLimit('api')]
    public function __invoke(): Response
    {
        $response = new Response('ok');
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }
}

class StackedRateLimitController
{
    #[RateLimit('generous', key: 'k', exposeHeaders: true)]
    #[RateLimit('tight', key: 'k', exposeHeaders: true)]
    public function __invoke(): Response
    {
        return new Response('ok');
    }
}

class StackedRejectingOptedOutController
{
    #[RateLimit('generous', key: 'k', exposeHeaders: true)]
    #[RateLimit('tight', key: 'k', exposeHeaders: false)]
    public function __invoke(): Response
    {
        return new Response('ok');
    }
}

class StackedTokenCostController
{
    #[RateLimit('cheap', key: 'k', exposeHeaders: true)]
    #[RateLimit('pricey', key: 'k', tokens: 10, exposeHeaders: true)]
    public function __invoke(): Response
    {
        return new Response('ok');
    }
}
