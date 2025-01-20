<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Firewall\ExceptionListener;
use Symfony\Component\Security\Http\Firewall\FirewallListenerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Firewall uses a FirewallMap to register security listeners for the given
 * request.
 *
 * It allows for different security strategies within the same application
 * (a Basic authentication for the /api, and a web based authentication for
 * everything else for instance).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Firewall implements EventSubscriberInterface
{
    /**
     * @var \SplObjectStorage<Request, ExceptionListener>
     */
    private \SplObjectStorage $exceptionListeners;

    public function __construct(
        private FirewallMapInterface $map,
        private EventDispatcherInterface $dispatcher,
    ) {
        $this->exceptionListeners = new \SplObjectStorage();
    }

    /**
     * @return void
     */
    public function onKernelRequest(RequestEvent $event)
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // register listeners for this firewall
        $listeners = $this->map->getListeners($event->getRequest());

        $authenticationListeners = $listeners[0];
        $exceptionListener = $listeners[1];
        $logoutListener = $listeners[2];

        if (null !== $exceptionListener) {
            $this->exceptionListeners[$event->getRequest()] = $exceptionListener;
            $exceptionListener->register($this->dispatcher);
        }

        // Authentication listeners are pre-sorted by SortFirewallListenersPass
        $authenticationListeners = function () use ($authenticationListeners, $logoutListener) {
            if (null !== $logoutListener) {
                $logoutListenerPriority = $this->getListenerPriority($logoutListener);
            }

            foreach ($authenticationListeners as $listener) {
                $listenerPriority = $this->getListenerPriority($listener);

                // Yielding the LogoutListener at the correct position
                if (null !== $logoutListener && $listenerPriority < $logoutListenerPriority) {
                    yield $logoutListener;
                    $logoutListener = null;
                }

                yield $listener;
            }

            // When LogoutListener has the lowest priority of all listeners
            if (null !== $logoutListener) {
                yield $logoutListener;
            }
        };

        $this->callListeners($event, $authenticationListeners());
    }

    /**
     * @return void
     */
    public function onKernelFinishRequest(FinishRequestEvent $event)
    {
        $request = $event->getRequest();

        if (isset($this->exceptionListeners[$request])) {
            $this->exceptionListeners[$request]->unregister($this->dispatcher);
            unset($this->exceptionListeners[$request]);
        }
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 8],
            KernelEvents::FINISH_REQUEST => 'onKernelFinishRequest',
        ];
    }

    /**
     * @return void
     */
    protected function callListeners(RequestEvent $event, iterable $listeners)
    {
        foreach ($listeners as $listener) {
            $listener($event);

            if ($event->hasResponse()) {
                break;
            }
        }
    }

    private function getListenerPriority(object $logoutListener): int
    {
        return $logoutListener instanceof FirewallListenerInterface ? $logoutListener->getPriority() : 0;
    }
}
