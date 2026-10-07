<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\Parser;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\Lock;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerAttributeEvent;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\ConcurrentRequestHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\LockReleasingException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Contracts\Service\ResetInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * Handles the Lock attribute on controllers.
 *
 * A lock is acquired as soon as the controller is known, or once its arguments are resolved when the key reads them.
 * The locks are shared by the main request and its sub-requests.
 * Each one is released by the request that acquired it, right after its controller, whether it returned a response or threw an exception.
 * When the response is streamed, they are released once its content has been sent.
 */
final class LockAttributeListener implements EventSubscriberInterface, ResetInterface
{
    /**
     * The locks of each main request, with the request that acquired them.
     *
     * @var \WeakMap<Request, array<string, array{lock: SharedLockInterface, owner: Request}>>
     */
    private \WeakMap $locks;

    /** @var \WeakMap<SharedLockInterface, true> */
    private \WeakMap $readLocks;

    /**
     * The requests whose locks are released once their streamed response has been sent.
     *
     * @var \WeakMap<Request, true>
     */
    private \WeakMap $streamedRequests;

    /** @var \WeakMap<Request, list<Lock>> */
    private \WeakMap $acquiredBeforeArguments;

    /**
     * @param ServiceProviderInterface<LockFactory> $lockFactories
     * @param ExpressionLanguage|null               $expressionLanguage The one that evaluates the keys, which knows the variables of the expressions it compiled
     */
    public function __construct(
        private readonly ServiceProviderInterface $lockFactories,
        private readonly RequestStack $requestStack,
        private ?ExpressionLanguage $expressionLanguage = null,
    ) {
        $this->locks = new \WeakMap();
        $this->readLocks = new \WeakMap();
        $this->streamedRequests = new \WeakMap();
        $this->acquiredBeforeArguments = new \WeakMap();
    }

    /**
     * @param ControllerAttributeEvent<Lock, ControllerEvent|ControllerArgumentsEvent> $event
     */
    public function onKernelControllerAttribute(ControllerAttributeEvent $event): void
    {
        $request = $event->kernelEvent->getRequest();
        $attribute = $event->attribute;

        if ($event->kernelEvent instanceof ControllerEvent) {
            if ($this->readsArguments($attribute->key)) {
                return;
            }

            $this->acquiredBeforeArguments[$request] = [...$this->acquiredBeforeArguments[$request] ?? [], $attribute];
        } elseif (\in_array($attribute, $this->acquiredBeforeArguments[$request] ?? [], true)) {
            return;
        }

        if ($attribute->methods && !\in_array($request->getMethod(), $attribute->methods, true)) {
            return;
        }

        if (!$this->lockFactories->has($attribute->factory)) {
            throw new \InvalidArgumentException(\sprintf('Lock factory "%s" does not exist. Did you forget to configure it? Available factories: "%s".', $attribute->factory, implode('", "', array_keys($this->lockFactories->getProvidedServices()))));
        }

        if (\is_string($key = $event->evaluate($attribute->key)) || \is_int($key) || $key instanceof \Stringable) {
            $key = (string) $key;
        } else {
            throw new \TypeError(\sprintf('The value of the "$key" option of the "%s" attribute must evaluate to a string, an integer or a "Stringable" object, "%s" given.', Lock::class, get_debug_type($key)));
        }

        $mainRequest = $this->requestStack->getMainRequest() ?? $request;
        $locks = $this->locks[$mainRequest] ?? [];
        $id = $attribute->factory."\0".$key;

        // the same lock is already held for the main request, e.g. when the attribute is set on both the class and the method,
        // or when a fragment is rendered from a controller locked with the same key
        if ($lock = $locks[$id]['lock'] ?? null) {
            // a write lock is needed while only a read lock is held: promote it without waiting,
            // as two requests promoting their read locks would wait for each other
            if (!$attribute->read && isset($this->readLocks[$lock])) {
                $this->acquire($lock, $attribute, $key, false);
                unset($this->readLocks[$lock]);
            }

            return;
        }

        $lock = $this->lockFactories->get($attribute->factory)->createLock($key, $attribute->ttl);
        $this->acquire($lock, $attribute, $key, $attribute->blocking);

        if ($attribute->read) {
            $this->readLocks[$lock] = true;
        }

        $locks[$id] = ['lock' => $lock, 'owner' => $request];
        $this->locks[$mainRequest] = $locks;
    }

