<?php

namespace Symfony\Component\HttpKernel\Security\Firewall;

use Symfony\Component\Security\SecurityContext;
use Symfony\Component\Security\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Authentication\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Security\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Exception\AuthenticationException;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * BasicAuthenticationListener implements Basic HTTP authentication.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class BasicAuthenticationListener implements ListenerInterface
{
    protected $securityContext;
    protected $authenticationManager;
    protected $authenticationEntryPoint;
    protected $logger;
    protected $ignoreFailure;

    public function __construct(SecurityContext $securityContext, AuthenticationManagerInterface $authenticationManager, AuthenticationEntryPointInterface $authenticationEntryPoint, LoggerInterface $logger = null)
    {
        $this->securityContext = $securityContext;
        $this->authenticationManager = $authenticationManager;
        $this->authenticationEntryPoint = $authenticationEntryPoint;
        $this->logger = $logger;
        $this->ignoreFailure = false;
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
     * Handles basic authentication.
     *
     * @param Event $event An Event instance
     */
    public function handle(Event $event)
    {
        $request = $event->get('request');

        if (false === $username = $request->server->get('PHP_AUTH_USER', false)) {
            return;
        }

        if (null !== $token = $this->securityContext->getToken()) {
            if ($token->isImmutable()) {
                return;
            }

            if ($token instanceof UsernamePasswordToken && $token->isAuthenticated() && (string) $token === $username) {
                return;
            }
        }

        if (null !== $this->logger) {
            $this->logger->debug(sprintf('Basic Authentication Authorization header found for user "%s"', $username));
        }

        try {
            $token = $this->authenticationManager->authenticate(new UsernamePasswordToken($username, $request->server->get('PHP_AUTH_PW')));
            $this->securityContext->setToken($token);
        } catch (AuthenticationException $failed) {
            $this->securityContext->setToken(null);

            if (null !== $this->logger) {
                $this->logger->debug(sprintf('Authentication request failed: %s', $failed->getMessage()));
            }

            if ($this->ignoreFailure) {
                return;
            }

            $event->setReturnValue($this->authenticationEntryPoint->start($request, $failed));

            return true;
        }
    }
}
