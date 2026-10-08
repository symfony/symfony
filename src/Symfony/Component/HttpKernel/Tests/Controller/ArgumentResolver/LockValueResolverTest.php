<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Controller\ArgumentResolver;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Lock;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\LockValueResolver;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpKernel\EventListener\ControllerAttributesListener;
use Symfony\Component\HttpKernel\EventListener\LockAttributeListener;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Contracts\Service\ServiceProviderInterface;

class LockValueResolverTest extends TestCase
{
    private InMemoryStore $store;
    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->store = new InMemoryStore();
        $this->requestStack = new RequestStack();
    }

    private function makeKernel(): HttpKernel
    {
        $factories = ['default' => new class($this->store) extends LockFactory {
            public function createLock(string $resource, ?float $ttl = 300.0, bool $autoRelease = true): SharedLockInterface
            {
                return parent::createLock($resource, $ttl, false);
            }
        }];
        $locator = $this->createStub(ServiceProviderInterface::class);
        $locator->method('has')->willReturnCallback(static fn (string $id): bool => isset($factories[$id]));
        $locator->method('get')->willReturnCallback(static fn (string $id): LockFactory => $factories[$id]);

        $resolver = new LockValueResolver($listener = new LockAttributeListener($locator, $this->requestStack));

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new ControllerAttributesListener([
            KernelEvents::CONTROLLER => [Lock::class => true],
            KernelEvents::CONTROLLER_ARGUMENTS => [Lock::class => true],
            KernelEvents::RESPONSE => [Lock::class => true],
            KernelEvents::EXCEPTION => [Lock::class => true],
            KernelEvents::FINISH_REQUEST => [Lock::class => true],
        ], new ExpressionLanguage()));
        $dispatcher->addSubscriber($listener);
        $dispatcher->addSubscriber($resolver);

        return new HttpKernel($dispatcher, new ControllerResolver(), $this->requestStack, new ArgumentResolver(null, [$resolver, ...ArgumentResolver::getDefaultArgumentValueResolvers()]));
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

    public function testKernelResolvesTheLockArgument()
    {
        $kernel = $this->makeKernel();
        $lockedInController = null;
        $request = Request::create('/');
        $request->attributes->set('_controller', #[Lock('foo')] function (LockInterface $lock) use (&$lockedInController) {
            $lockedInController = $lock->isAcquired() && $this->isLocked('foo');

            return new Response();
        });

        $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);

        $this->assertTrue($lockedInController);
        $this->assertFalse($this->isLocked('foo'));
    }

    public function testControllerCanReleaseTheLockArgumentEarly()
    {
        $kernel = $this->makeKernel();
        $lockedAfterRelease = null;
        $request = Request::create('/');
        $request->attributes->set('_controller', #[Lock('foo')] function (SharedLockInterface $lock) use (&$lockedAfterRelease) {
            $lock->release();
            $lockedAfterRelease = $this->isLocked('foo');

            return new Response();
        });

        $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);

        $this->assertFalse($lockedAfterRelease);
        $this->assertFalse($this->isLocked('foo'));
    }

    public function testSubRequestAcquiresAgainTheLockReleasedEarlyByItsMainRequest()
    {
        $kernel = $this->makeKernel();
        $lockedInFragment = $lockedAfterFragment = null;
        $fragment = Request::create('/fragment');
        $fragment->attributes->set('_controller', #[Lock('foo')] function () use (&$lockedInFragment) {
            $lockedInFragment = $this->isLocked('foo');

            return new Response();
        });
        $request = Request::create('/');
        $request->attributes->set('_controller', #[Lock('foo')] function (LockInterface $lock) use ($kernel, $fragment, &$lockedAfterFragment) {
            $lock->release();
            $kernel->handle($fragment, HttpKernelInterface::SUB_REQUEST, false);
            $lockedAfterFragment = $this->isLocked('foo');

            return new Response();
        });

        $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);

        $this->assertTrue($lockedInFragment);
        $this->assertFalse($lockedAfterFragment);
        $this->assertFalse($this->isLocked('foo'));
    }

    public function testKernelResolvesTheLockArgumentOnceTheLockWhoseKeyReadsTheArgumentsIsAcquired()
    {
        $kernel = $this->makeKernel();
        $lockedInController = null;
        $request = Request::create('/');
        $request->attributes->set('id', '42');
        $request->attributes->set('_controller', #[Lock(new Expression('"order-" ~ args["id"]'))] function (string $id, LockInterface $lock) use (&$lockedInController) {
            $lockedInController = $lock->isAcquired() && $this->isLocked('order-42');

            return new Response();
        });

        $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);

        $this->assertTrue($lockedInController);
        $this->assertFalse($this->isLocked('order-42'));
    }

    public function testKernelResolvesTheLockArgumentOnceAllTheLockAttributesAreHandled()
    {
        $lockedInController = null;
        $request = Request::create('/');
        $request->attributes->set('id', 'bar');
        $request->attributes->set('_controller', #[Lock('foo', methods: ['POST']), Lock(new Expression('args["id"]'))] function (string $id, LockInterface $lock) use (&$lockedInController) {
            $lockedInController = $lock->isAcquired() && $this->isLocked('bar');

            return new Response();
        });

        $this->makeKernel()->handle($request, HttpKernelInterface::MAIN_REQUEST, false);

        $this->assertTrue($lockedInController);
        $this->assertFalse($this->isLocked('bar'));
    }

    #[DataProvider('provideControllersWithoutTheirOwnLock')]
    public function testNullableLockArgumentIsNullWhenTheRequestHasNoLockOfItsOwn(\Closure $nullable, \Closure $notNullable)
    {
        $request = Request::create('/');
        $request->attributes->set('id', '42');
        $request->attributes->set('_controller', $nullable);

        $response = $this->makeKernel()->handle($request, HttpKernelInterface::MAIN_REQUEST, false);

        $this->assertSame('null', $response->getContent());
    }

    #[DataProvider('provideControllersWithoutTheirOwnLock')]
    public function testLockArgumentThrowsWhenTheRequestHasNoLockOfItsOwn(\Closure $nullable, \Closure $notNullable)
    {
        $request = Request::create('/');
        $request->attributes->set('id', '42');
        $request->attributes->set('_controller', $notNullable);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageIsOrContains('no lock is acquired by the request');

        $this->makeKernel()->handle($request, HttpKernelInterface::MAIN_REQUEST, false);
    }

    public static function provideControllersWithoutTheirOwnLock(): iterable
    {
        yield 'no lock' => [
            static fn (?LockInterface $lock) => new Response(get_debug_type($lock)),
            static fn (LockInterface $lock) => new Response(),
        ];
        yield 'method not locked' => [
            #[Lock('foo', methods: ['POST'])] static fn (?LockInterface $lock) => new Response(get_debug_type($lock)),
            #[Lock('foo', methods: ['POST'])] static fn (LockInterface $lock) => new Response(),
        ];
        yield 'method not locked, key reading the arguments' => [
            #[Lock(new Expression('args["id"]'), methods: ['POST'])] static fn (string $id, ?LockInterface $lock) => new Response(get_debug_type($lock)),
            #[Lock(new Expression('args["id"]'), methods: ['POST'])] static fn (string $id, LockInterface $lock) => new Response(),
        ];
    }

    #[DataProvider('provideSubRequestControllersReusingTheLockOfTheMainRequest')]
    public function testNullableLockArgumentOfASubRequestIsNullWhenItReusesTheLockOfItsMainRequest(\Closure $nullable, \Closure $notNullable)
    {
        $kernel = $this->makeKernel();
        $fragment = Request::create('/fragment');
        $fragment->attributes->set('id', 'foo');
        $fragment->attributes->set('_controller', $nullable);
        $request = Request::create('/');
        $request->attributes->set('_controller', #[Lock('foo')] static fn () => $kernel->handle($fragment, HttpKernelInterface::SUB_REQUEST, false));

        $response = $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);

        $this->assertSame('null', $response->getContent());
        $this->assertFalse($this->isLocked('foo'));
    }

    #[DataProvider('provideSubRequestControllersReusingTheLockOfTheMainRequest')]
    public function testLockArgumentOfASubRequestThrowsWhenItReusesTheLockOfItsMainRequest(\Closure $nullable, \Closure $notNullable)
    {
        $kernel = $this->makeKernel();
        $fragment = Request::create('/fragment');
        $fragment->attributes->set('id', 'foo');
        $fragment->attributes->set('_controller', $notNullable);
        $request = Request::create('/');
        $request->attributes->set('_controller', #[Lock('foo')] static fn () => $kernel->handle($fragment, HttpKernelInterface::SUB_REQUEST, false));

        try {
            $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);
            $this->fail('A \LogicException should have been thrown.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('the lock is held by a parent request', $e->getMessage());
        }

        $this->assertFalse($this->isLocked('foo'));
    }

    public static function provideSubRequestControllersReusingTheLockOfTheMainRequest(): iterable
    {
        yield 'literal key' => [
            #[Lock('foo')] static fn (?LockInterface $lock) => new Response(get_debug_type($lock)),
            #[Lock('foo')] static fn (LockInterface $lock) => new Response(),
        ];
        yield 'key reading the arguments' => [
            #[Lock(new Expression('args["id"]'))] static fn (string $id, ?LockInterface $lock) => new Response(get_debug_type($lock)),
            #[Lock(new Expression('args["id"]'))] static fn (string $id, LockInterface $lock) => new Response(),
        ];
    }

    public function testLockArgumentThrowsWhenSeveralLocksAreAcquiredByTheRequest()
    {
        $request = Request::create('/');
        $request->attributes->set('_controller', #[Lock('foo'), Lock('bar')] static fn (?LockInterface $lock) => new Response());

        try {
            $this->makeKernel()->handle($request, HttpKernelInterface::MAIN_REQUEST, false);
            $this->fail('A \LogicException should have been thrown.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('several locks are acquired by the request', $e->getMessage());
        }

        $this->assertFalse($this->isLocked('foo'));
        $this->assertFalse($this->isLocked('bar'));
    }
}
