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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\Lock;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerAttributeEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\ConcurrentRequestHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\LockReleasingException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Contracts\Service\ResetInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * Handles the Lock attribute on controllers.
 *
 * The locks belong to the main request, including the ones acquired by its sub-requests,
 * and are released right after its controller, whether it returned a response or threw
 * an exception. When the response is streamed, they are released once its content has been sent.
 */
final class LockAttributeListener implements EventSubscriberInterface, ResetInterface
{
    /** @var \WeakMap<Request, array<string, LockInterface>> */
    private \WeakMap $locks;

    /**
     * The main requests whose locks are released once their streamed response has been sent.
     *
     * @var \WeakMap<Request, true>
     */
    private \WeakMap $streamedRequests;

    /**
     * @param ServiceProviderInterface<LockFactory> $lockFactories
     */
    public function __construct(
        private readonly ServiceProviderInterface $lockFactories,
        private readonly RequestStack $requestStack,
    ) {
        $this->locks = new \WeakMap();
        $this->streamedRequests = new \WeakMap();
    }

    /**
     * @param ControllerAttributeEvent<Lock, ControllerArgumentsEvent> $event
     */
    public function onKernelControllerAttribute(ControllerAttributeEvent $event): void
    {
        $request = $event->kernelEvent->getRequest();
        $attribute = $event->attribute;

        if ($attribute->methods && !\in_array($request->getMethod(), $attribute->methods, true)) {
            return;
        }

        if (!$this->lockFactories->has($attribute->factory)) {
            throw new \InvalidArgumentException(\sprintf('Lock factory "%s" does not exist. Did you forget to configure it? Available factories: "%s".', $attribute->factory, implode('", "', array_keys($this->lockFactories->getProvidedServices()))));
        }

        if (null === $attribute->key) {
            $key = $request->getPathInfo();
        } elseif (!\is_string($key = $event->evaluate($attribute->key))) {
            throw new \TypeError(\sprintf('The value of the "$key" option of the "%s" attribute must evaluate to a string, "%s" given.', Lock::class, get_debug_type($key)));
        }

        $mainRequest = $this->requestStack->getMainRequest() ?? $request;
        $locks = $this->locks[$mainRequest] ?? [];

        // the same lock is already held for the main request, e.g. when the attribute is set on both
        // the class and the method, or when a fragment is rendered from a controller locked with the same key
        if (isset($locks[$id = $attribute->factory."\0".$key])) {
            return;
        }

        $lock = $this->lockFactories->get($attribute->factory)->createLock($key, $attribute->ttl);

        try {
            $acquired = $lock->acquire($attribute->blocking);
        } catch (LockConflictedException $e) {
            throw new ConcurrentRequestHttpException($key, $attribute->factory, $e);
        }

        if (!$acquired) {
            throw new ConcurrentRequestHttpException($key, $attribute->factory);
        }

        $locks[$id] = $lock;
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

        // locks handed over to a streamed response are released once it has been sent
        if (!isset($this->locks[$request]) || isset($this->streamedRequests[$request])) {
            return;
        }

        if ($event->kernelEvent instanceof ResponseEvent
            && ($response = $event->kernelEvent->getResponse()) instanceof StreamedResponse
            && $callback = $response->getCallback()
        ) {
            $this->streamedRequests[$request] = true;

            $response->setCallback(function () use ($callback, $request) {
                try {
                    $callback();
                } finally {
                    unset($this->streamedRequests[$request]);
                    $this->releaseRequestLocks($request);
                }
            });

            return;
        }

        $this->releaseRequestLocks($request);
    }

    public function reset(): void
    {
        $locks = [];
        foreach ($this->locks as $requestLocks) {
            array_push($locks, ...array_values($requestLocks));
        }
        $this->locks = new \WeakMap();
        $this->streamedRequests = new \WeakMap();

        self::release($locks);
    }

    private function releaseRequestLocks(Request $mainRequest): void
    {
        $locks = $this->locks[$mainRequest] ?? [];
        unset($this->locks[$mainRequest]);

        self::release($locks);
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

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER_ARGUMENTS.'.'.Lock::class => 'onKernelControllerAttribute',
            KernelEvents::RESPONSE.'.'.Lock::class => 'releaseLocks',
            KernelEvents::EXCEPTION.'.'.Lock::class => 'releaseLocks',
            KernelEvents::FINISH_REQUEST.'.'.Lock::class => 'releaseLocks',
        ];
    }
}