    /**
     * Releases the locks acquired for the request.
     *
     * @param ControllerAttributeEvent<Lock, ResponseEvent|ExceptionEvent|FinishRequestEvent> $event
     */
    public function releaseLocks(ControllerAttributeEvent $event): void
    {
        $request = $event->kernelEvent->getRequest();
        $mainRequest = $this->requestStack->getMainRequest() ?? $request;

        // nothing to release for this request, or its locks are handed over to a streamed response that releases them once sent
        if (isset($this->streamedRequests[$request])
            || !array_any($this->locks[$mainRequest] ?? [], static fn (array $entry): bool => $request === $entry['owner'])
        ) {
            return;
        }

        if ($event->kernelEvent instanceof ResponseEvent
            && ($response = $event->kernelEvent->getResponse()) instanceof StreamedResponse
            && $callback = $response->getCallback()
        ) {
            $this->streamedRequests[$request] = true;

            $response->setCallback(function () use ($callback, $mainRequest, $request) {
                try {
                    $callback();
                } finally {
                    unset($this->streamedRequests[$request]);
                    $this->releaseRequestLocks($mainRequest, $request);
                }
            });

            return;
        }

        $this->releaseRequestLocks($mainRequest, $request);
    }

    public function reset(): void
    {
        $locks = [];
        foreach ($this->locks as $requestLocks) {
            array_push($locks, ...array_column($requestLocks, 'lock'));
        }
        $this->locks = new \WeakMap();
        $this->readLocks = new \WeakMap();
        $this->streamedRequests = new \WeakMap();

        self::release($locks);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER.'.'.Lock::class => 'onKernelControllerAttribute',
            KernelEvents::CONTROLLER_ARGUMENTS.'.'.Lock::class => 'onKernelControllerAttribute',
            KernelEvents::RESPONSE.'.'.Lock::class => 'releaseLocks',
            KernelEvents::EXCEPTION.'.'.Lock::class => 'releaseLocks',
            KernelEvents::FINISH_REQUEST.'.'.Lock::class => 'releaseLocks',
        ];
    }

    private function acquire(SharedLockInterface $lock, Lock $attribute, string $key, bool $blocking): void
    {
        try {
            $acquired = $attribute->read ? $lock->acquireRead($blocking) : $lock->acquire($blocking);
        } catch (LockConflictedException $e) {
            throw new ConcurrentRequestHttpException($key, $attribute->factory, $e);
        }

        if (!$acquired) {
            throw new ConcurrentRequestHttpException($key, $attribute->factory);
        }
    }

    /**
     * Releases the locks acquired by the request, keeping the ones acquired by the other requests of the stack.
     */
    private function releaseRequestLocks(Request $mainRequest, Request $request): void
    {
        $locks = $this->locks[$mainRequest] ?? [];
        $released = [];

        foreach ($locks as $id => ['lock' => $lock, 'owner' => $owner]) {
            if ($owner === $request) {
                $released[] = $lock;
                unset($locks[$id]);
            }
        }

        if ($locks) {
            $this->locks[$mainRequest] = $locks;
        } else {
            unset($this->locks[$mainRequest]);
        }

        self::release($released);
    }

    /**
     * @param LockInterface[] $locks
     */
    private static function release(array $locks): void
    {
        foreach ($locks as $lock) {
            try {
                $lock->release();
            } catch (LockReleasingException) {
                // already logged by the lock, and the TTL bounds the damage
            }
        }
    }

    private function readsArguments(string|Expression|\Closure $key): bool
    {
        if (!$key instanceof Expression) {
            return $key instanceof \Closure;
        }

        try {
            ($this->expressionLanguage ??= new ExpressionLanguage())->lint($key, ['request', 'this'], Parser::IGNORE_UNKNOWN_FUNCTIONS);
        } catch (SyntaxError) {
            return true;
        }

        return false;
    }
}
