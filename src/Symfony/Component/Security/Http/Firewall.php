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
    protected $map;
    protected $evm;
    protected $currentListeners;

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

        $request = $eventArgs->getRequest();

        // disconnect all listeners from onCoreSecurity to avoid the overhead
        // of most listeners having to do this manually
        $this->evm->removeEventListeners(Events::onCoreSecurity);

        // ensure that listeners disconnect from wherever they have connected to
        foreach ($this->currentListeners as $listener) {
            $listener->unregister($this->evm);
        }

        // register listeners for this firewall
        list($listeners, $exception) = $this->map->getListeners($request);
        if (null !== $exception) {
            $exception->register($this->evm);
        }
        foreach ($listeners as $listener) {
            $listener->register($this->evm);
        }

        // save current listener instances
        $this->currentListeners = $listeners;
        if (null !== $exception) {
            $this->currentListeners[] = $exception;
        }

        // initiate the listener chain
        $securityEventArgs = new GetResponseEventArgs($eventArgs->getKernel(), $request, $eventArgs->getRequestType());
        $this->evm->dispatchEvent($securityEventArgs);

        if ($securityEventArgs->hasResponse()) {
            $eventArgs->setResponse($securityEventArgs->getResponse());
        }
    }
}
