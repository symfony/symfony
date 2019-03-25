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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Firewall\AccessListener;
use Symfony\Component\Security\Http\Firewall\LogoutListener;

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
    private $map;
    private $dispatcher;
    private $exceptionListeners;

    public function __construct(FirewallMapInterface $map, EventDispatcherInterface $dispatcher)
    {
        // the type-hint will be updated to the "EventDispatcherInterface" from symfony/contracts in 5.0

        $this->map = $map;
        $this->dispatcher = $dispatcher;
        $this->exceptionListeners = new \SplObjectStorage();
    }

    /**
     * @internal since Symfony 4.3
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        // register listeners for this firewall
        $listeners = $this->map->getListeners($event->getRequest());

        if (3 !== \count($listeners)) {
            @trigger_error(sprintf('Not returning an array of 3 elements from %s::getListeners() is deprecated since Symfony 4.2, the 3rd element must be an instance of %s or null.', FirewallMapInterface::class, LogoutListener::class), E_USER_DEPRECATED);
            $listeners[2] = null;
        }

        $authenticationListeners = $listeners[0];
        $exceptionListener = $listeners[1];
        $logoutListener = $listeners[2];

        if (null !== $exceptionListener) {
            $this->exceptionListeners[$event->getRequest()] = $exceptionListener;
            $exceptionListener->register($this->dispatcher);
        }

        $authenticationListeners = function () use ($authenticationListeners, $logoutListener) {
            $accessListener = null;

            foreach ($authenticationListeners as $listener) {
                if ($listener instanceof AccessListener) {
                    $accessListener = $listener;

                    continue;
                }

                yield $listener;
            }

            if (null !== $logoutListener) {
                yield $logoutListener;
            }

            if (null !== $accessListener) {
                yield $accessListener;
            }
        };

        if ($event instanceof RequestEvent) {
            $this->callListeners($event, $authenticationListeners());
        } else {
            $this->handleRequest($event, $authenticationListeners());
        }
    }

    /**
     * @internal since Symfony 4.3
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
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 8],
            KernelEvents::FINISH_REQUEST => 'onKernelFinishRequest',
        ];
    }

    protected function callListeners(RequestEvent $event, iterable $listeners)
    {
        $this->handleRequest($event, $listeners);
    }

    /**
     * @deprecated since Symfony 4.3, use callListeners instead
     */
    protected function handleRequest(GetResponseEvent $event, $listeners)
    {
        foreach ($listeners as $listener) {
            if (\is_callable($listener)) {
                $listener($event);
            } else {
                @trigger_error(sprintf('Calling the "%s::handle()" method from the firewall is deprecated since Symfony 4.3, implement "__invoke()" instead.', \get_class($this)), E_USER_DEPRECATED);
                $listener->handle($event);
            }

            if ($event->hasResponse()) {
                break;
            }
        }
    }
}
