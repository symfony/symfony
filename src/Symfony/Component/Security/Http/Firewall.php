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
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Firewall
{
    private $map;

    /**
     * Constructor.
     *
     * @param FirewallMap $map A FirewallMap instance
     */
    public function __construct(FirewallMapInterface $map)
    {
        $this->map = $map;
        $this->dispatcher = $dispatcher;
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

        // register listeners for this firewall
        list($listeners, $exception) = $this->map->getListeners($event->get('request'));
        if (null !== $exception) {
            $exception->register($this->dispatcher);
        }

        // initiate the listener chain
        foreach ($listeners as $listener) {
            $response = $listener->handle($event);

            if ($event->isProcessed()) {
                return $response;
            }
        }
    }
}
