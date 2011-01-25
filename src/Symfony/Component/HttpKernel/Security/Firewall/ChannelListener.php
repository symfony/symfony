<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Security\Firewall;

use Symfony\Component\HttpKernel\Security\AccessMap;
use Symfony\Component\Security\Authentication\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventInterface;

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
     * @param EventDispatcherInterface $dispatcher An EventDispatcherInterface instance
     * @param integer                  $priority   The priority
     */
    public function register(EventDispatcherInterface $dispatcher)
    {
        $dispatcher->connect('core.security', array($this, 'handle'), 0);
    }

    /**
     * {@inheritDoc}
     */
    public function unregister(EventDispatcherInterface $dispatcher)
    {
    }
    
    /**
     * Handles channel management.
     *
     * @param EventInterface $event An EventInterface instance
     */
    public function handle(EventInterface $event)
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
