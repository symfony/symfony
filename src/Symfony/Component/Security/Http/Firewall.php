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
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Firewall\AccessListener;

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
        $this->map = $map;
        $this->dispatcher = $dispatcher;
        $this->exceptionListeners = new \SplObjectStorage();
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        // register listeners for this firewall
        $listeners = $this->map->getListeners($event->getRequest());

        $authenticationListeners = $listeners[0];
        $exceptionListener = $listeners[1];
        $logoutListener = isset($listeners[2]) ? $listeners[2] : null;

        if (null !== $exceptionListener) {
            $this->exceptionListeners[$event->getRequest()] = $exceptionListener;
            $exceptionListener->register($this->dispatcher);
        }

        $accessListener = null;

        // initiate the listener chain
        foreach ($authenticationListeners as $listener) {
            if ($listener instanceof AccessListener) {
                $accessListener = $listener;

                continue;
            }

            $listener->handle($event);

            if ($event->hasResponse()) {
                break;
            }
        }

        if (null !== $logoutListener) {
            $logoutListener->handle($event);
        }

        if (!$event->hasResponse() && null !== $accessListener) {
            $accessListener->handle($event);
        }
    }

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
        return array(
            KernelEvents::REQUEST => array('onKernelRequest', 8),
            KernelEvents::FINISH_REQUEST => 'onKernelFinishRequest',
        );
    }
}
