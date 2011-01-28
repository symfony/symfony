<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Firewall uses a FirewallMap to register security listeners for the given
 * request.
 *
 * It allows for different security strategies within the same application
 * (a Basic authentication for the /api, and a web based authentication for
 * everything else for instance).
 *
 * The handle method must be connected to the core.request event.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Firewall
{
    protected $map;
    protected $dispatcher;
    protected $currentListeners;

    /**
     * Constructor.
     *
     * @param FirewallMap $map A FirewallMap instance
     */
    public function __construct(FirewallMapInterface $map, EventDispatcherInterface $dispatcher)
    {
        $this->map = $map;
        $this->dispatcher = $dispatcher;
        $this->currentListeners = array();
    }

    /**
     * Handles security.
     *
     * @param EventInterface $event An EventInterface instance
     */
    public function handle(EventInterface $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->get('request_type')) {
            return;
        }

        $request = $event->get('request');

        // disconnect all listeners from core.security to avoid the overhead
        // of most listeners having to do this manually
        $this->dispatcher->disconnect('core.security');

        // ensure that listeners disconnect from wherever they have connected to
        foreach ($this->currentListeners as $listener) {
            $listener->unregister($this->dispatcher);
        }

        // register listeners for this firewall
        list($listeners, $exception) = $this->map->getListeners($request);
        if (null !== $exception) {
            $exception->register($this->dispatcher);
        }
        foreach ($listeners as $listener) {
            $listener->register($this->dispatcher);
        }

        // save current listener instances
        $this->currentListeners = $listeners;
        if (null !== $exception) {
            $this->currentListeners[] = $exception;
        }

        // initiate the listener chain
        $ret = $this->dispatcher->notifyUntil($securityEvent = new Event($request, 'core.security', array('request' => $request)));
        if ($securityEvent->isProcessed()) {
            $event->setProcessed();

            return $ret;
        }
    }
}
