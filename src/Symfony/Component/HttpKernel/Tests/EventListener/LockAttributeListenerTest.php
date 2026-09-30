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
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\Lock;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerAttributeEvent;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\EventListener\LockAttributeListener;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Contracts\Service\ServiceProviderInterface;

class LockAttributeListenerTest extends TestCase
{
    private InMemoryStore $store;

    protected function setUp(): void
    {
        $this->store = new InMemoryStore();
    }

    /**
     * @param array<string, LockFactory> $factories
     */
    private function makeListener(array $factories = []): LockAttributeListener
    {
        $factories ?: $factories = ['default' => new LockFactory($this->store)];

        $locator = $this->createStub(ServiceProviderInterface::class);
        $locator->method('has')->willReturnCallback(static fn (string $id): bool => isset($factories[$id]));
        $locator->method('get')->willReturnCallback(static fn (string $id): LockFactory => $factories[$id]);
        $locator->method('getProvidedServices')->willReturn(array_map(static fn (): string => LockFactory::class, $factories));

        return new LockAttributeListener($locator);
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

    private function makeFinishRequestEvent(Request $request): FinishRequestEvent
    {
        return new FinishRequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
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

        $listener->onKernelControllerAttribute($this->makeEvent(new Lock(), $request));
        $this->assertTrue($this->isLocked('/import'));

        $listener->onKernelFinishRequest($this->makeFinishRequestEvent($request));
        $this->assertFalse($this->isLocked('/import'));
    }

    public function testRejectsAConcurrentRequestWith409()
    {
        $listener = $this->makeListener();
        $first = Request::create('/');
        $second = Request::create('/');
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock(), $first));

        $this->expectException(ConflictHttpException::class);
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock(), $second));
    }

    public function testAcceptsTheNextRequestOnceTheLockIsReleased()
    {
        $listener = $this->makeListener();
        $first = Request::create('/');
        $second = Request::create('/');

        $listener->onKernelControllerAttribute($this->makeEvent(new Lock(), $first));
        $listener->onKernelFinishRequest($this->makeFinishRequestEvent($first));
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock(), $second));

        $this->assertTrue($this->isLocked('/'));
    }

    public function testTheSameLockIsAcquiredOnceForARequest()
    {
        $listener = $this->makeListener();
        $request = Request::create('/');

        // e.g. the attribute is set on both the controller class and its method
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock(), $request));
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock(), $request));
        $this->assertTrue($this->isLocked('/'));

        $listener->onKernelFinishRequest($this->makeFinishRequestEvent($request));
        $this->assertFalse($this->isLocked('/'));
    }

    public function testSeveralLocksCanBeHeldByARequest()
    {
        $listener = $this->makeListener();
        $request = Request::create('/');

        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('foo'), $request));
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock('bar'), $request));
        $this->assertTrue($this->isLocked('foo'));
        $this->assertTrue($this->isLocked('bar'));

        $listener->onKernelFinishRequest($this->makeFinishRequestEvent($request));
        $this->assertFalse($this->isLocked('foo'));
        $this->assertFalse($this->isLocked('bar'));
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

        $this->makeListener()->onKernelControllerAttribute($this->makeEvent(new Lock(factory: 'missing'), Request::create('/')));
    }

    public function testTtlIsPassedToTheFactory()
    {
        $lock = $this->createStub(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(true);

        $factory = $this->createMock(LockFactory::class);
        $factory->expects($this->once())->method('createLock')->with('/', 10.0)->willReturn($lock);

        $this->makeListener(['default' => $factory])->onKernelControllerAttribute($this->makeEvent(new Lock(ttl: 10.0), Request::create('/')));
    }

    public function testBlockingWaitsForTheLock()
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->with(true)->willReturn(true);

        $factory = $this->createStub(LockFactory::class);
        $factory->method('createLock')->willReturn($lock);

        $this->makeListener(['default' => $factory])->onKernelControllerAttribute($this->makeEvent(new Lock(blocking: true), Request::create('/')));
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

    public function testNonStringKeyThrows()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessageIs('The value of the "$key" option of the "Symfony\\Component\\HttpKernel\\Attribute\\Lock" attribute must evaluate to a string, "int" given.');

        $this->makeListener()->onKernelControllerAttribute($this->makeEvent(new Lock(static fn () => 42), Request::create('/')));
    }

    public function testMethodFilterSkipsNonMatchingMethod()
    {
        $factory = $this->createMock(LockFactory::class);
        $factory->expects($this->never())->method('createLock');

        $this->makeListener(['default' => $factory])->onKernelControllerAttribute($this->makeEvent(new Lock(methods: ['POST']), Request::create('/', 'GET')));
    }
}
