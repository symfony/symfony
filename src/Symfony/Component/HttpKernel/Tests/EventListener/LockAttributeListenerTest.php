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
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerAttributeEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\ControllerAttributesListener;
use Symfony\Component\HttpKernel\EventListener\LockAttributeListener;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
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

    /**
     * @param 'response'|'exception'|'finish_request' $eventName
     */
    private function makeReleaseEvent(Request $request, string $eventName = 'response', int $requestType = HttpKernelInterface::MAIN_REQUEST, ?Response $response = null): ControllerAttributeEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);

        return new ControllerAttributeEvent(new Lock(), match ($eventName) {
            'response' => new ResponseEvent($kernel, $request, $requestType, $response ?? new Response()),
            'exception' => new ExceptionEvent($kernel, $request, $requestType, new \RuntimeException()),
            'finish_request' => new FinishRequestEvent($kernel, $request, $requestType),
        });
    }

    private function makeKernel(): HttpKernel
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new ControllerAttributesListener([
            KernelEvents::CONTROLLER_ARGUMENTS => [Lock::class => true],
            KernelEvents::RESPONSE => [Lock::class => true],
            KernelEvents::EXCEPTION => [Lock::class => true],
            KernelEvents::FINISH_REQUEST => [Lock::class => true],
        ]));
        $dispatcher->addSubscriber($this->makeListener());

        return new HttpKernel($dispatcher, new ControllerResolver(), new RequestStack(), new ArgumentResolver());
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

        $listener->releaseLocks($this->makeReleaseEvent($request));
        $this->assertFalse($this->isLocked('/import'));
    }

    #[DataProvider('provideReleaseEvents')]
    public function testReleasesTheLockAfterTheController(string $eventName)
    {
        $listener = $this->makeListener();
        $request = Request::create('/');

        $listener->onKernelControllerAttribute($this->makeEvent(new Lock(), $request));
        $listener->releaseLocks($this->makeReleaseEvent($request, $eventName));

        $this->assertFalse($this->isLocked('/'));
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

        // e.g. an exception listener sets a response, so the locks are released on "kernel.exception", "kernel.response" and "kernel.finish_request"
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock(), $first));
        $listener->releaseLocks($this->makeReleaseEvent($first, 'exception'));
        $listener->onKernelControllerAttribute($this->makeEvent(new Lock(), $second));
        $listener->releaseLocks($this->makeReleaseEvent($first, 'response'));
        $listener->releaseLocks($this->makeReleaseEvent($first, 'finish_request'));

        $this->assertTrue($this->isLocked('/'));
    }

    public function testStreamedResponseReleasesTheLockOnceItsContentIsSent()
    {
        $listener = $this->makeListener();
        $request = Request::create('/');
        $lockedWhileStreaming = null;
        $response = new StreamedResponse(function () use (&$lockedWhileStreaming) {
            $lockedWhileStreaming = $this->isLocked('/');
        });

        $listener->onKernelControllerAttribute($this->makeEvent(new Lock(), $request));
        $listener->releaseLocks($this->makeReleaseEvent($request, 'response', response: $response));
        $listener->releaseLocks($this->makeReleaseEvent($request, 'finish_request'));
        $this->assertTrue($this->isLocked('/'));

        $response->sendContent();

        $this->assertTrue($lockedWhileStreaming);
        $this->assertFalse($this->isLocked('/'));
    }

    public function testStreamedResponseReleasesTheLockWhenItsCallbackThrows()
    {
        $listener = $this->makeListener();
        $request = Request::create('/');
        $response = new StreamedResponse(static fn () => throw new \RuntimeException('Streaming failed.'));

        $listener->onKernelControllerAttribute($this->makeEvent(new Lock(), $request));
        $listener->releaseLocks($this->makeReleaseEvent($request, 'response', response: $response));

        try {
            $response->sendContent();
            $this->fail('A RuntimeException should have been thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Streaming failed.', $e->getMessage());
        }

        $this->assertFalse($this->isLocked('/'));
    }

    public function testKernelKeepsTheLockWhileStreamingTheResponse()
    {
        $lockedWhileStreaming = null;
        $request = Request::create('/');
        $request->attributes->set('_controller', #[Lock] function () use (&$lockedWhileStreaming) {
            return new StreamedResponse(function () use (&$lockedWhileStreaming) {
                $lockedWhileStreaming = $this->isLocked('/');
            });
        });

        $response = $this->makeKernel()->handle($request);
        $this->assertTrue($this->isLocked('/'));

        ob_start();
        try {
            $response->sendContent();
        } finally {
            ob_end_clean();
        }

        $this->assertTrue($lockedWhileStreaming);
        $this->assertFalse($this->isLocked('/'));
    }

    public function testKernelReleasesTheLockWhenTheControllerThrows()
    {
        $lockedInController = null;
        $request = Request::create('/');
        $request->attributes->set('_controller', #[Lock] function () use (&$lockedInController) {
            $lockedInController = $this->isLocked('/');

            throw new \RuntimeException('Controller failed.');
        });

        try {
            $this->makeKernel()->handle($request);
            $this->fail('A RuntimeException should have been thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Controller failed.', $e->getMessage());
        }

        $this->assertTrue($lockedInController);
        $this->assertFalse($this->isLocked('/'));
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
        $listener->releaseLocks($this->makeReleaseEvent($first));
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

        $listener->releaseLocks($this->makeReleaseEvent($request));
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

        $listener->releaseLocks($this->makeReleaseEvent($request));
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
