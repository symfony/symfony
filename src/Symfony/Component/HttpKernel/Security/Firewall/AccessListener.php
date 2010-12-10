<?php

namespace Symfony\Component\HttpKernel\Security\Firewall;

use Symfony\Component\Security\SecurityContext;
use Symfony\Component\Security\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\HttpKernel\Security\AccessMap;
use Symfony\Component\Security\Authentication\AuthenticationManagerInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Security\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Exception\AccessDeniedException;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * AccessListener enforces access control rules.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class AccessListener implements ListenerInterface
{
    protected $context;
    protected $accessDecisionManager;
    protected $map;
    protected $authManager;
    protected $logger;

    public function __construct(SecurityContext $context, AccessDecisionManagerInterface $accessDecisionManager, AccessMap $map, AuthenticationManagerInterface $authManager, LoggerInterface $logger = null)
    {
        $this->context = $context;
        $this->accessDecisionManager = $accessDecisionManager;
        $this->map = $map;
        $this->authManager = $authManager;
        $this->logger = $logger;
    }

    /**
     * Registers a core.security listener to enforce authorization rules.
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
     * Handles access authorization.
     *
     * @param Event $event An Event instance
     */
    public function handle(Event $event)
    {
        if (null === $token = $this->context->getToken()) {
            throw new AuthenticationCredentialsNotFoundException('A Token was not found in the SecurityContext.');
        }

        $request = $event->get('request');

        list($attributes, $channel) = $this->map->getPatterns($request);

        if (null === $attributes) {
            return;
        }

        if (!$token->isAuthenticated()) {
            $token = $this->authManager->authenticate($token);
            $this->context->setToken($token);
        }

        if (!$this->accessDecisionManager->decide($token, $attributes, $request)) {
            throw new AccessDeniedException();
        }
    }
}
