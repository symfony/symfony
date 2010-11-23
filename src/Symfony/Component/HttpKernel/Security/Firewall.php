<?php

namespace Symfony\Component\HttpKernel\Security;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Firewall uses a FirewallMap to register security listeners for the given
 * request.
 *
 * It allows for different security strategies within the same application
 * (a Basic authentication for the /api, and a web based authentication for
 * everything else for instance).
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Firewall
{
    protected $map;
    protected $dispatcher;

    /**
     * Constructor.
     *
     * @param FirewallMap $map A FirewallMap instance
     */
    public function __construct(FirewallMap $map)
    {
        $this->map = $map;
    }

    /**
     * Registers a core.request listener to enforce security.
     *
     * @param EventDispatcher $dispatcher An EventDispatcher instance
     * @param integer         $priority   The priority
     */
    public function register(EventDispatcher $dispatcher, $priority = 0)
    {
        $dispatcher->connect('core.request', array($this, 'handle'), $priority);
        $this->dispatcher = $dispatcher;
    }

    /**
     * Handles security.
     *
     * @param Event $event An Event instance
     */
    public function handle(Event $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->get('request_type')) {
            return;
        }

        $request = $event->get('request');

        $this->dispatcher->disconnect('core.security');
        list($listeners, $exception) = $this->map->getListeners($request);
        if (null !== $exception) {
            $exception->register($this->dispatcher);
        }
        foreach ($listeners as $listener) {
            $listener->register($this->dispatcher);
        }

        $e = $this->dispatcher->notifyUntil(new Event($request, 'core.security', array('request' => $request)));
        if ($e->isProcessed()) {
            $event->setReturnValue($e->getReturnValue());

            return true;
        }

        return;
    }
}
