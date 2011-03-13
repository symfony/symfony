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

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Events;
use Symfony\Component\HttpKernel\Event\GetResponseEventArgs;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\Common\EventManager;

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
class Firewall
{
    private $map;
    private $evm;
    private $currentListeners;

    /**
     * Constructor.
     *
     * @param FirewallMap $map A FirewallMap instance
     */
    public function __construct(FirewallMapInterface $map, EventManager $evm)
    {
        $this->map = $map;
        $this->evm = $evm;
        $this->currentListeners = array();
    }

    /**
     * Handles security.
     *
     * @param GetResponseEventArgs $eventArgs An GetResponseEventArgs instance
     */
    public function onCoreRequest(GetResponseEventArgs $eventArgs)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $eventArgs->getRequestType()) {
            return;
        }

        // register listeners for this firewall
        list($listeners, $exception) = $this->map->getListeners($eventArgs->getRequest());
        if (null !== $exception) {
            $exception->register($this->evm);
        }

        // initiate the listener chain
        foreach ($listeners as $listener) {
            $response = $listener->handle($eventArgs);

            if ($eventArgs->hasResponse()) {
                break;
            }
        }
    }
}
