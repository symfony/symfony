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
use Symfony\Component\HttpKernel\Attribute\Lock;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerAttributeEvent;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
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
 * The locks acquired for a request are released when the request is finished,
 * whether the controller returned a response or threw an exception.
 */
final class LockAttributeListener implements EventSubscriberInterface, ResetInterface
{
    /** @var \WeakMap<Request, array<string, LockInterface>> */
    private \WeakMap $locks;

    /**
     * @param ServiceProviderInterface<LockFactory> $lockFactories
     */
    public function __construct(
        private readonly ServiceProviderInterface $lockFactories,
    ) {
        $this->locks = new \WeakMap();
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

        $locks = $this->locks[$request] ?? [];

        // the same lock is already held by this request, e.g. when the attribute is set on both the class and the method
        if (isset($locks[$id = $attribute->factory."\0".$key])) {
            return;
        }

        $lock = $this->lockFactories->get($attribute->factory)->createLock($key, $attribute->ttl);

        try {
            $acquired = $lock->acquire($attribute->blocking);
        } catch (LockConflictedException $e) {
            throw new ConflictHttpException('A concurrent request is already being processed.', $e);
        }

        if (!$acquired) {
            throw new ConflictHttpException('A concurrent request is already being processed.');
        }

        $locks[$id] = $lock;
        $this->locks[$request] = $locks;
    }

    /**
     * Releases the locks acquired for the request.
     */
    public function onKernelFinishRequest(FinishRequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$locks = $this->locks[$request] ?? []) {
            return;
        }

        unset($this->locks[$request]);
        $this->release($locks);
    }

    public function reset(): void
    {
        $locks = [];
        foreach ($this->locks as $requestLocks) {
            array_push($locks, ...array_values($requestLocks));
        }
        $this->locks = new \WeakMap();

        $this->release($locks);
    }

    /**
     * @param LockInterface[] $locks
     */
    private function release(array $locks): void
    {
        $exception = null;

        foreach ($locks as $lock) {
            try {
                $lock->release();
            } catch (LockReleasingException $e) {
                $exception ??= $e;
            }
        }

        if ($exception) {
            throw $exception;
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER_ARGUMENTS.'.'.Lock::class => 'onKernelControllerAttribute',
            KernelEvents::FINISH_REQUEST => 'onKernelFinishRequest',
        ];
    }
}
