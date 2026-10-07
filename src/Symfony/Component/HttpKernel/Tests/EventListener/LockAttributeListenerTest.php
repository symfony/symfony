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
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\Lock;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerAttributeEvent;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\ControllerAttributesListener;
use Symfony\Component\HttpKernel\EventListener\LockAttributeListener;
use Symfony\Component\HttpKernel\Exception\ConcurrentRequestHttpException;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Contracts\Service\ServiceProviderInterface;

class LockAttributeListenerTest extends TestCase
{
    private InMemoryStore $store;
    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->store = new InMemoryStore();
        $this->requestStack = new RequestStack();
    }

    /**
     * @param array<string, LockFactory> $factories
     */
    private function makeListener(array $factories = []): LockAttributeListener
    {
        // locks are not released on destruction, so that tests fail when the listener does not release them
        $factories ?: $factories = ['default' => new class($this->store) extends LockFactory {
            public function createLock(string $resource, ?float $ttl = 300.0, bool $autoRelease = true): SharedLockInterface
            {
                return parent::createLock($resource, $ttl, false);
            }
        }];

        $locator = $this->createStub(ServiceProviderInterface::class);
        $locator->method('has')->willReturnCallback(static fn (string $id): bool => isset($factories[$id]));
        $locator->method('get')->willReturnCallback(static fn (string $id): LockFactory => $factories[$id]);
        $locator->method('getProvidedServices')->willReturn(array_map(static fn (): string => LockFactory::class, $factories));

        return new LockAttributeListener($locator, $this->requestStack);
    }

    private function makeEvent(Lock $attribute, Request $request, ?ExpressionLanguage $el = null): ControllerAttributeEvent
    {
        return new ControllerAttributeEvent($attribute, new ControllerArgumentsEvent(
            $this->createStub(HttpKernelInterface::class),
            static fn () => null,
            [],
            $request,
            null,
        ), $el);
    }

    private function makeControllerEvent(Lock $attribute, Request $request, ?ExpressionLanguage $el = null): ControllerAttributeEvent
    {
        return new ControllerAttributeEvent($attribute, new ControllerEvent(
            $this->createStub(HttpKernelInterface::class),
            static fn () => null,
            $request,
            null,
        ), $el);
    }

    /**
     * @param 'response'|'exception'|'finish_request' $eventName
     */
    private function makeReleaseEvent(Request $request, string $eventName = 'response', int $requestType = HttpKernelInterface::MAIN_REQUEST, ?Response $response = null): ControllerAttributeEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);

        return new ControllerAttributeEvent(new Lock('foo'), match ($eventName) {
            'response' => new ResponseEvent($kernel, $request, $requestType, $response ?? new Response()),
            'exception' => new ExceptionEvent($kernel, $request, $requestType, new \RuntimeException()),
            'finish_request' => new FinishRequestEvent($kernel, $request, $requestType),
        });
    }

    private function makeKernel(?ValueResolverInterface $valueResolver = null): HttpKernel
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new ControllerAttributesListener([
            KernelEvents::CONTROLLER => [Lock::class => true],
            KernelEvents::CONTROLLER_ARGUMENTS => [Lock::class => true],
            KernelEvents::RESPONSE => [Lock::class => true],
            KernelEvents::EXCEPTION => [Lock::class => true],
            KernelEvents::FINISH_REQUEST => [Lock::class => true],
        ]));
        $dispatcher->addSubscriber($this->makeListener());

        return new HttpKernel($dispatcher, new ControllerResolver(), $this->requestStack, new ArgumentResolver(null, $valueResolver ? [$valueResolver] : []));
    }

    private function isWriteLocked(string $key): bool
    {
        $lock = new LockFactory($this->store)->createLock($key);

        if (!$lock->acquireRead()) {
            return true;
        }

        $lock->release();

        return false;
    }

    private function isLocked(string $key): bool
    {
        $lock = new LockFactory($this->store)->createLock($key);

        if (!$lock->acquire()) {
            return true;
        }

        $lock->release();

        return false;
    }

    public function testAcquiresTheLockAndReleasesItWhenTheRequestIsFinished()
    {
        $listener = $this->makeListener();
        $request = Request::create('/import', 'POST');

        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('import'), $request));
        $this->assertTrue($this->isLocked('import'));

        $listener->releaseLocks($this->makeReleaseEvent($request));
        $this->assertFalse($this->isLocked('import'));
    }

    #[DataProvider('provideReleaseEvents')]
    public function testReleasesTheLockAfterTheController(string $eventName)
    {
        $listener = $this->makeListener();
        $request = Request::create('/');

        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo'), $request));
        $listener->releaseLocks($this->makeReleaseEvent($request, $eventName));

        $this->assertFalse($this->isLocked('foo'));
    }

    public static function provideReleaseEvents(): iterable
    {
        yield 'response' => ['response'];
        yield 'exception' => ['exception'];
        yield 'finish request' => ['finish_request'];
    }

    public function testReleasingSeveralTimesIsANoop()
    {
        $listener = $this->makeListener();
        $first = Request::create('/');
        $second = Request::create('/');

        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo'), $first));
        $listener->releaseLocks($this->makeReleaseEvent($first, 'exception'));
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo'), $second));
        $listener->releaseLocks($this->makeReleaseEvent($first, 'response'));
        $listener->releaseLocks($this->makeReleaseEvent($first, 'finish_request'));

        $this->assertTrue($this->isLocked('foo'));
    }

    public function testStreamedResponseReleasesTheLockOnceItsContentIsSent()
    {
        $listener = $this->makeListener();
        $request = Request::create('/');
        $lockedWhileStreaming = null;
        $response = new StreamedResponse(function () use (&$lockedWhileStreaming) {
            $lockedWhileStreaming = $this->isLocked('foo');
        });

        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo'), $request));
        $listener->releaseLocks($this->makeReleaseEvent($request, 'response', response: $response));
        $listener->releaseLocks($this->makeReleaseEvent($request, 'finish_request'));
        $this->assertTrue($this->isLocked('foo'));

        $response->sendContent();

        $this->assertTrue($lockedWhileStreaming);
        $this->assertFalse($this->isLocked('foo'));
    }

    public function testStreamedResponseReleasesTheLockWhenItsCallbackThrows()
    {
        $listener = $this->makeListener();
        $request = Request::create('/');
        $response = new StreamedResponse(static fn () => throw new \RuntimeException('Streaming failed.'));

        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo'), $request));
        $listener->releaseLocks($this->makeReleaseEvent($request, 'response', response: $response));

        try {
            $response->sendContent();
            $this->fail('A RuntimeException should have been thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Streaming failed.', $e->getMessage());
        }

        $this->assertFalse($this->isLocked('foo'));
    }

    public function testKernelKeepsTheLockWhileStreamingTheResponse()
    {
        $lockedWhileStreaming = null;
        $request = Request::create('/');
        $request->attributes->set('_controller', #[Lock('foo')] function () use (&$lockedWhileStreaming) {
            return new StreamedResponse(function () use (&$lockedWhileStreaming) {
                $lockedWhileStreaming = $this->isLocked('foo');
            });
        });

        $response = $this->makeKernel()->handle($request);
        $this->assertTrue($this->isLocked('foo'));

        ob_start();
        try {
            $response->sendContent();
        } finally {
            ob_end_clean();
        }

        $this->assertTrue($lockedWhileStreaming);
        $this->assertFalse($this->isLocked('foo'));
    }

    public function testKernelFragmentRenderedWhileStreamingReusesTheLock()
    {
        $kernel = $this->makeKernel();
        $fragment = Request::create('/fragment');
        $fragment->attributes->set('_controller', #[Lock('foo')] static fn () => new Response());
        $request = Request::create('/');
        $request->attributes->set('_controller', #[Lock('foo')] static fn () => new StreamedResponse(static fn () => $kernel->handle($fragment, HttpKernelInterface::SUB_REQUEST, false)));

        $kernel->handle($request)->sendContent();

        $this->assertFalse($this->isLocked('foo'));
    }

    public function testKernelReleasesTheLocksAcquiredWhileStreaming()
    {
        $kernel = $this->makeKernel();
        $lockedInFragment = $lockedAfterFragment = null;
        $fragment = Request::create('/fragment');
        $fragment->attributes->set('_controller', #[Lock('bar')] function () use (&$lockedInFragment) {
            $lockedInFragment = $this->isLocked('bar');

            return new Response();
        });
        $request = Request::create('/');
        $request->attributes->set('_controller', #[Lock('foo')] function () use ($kernel, $fragment, &$lockedAfterFragment) {
            return new StreamedResponse(function () use ($kernel, $fragment, &$lockedAfterFragment) {
                $kernel->handle($fragment, HttpKernelInterface::SUB_REQUEST, false);
                $lockedAfterFragment = $this->isLocked('bar');
            });
        });

        $kernel->handle($request)->sendContent();

        $this->assertTrue($lockedInFragment);
        $this->assertFalse($lockedAfterFragment);
        $this->assertFalse($this->isLocked('foo'));
        $this->assertFalse($this->isLocked('bar'));
    }

    public function testResetReleasesTheLocksOfAStreamedResponseThatWasNotSent()
    {
        $listener = $this->makeListener();
        $request = Request::create('/');

        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo'), $request));
        $listener->releaseLocks($this->makeReleaseEvent($request, response: new StreamedResponse(static function () {})));
        $this->assertTrue($this->isLocked('foo'));

        $listener->reset();
        $this->assertFalse($this->isLocked('foo'));

        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo'), $request));
        $listener->releaseLocks($this->makeReleaseEvent($request));
        $this->assertFalse($this->isLocked('foo'));
    }

    public function testStreamedResponseIsLeftUntouchedWhenTheRequestHoldsNoLock()
    {
        $listener = $this->makeListener();
        $request = Request::create('/');
        $response = new StreamedResponse($callback = static function () {});

        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo', methods: ['POST']), $request));
        $listener->releaseLocks($this->makeReleaseEvent($request, response: $response));

        $this->assertSame($callback, $response->getCallback());
    }

    public function testKernelReleasesTheLockWhenTheControllerThrows()
    {
        $lockedInController = null;
        $request = Request::create('/');
        $request->attributes->set('_controller', #[Lock('foo')] function () use (&$lockedInController) {
            $lockedInController = $this->isLocked('foo');

            throw new \RuntimeException('Controller failed.');
        });

        try {
            $this->makeKernel()->handle($request);
            $this->fail('A RuntimeException should have been thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Controller failed.', $e->getMessage());
        }

        $this->assertTrue($lockedInController);
        $this->assertFalse($this->isLocked('foo'));
    }

    public function testRejectsAConcurrentRequestWith409()
    {
        $listener = $this->makeListener();
        $first = Request::create('/');
        $second = Request::create('/');
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo'), $first));

        try {
            $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo'), $second));
            $this->fail('A ConcurrentRequestHttpException should have been thrown.');
        } catch (ConcurrentRequestHttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
            $this->assertSame('foo', $e->key);
            $this->assertSame('default', $e->factory);
            $this->assertNull($e->getPrevious());
        }
    }

    public function testLockConflictedExceptionIsConvertedTo409()
    {
        $conflict = new LockConflictedException();
        $lock = $this->createStub(SharedLockInterface::class);
        $lock->method('acquire')->willThrowException($conflict);

        $factory = $this->createStub(LockFactory::class);
        $factory->method('createLock')->willReturn($lock);

        try {
            $this->makeListener(['other' => $factory])->onKernelControllerAttribute($this->makeEvent(new Lock('foo', factory: 'other', blocking: true), Request::create('/')));
            $this->fail('A ConcurrentRequestHttpException should have been thrown.');
        } catch (ConcurrentRequestHttpException $e) {
            $this->assertSame('foo', $e->key);
            $this->assertSame('other', $e->factory);
            $this->assertSame($conflict, $e->getPrevious());
        }
    }

    public function testAcceptsTheNextRequestOnceTheLockIsReleased()
    {
        $listener = $this->makeListener();
        $first = Request::create('/');
        $second = Request::create('/');

        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo'), $first));
        $listener->releaseLocks($this->makeReleaseEvent($first));
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo'), $second));

        $this->assertTrue($this->isLocked('foo'));
    }

    public function testTheSameLockIsAcquiredOnceForARequest()
    {
        $listener = $this->makeListener();
        $request = Request::create('/');

        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo'), $request));
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo'), $request));
        $this->assertTrue($this->isLocked('foo'));

        $listener->releaseLocks($this->makeReleaseEvent($request));
        $this->assertFalse($this->isLocked('foo'));
    }

    public function testSeveralLocksCanBeHeldByARequest()
    {
        $listener = $this->makeListener();
        $request = Request::create('/');

        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo'), $request));
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('bar'), $request));
        $this->assertTrue($this->isLocked('foo'));
        $this->assertTrue($this->isLocked('bar'));

        $listener->releaseLocks($this->makeReleaseEvent($request));
        $this->assertFalse($this->isLocked('foo'));
        $this->assertFalse($this->isLocked('bar'));
    }

    public function testSubRequestReusesTheLockHeldByItsMainRequest()
    {
        $listener = $this->makeListener();
        $main = Request::create('/');
        $sub = Request::create('/fragment');

        $this->requestStack->push($main);
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo'), $main));
        $this->requestStack->push($sub);
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo'), $sub));
        $listener->releaseLocks($this->makeReleaseEvent($sub, requestType: HttpKernelInterface::SUB_REQUEST));
        $this->requestStack->pop();
        $this->assertTrue($this->isLocked('foo'));

        $listener->releaseLocks($this->makeReleaseEvent($main));
        $this->assertFalse($this->isLocked('foo'));
    }

    public function testBlockingSubRequestDoesNotWaitForTheLockHeldByItsMainRequest()
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(true);

        $factory = $this->createStub(LockFactory::class);
        $factory->method('createLock')->willReturn($lock);

        $listener = $this->makeListener(['default' => $factory]);
        $this->requestStack->push($main = Request::create('/'));
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo', blocking: true), $main));
        $this->requestStack->push($sub = Request::create('/fragment'));
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo', blocking: true), $sub));
    }

    public function testLocksOfASubRequestAreReleasedAfterItsController()
    {
        $listener = $this->makeListener();
        $main = Request::create('/');
        $sub = Request::create('/fragment');

        $this->requestStack->push($main);
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo'), $main));
        $this->requestStack->push($sub);
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('bar'), $sub));
        $listener->releaseLocks($this->makeReleaseEvent($sub, requestType: HttpKernelInterface::SUB_REQUEST));
        $this->requestStack->pop();
        $this->assertFalse($this->isLocked('bar'));
        $this->assertTrue($this->isLocked('foo'));

        $listener->releaseLocks($this->makeReleaseEvent($main));
        $this->assertFalse($this->isLocked('foo'));
    }

    public function testKernelReleasesTheLockOfASubRequestWhenItsMainRequestHasNone()
    {
        $kernel = $this->makeKernel();
        $fragment = Request::create('/fragment');
        $fragment->attributes->set('_controller', #[Lock('foo')] static fn () => new Response());
        $request = Request::create('/');
        $request->attributes->set('_controller', static fn () => $kernel->handle($fragment, HttpKernelInterface::SUB_REQUEST, false));

        $kernel->handle($request);

        $this->assertFalse($this->isLocked('foo'));
    }

    public function testNestedSubRequestReusesTheLockHeldByItsParentSubRequest()
    {
        $listener = $this->makeListener();
        $main = Request::create('/');
        $sub = Request::create('/fragment');
        $nestedSub = Request::create('/nested-fragment');

        $this->requestStack->push($main);
        $this->requestStack->push($sub);
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo'), $sub));
        $this->requestStack->push($nestedSub);
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo'), $nestedSub));
        $listener->releaseLocks($this->makeReleaseEvent($nestedSub, requestType: HttpKernelInterface::SUB_REQUEST));
        $this->requestStack->pop();
        $this->assertTrue($this->isLocked('foo'));

        $listener->releaseLocks($this->makeReleaseEvent($sub, requestType: HttpKernelInterface::SUB_REQUEST));
        $this->assertFalse($this->isLocked('foo'));
    }

    public function testKernelSubRequestReusesTheLockHeldByItsMainRequest()
    {
        $kernel = $this->makeKernel();
        $lockedInFragment = null;
        $fragment = Request::create('/fragment');
        $fragment->attributes->set('_controller', #[Lock('foo')] function () use (&$lockedInFragment) {
            $lockedInFragment = $this->isLocked('foo');

            return new Response();
        });
        $request = Request::create('/');
        $request->attributes->set('_controller', #[Lock('foo')] static fn () => $kernel->handle($fragment, HttpKernelInterface::SUB_REQUEST, false));

        $kernel->handle($request);

        $this->assertTrue($lockedInFragment);
        $this->assertFalse($this->isLocked('foo'));
    }

    public function testReadLocksAreShared()
    {
        $listener = $this->makeListener();
        $first = Request::create('/');
        $second = Request::create('/');

        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo', read: true), $first));
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo', read: true), $second));
        $this->assertFalse($this->isWriteLocked('foo'));
        $this->assertTrue($this->isLocked('foo'));

        $listener->releaseLocks($this->makeReleaseEvent($first));
        $listener->releaseLocks($this->makeReleaseEvent($second));
        $this->assertFalse($this->isLocked('foo'));
    }

    public function testRejectsAWriteLockWhileAReadLockIsHeld()
    {
        $listener = $this->makeListener();
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo', read: true), Request::create('/')));

        $this->expectException(ConcurrentRequestHttpException::class);
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo'), Request::create('/')));
    }

    public function testRejectsAReadLockWhileAWriteLockIsHeld()
    {
        $listener = $this->makeListener();
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo'), Request::create('/')));

        $this->expectException(ConcurrentRequestHttpException::class);
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo', read: true), Request::create('/')));
    }

    public function testBlockingReadLockWaitsForTheLock()
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquireRead')->with(true)->willReturn(true);
        $lock->expects($this->never())->method('acquire');

        $factory = $this->createStub(LockFactory::class);
        $factory->method('createLock')->willReturn($lock);

        $this->makeListener(['default' => $factory])->onKernelControllerAttribute($this->makeEvent(new Lock('foo', blocking: true, read: true), Request::create('/')));
    }

    public function testSubRequestReusesTheWriteLockOfItsMainRequestForReading()
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(true);
        $lock->expects($this->never())->method('acquireRead');

        $factory = $this->createMock(LockFactory::class);
        $factory->expects($this->once())->method('createLock')->willReturn($lock);

        $listener = $this->makeListener(['default' => $factory]);
        $this->requestStack->push($main = Request::create('/'));
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo'), $main));
        $this->requestStack->push($sub = Request::create('/fragment'));
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo', read: true), $sub));
    }

    public function testReadLockIsPromotedWhenAWriteLockIsNeeded()
    {
        $listener = $this->makeListener();
        $main = Request::create('/');
        $sub = Request::create('/fragment');

        $this->requestStack->push($main);
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo', read: true), $main));
        $this->requestStack->push($sub);
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo'), $sub));
        $listener->releaseLocks($this->makeReleaseEvent($sub, requestType: HttpKernelInterface::SUB_REQUEST));
        $this->requestStack->pop();
        $this->assertTrue($this->isWriteLocked('foo'));

        $listener->releaseLocks($this->makeReleaseEvent($main));
        $this->assertFalse($this->isLocked('foo'));
    }

    public function testReadLockIsPromotedOnce()
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquireRead')->willReturn(true);
        $lock->expects($this->once())->method('acquire')->willReturn(true);

        $factory = $this->createStub(LockFactory::class);
        $factory->method('createLock')->willReturn($lock);

        $listener = $this->makeListener(['default' => $factory]);
        $this->requestStack->push($main = Request::create('/'));
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo', read: true), $main));
        $this->requestStack->push($sub = Request::create('/fragment'));
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo'), $sub));
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo'), $sub));
    }

    public function testRejectsThePromotionWhileAnotherRequestHoldsAReadLock()
    {
        // the other request is handled by another process, sharing only the store
        $otherListener = $this->makeListener();
        $otherListener->onKernelControllerAttribute($this->makeEvent(new Lock('foo', read: true), Request::create('/')));

        $listener = $this->makeListener();
        $this->requestStack->push($main = Request::create('/'));
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo', read: true), $main));
        $this->requestStack->push($sub = Request::create('/fragment'));

        $this->expectException(ConcurrentRequestHttpException::class);
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo'), $sub));
    }

    public function testNamedFactory()
    {
        $otherStore = new InMemoryStore();
        $listener = $this->makeListener(['default' => new LockFactory($this->store), 'other' => new LockFactory($otherStore)]);
        $request = Request::create('/');

        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo', factory: 'other'), $request));

        $this->assertFalse($this->isLocked('foo'));
        $this->assertFalse(new LockFactory($otherStore)->createLock('foo')->acquire());
    }

    public function testUnknownFactoryThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Lock factory "missing" does not exist. Did you forget to configure it? Available factories: "default".');

        $this->makeListener()->onKernelControllerAttribute($this->makeEvent(new Lock('foo', factory: 'missing'), Request::create('/')));
    }

    public function testTtlIsPassedToTheFactory()
    {
        $lock = $this->createStub(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(true);

        $factory = $this->createMock(LockFactory::class);
        $factory->expects($this->once())->method('createLock')->with('foo', 10.0)->willReturn($lock);

        $this->makeListener(['default' => $factory])->onKernelControllerAttribute($this->makeEvent(new Lock('foo', ttl: 10.0), Request::create('/')));
    }

    public function testBlockingWaitsForTheLock()
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->with(true)->willReturn(true);

        $factory = $this->createStub(LockFactory::class);
        $factory->method('createLock')->willReturn($lock);

        $this->makeListener(['default' => $factory])->onKernelControllerAttribute($this->makeEvent(new Lock('foo', blocking: true), Request::create('/')));
    }

    public function testExpressionKey()
    {
        $listener = $this->makeListener();
        $request = Request::create('/?id=42');

        $listener->onKernelControllerAttribute($this->makeEvent(
            new Lock(new Expression('"order-"~request.query.get("id")')),
            $request,
            new ExpressionLanguage(),
        ));

        $this->assertTrue($this->isLocked('order-42'));
    }

    public function testClosureKey()
    {
        $listener = $this->makeListener();
        $request = Request::create('/?id=42');

        $listener->onKernelControllerAttribute($this->makeEvent(
            new Lock(static fn ($args, Request $request) => 'order-'.$request->query->get('id')),
            $request,
        ));

        $this->assertTrue($this->isLocked('order-42'));
    }

    public function testIntegerKey()
    {
        $listener = $this->makeListener();
        $request = Request::create('/');

        $listener->onKernelControllerAttribute($this->makeEvent(new Lock(static fn () => 42), $request));

        $this->assertTrue($this->isLocked('42'));
    }

    public function testStringableKey()
    {
        $listener = $this->makeListener();
        $request = Request::create('/');
        $key = new class implements \Stringable {
            public function __toString(): string
            {
                return 'order-42';
            }
        };

        $listener->onKernelControllerAttribute($this->makeEvent(new Lock(static fn () => $key), $request));

        $this->assertTrue($this->isLocked('order-42'));
    }

    public function testInvalidKeyThrows()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessageIs('The value of the "$key" option of the "Symfony\\Component\\HttpKernel\\Attribute\\Lock" attribute must evaluate to a string, an integer or a "Stringable" object, "float" given.');

        $this->makeListener()->onKernelControllerAttribute($this->makeEvent(new Lock(static fn () => 4.2), Request::create('/')));
    }

    #[DataProvider('provideKeysNotReadingArguments')]
    public function testKeyNotReadingArgumentsIsAcquiredBeforeTheArgumentsAreResolved(string|Expression $key, string $expectedKey)
    {
        $listener = $this->makeListener();
        $request = Request::create('/?id=42');
        $attribute = new Lock($key);

        $listener->onKernelControllerAttribute($this->makeControllerEvent($attribute, $request, new ExpressionLanguage()));
        $this->assertTrue($this->isLocked($expectedKey));

        $listener->releaseLocks($this->makeReleaseEvent($request));
        $listener->onKernelControllerAttribute($this->makeEvent($attribute, $request, new ExpressionLanguage()));
        $this->assertFalse($this->isLocked($expectedKey));
    }

    public static function provideKeysNotReadingArguments(): iterable
    {
        yield 'literal key' => ['order', 'order'];
        yield 'expression not reading args' => [new Expression('"order-" ~ request.query.get("id")'), 'order-42'];
    }

    #[DataProvider('provideKeysReadingArguments')]
    public function testKeyReadingArgumentsIsAcquiredOnceTheArgumentsAreResolved(Expression|\Closure $key)
    {
        $listener = $this->makeListener();
        $request = Request::create('/');
        $attribute = new Lock($key);
        $kernel = $this->createStub(HttpKernelInterface::class);
        $controller = static fn ($id) => null;

        $listener->onKernelControllerAttribute(new ControllerAttributeEvent($attribute, new ControllerEvent($kernel, $controller, $request, null), new ExpressionLanguage()));
        $this->assertFalse($this->isLocked('order-42'));

        $listener->onKernelControllerAttribute(new ControllerAttributeEvent($attribute, new ControllerArgumentsEvent($kernel, $controller, ['42'], $request, null), new ExpressionLanguage()));
        $this->assertTrue($this->isLocked('order-42'));
    }

    public static function provideKeysReadingArguments(): iterable
    {
        yield 'expression reading args' => [new Expression('"order-" ~ args["id"]')];
        yield 'closure' => [static fn (array $args) => 'order-'.$args['id']];
    }

    public function testExpressionKeyIsLintedByTheExpressionLanguageThatEvaluatesIt()
    {
        $expressionLanguage = new class extends ExpressionLanguage {
            public array $lintedNames = [];

            public function lint(Expression|string $expression, array $names, int $flags = 0): void
            {
                $this->lintedNames[] = $names;

                parent::lint($expression, $names, $flags);
            }
        };

        $listener = new LockAttributeListener($this->createStub(ServiceProviderInterface::class), $this->requestStack, $expressionLanguage);
        $listener->onKernelControllerAttribute($this->makeControllerEvent(new Lock(new Expression('"order-" ~ args["id"]')), Request::create('/'), $expressionLanguage));

        $this->assertSame([['request', 'this']], $expressionLanguage->lintedNames);
    }

    public function testKernelResolvesTheArgumentsWhileHoldingTheLock()
    {
        $valueResolver = new class(fn () => $this->isLocked('foo')) implements ValueResolverInterface {
            public ?bool $locked = null;

            public function __construct(private \Closure $isLocked)
            {
            }

            public function resolve(Request $request, ArgumentMetadata $argument): iterable
            {
                $this->locked = ($this->isLocked)();

                return ['42'];
            }
        };
        $request = Request::create('/');
        $request->attributes->set('_controller', #[Lock('foo')] static fn (string $id) => new Response($id));

        $this->makeKernel($valueResolver)->handle($request);

        $this->assertTrue($valueResolver->locked);
        $this->assertFalse($this->isLocked('foo'));
    }

    public function testKernelRejectsAConcurrentRequestBeforeResolvingItsArguments()
    {
        new LockFactory($this->store)->createLock('foo', null, false)->acquire();

        $valueResolver = new class implements ValueResolverInterface {
            public function resolve(Request $request, ArgumentMetadata $argument): iterable
            {
                throw new \LogicException('The arguments of a rejected request must not be resolved.');
            }
        };
        $request = Request::create('/');
        $request->attributes->set('_controller', #[Lock('foo')] static fn (string $id) => new Response($id));

        $this->expectException(ConcurrentRequestHttpException::class);

        $this->makeKernel($valueResolver)->handle($request, HttpKernelInterface::MAIN_REQUEST, false);
    }

    public function testPromotionDoesNotWait()
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquireRead')->with(true)->willReturn(true);
        $lock->expects($this->once())->method('acquire')->with(false)->willReturn(false);

        $factory = $this->createStub(LockFactory::class);
        $factory->method('createLock')->willReturn($lock);

        $listener = $this->makeListener(['default' => $factory]);
        $this->requestStack->push($main = Request::create('/'));
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo', blocking: true, read: true), $main));
        $this->requestStack->push($sub = Request::create('/fragment'));

        $this->expectException(ConcurrentRequestHttpException::class);

        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo', blocking: true), $sub));
    }

    public function testMethodFilterSkipsNonMatchingMethod()
    {
        $factory = $this->createMock(LockFactory::class);
        $factory->expects($this->never())->method('createLock');

        $this->makeListener(['default' => $factory])->onKernelControllerAttribute($this->makeEvent(new Lock('foo', methods: ['POST']), Request::create('/', 'GET')));
    }
}
