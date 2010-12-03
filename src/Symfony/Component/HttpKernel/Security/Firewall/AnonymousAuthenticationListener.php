<?php

namespace Symfony\Component\HttpKernel\Security\Firewall;

use Symfony\Component\Security\SecurityContext;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Security\Authentication\Token\AnonymousToken;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * AnonymousAuthenticationListener automatically addds a Token if none is
 * already present.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class AnonymousAuthenticationListener implements ListenerInterface
{
    protected $context;
    protected $key;
    protected $logger;

    public function __construct(SecurityContext $context, $key, LoggerInterface $logger = null)
    {
        $this->context = $context;
        $this->key     = $key;
        $this->logger  = $logger;
    }

    /**
     * Registers a core.security listener to load the SecurityContext from the
     * session.
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
     * Handles anonymous authentication.
     *
     * @param Event $event An Event instance
     */
    public function handle(Event $event)
    {
        $request = $event->get('request');

        if (null !== $this->context->getToken()) {
            return;
        }

        $this->context->setToken(new AnonymousToken($this->key, 'anon.', array()));

        if (null !== $this->logger) {
            $this->logger->debug(sprintf('Populated SecurityContext with an anonymous Token'));
        }
    }
}
