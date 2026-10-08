<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Controller\ArgumentResolver;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\EventListener\ControllerAttributesListener;
use Symfony\Component\HttpKernel\EventListener\LockAttributeListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\SharedLockInterface;

/**
 * Resolves the LockInterface and SharedLockInterface arguments of a controller to the lock acquired by its #[Lock] attribute.
 *
 * The controller can then refresh() or release() it.
 * A nullable argument gets null when the request acquired no lock, e.g. when it reuses the one of its parent request.
 */
final class LockValueResolver implements ValueResolverInterface, EventSubscriberInterface
{
    /** @var \WeakMap<Request, list<ArgumentMetadata>> */
    private \WeakMap $lockArguments;

    public function __construct(
        private readonly LockAttributeListener $lockAttributeListener,
    ) {
        $this->lockArguments = new \WeakMap();
    }

    public function resolve(Request $request, ArgumentMetadata $argument): array
    {
        if (!\in_array($argument->getType(), [LockInterface::class, SharedLockInterface::class], true)) {
            return [];
        }

        $this->lockArguments[$request] = [...$this->lockArguments[$request] ?? [], $argument];

        return [$argument];
    }

    public function onKernelControllerArguments(ControllerArgumentsEvent $event): void
    {
        $request = $event->getRequest();

        if (!$lockArguments = $this->lockArguments[$request] ?? []) {
            return;
        }

        unset($this->lockArguments[$request]);

        $arguments = $event->getArguments();
        foreach ($arguments as $i => $argument) {
            if (\in_array($argument, $lockArguments, true)) {
                $arguments[$i] = $this->getLock($request, $argument);
            }
        }
        $event->setArguments($arguments);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER_ARGUMENTS => ['method' => 'onKernelControllerArguments', 'priority' => -10100, 'after' => ControllerAttributesListener::class],
        ];
    }

    private function getLock(Request $request, ArgumentMetadata $argument): ?SharedLockInterface
    {
        $locks = $this->lockAttributeListener->getAcquiredLocks($request);

        if (1 < \count($locks)) {
            throw new \LogicException(\sprintf('Cannot resolve the "$%s" argument of "%s()": several locks are acquired by the request.', $argument->getName(), $argument->getControllerName()));
        }

        if (!$locks && !$argument->isNullable()) {
            throw new \LogicException(\sprintf('Cannot resolve the "$%s" argument of "%s()": no lock is acquired by the request, either because no "#[Lock]" attribute applies to its method, or because the lock is held by a parent request. Make the argument nullable if this is expected.', $argument->getName(), $argument->getControllerName()));
        }

        return $locks[0] ?? null;
    }
}
