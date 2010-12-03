<?php

namespace Symfony\Component\HttpKernel\Security\Firewall;

use Symfony\Component\HttpKernel\Security\AccessMap;
use Symfony\Component\Security\Authentication\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\Event;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ChannelListener switches the HTTP protocol based on the access control
 * configuration.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ChannelListener implements ListenerInterface
{
    protected $map;
    protected $authenticationEntryPoint;
    protected $logger;

    public function __construct(AccessMap $map, AuthenticationEntryPointInterface $authenticationEntryPoint, LoggerInterface $logger = null)
    {
        $this->map = $map;
        $this->authenticationEntryPoint = $authenticationEntryPoint;
        $this->logger = $logger;
    }

    /**
     * 
     *
     * @param EventDispatcher $dispatcher An EventDispatcher instance
     * @param integer         $priority   The priority
     */
    public function register(EventDispatcher $dispatcher)
    {
        $dispatcher->connect('core.security', array($this, 'handle'), 0);
    }

    /**
     * {@inheritDoc}
     */
    public function unregister(EventDispatcher $dispatcher)
    {
    }
    
    /**
     * Handles channel management.
     *
     * @param Event $event An Event instance
     */
    public function handle(Event $event)
    {
        $request = $event->get('request');

        list($attributes, $channel) = $this->map->getPatterns($request);

        if ('https' === $channel && !$request->isSecure()) {
            if (null !== $this->logger) {
                $this->logger->debug('Redirecting to HTTPS');
            }

            $event->setReturnValue($this->authenticationEntryPoint->start($request));

            return true;
        }

        if ('http' === $channel && $request->isSecure()) {
            if (null !== $this->logger) {
                $this->logger->debug('Redirecting to HTTP');
            }

            $event->setReturnValue($this->authenticationEntryPoint->start($request));

            return true;
        }
    }
}
