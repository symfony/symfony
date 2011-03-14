<?php

namespace Symfony\Component\Security\Http\Firewall;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CookieTheftException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\SecurityContext;
use Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * RememberMeListener implements authentication capabilities via a cookie
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class RememberMeListener implements ListenerInterface
{
    private $securityContext;
    private $rememberMeServices;
    private $authenticationManager;
    private $logger;
    private $eventDispatcher;

    /**
     * Constructor
     *
     * @param SecurityContext $securityContext
     * @param RememberMeServicesInterface $rememberMeServices
     * @param AuthenticationManagerInterface $authenticationManager
     * @param LoggerInterface $logger
     */
    public function __construct(SecurityContext $securityContext, RememberMeServicesInterface $rememberMeServices, AuthenticationManagerInterface $authenticationManager, LoggerInterface $logger = null, EventDispatcherInterface $eventDispatcher = null)
    {
        $this->securityContext = $securityContext;
        $this->rememberMeServices = $rememberMeServices;
        $this->authenticationManager = $authenticationManager;
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Handles remember-me cookie based authentication.
     *
     * @param Event $event An Event instance
     */
    public function handle(EventInterface $event)
    {
        if (null !== $this->securityContext->getToken()) {
            return;
        }

        $request = $event->get('request');
        if (null === $token = $this->rememberMeServices->autoLogin($request)) {
            return;
        }

        try {
            $token = $this->authenticationManager->authenticate($token);
            $this->securityContext->setToken($token);

            if (null !== $this->eventDispatcher) {
                $this->eventDispatcher->notify(new Event($this, 'security.interactive_login', array('request' => $request, 'token' => $token)));
            }

            if (null !== $this->logger) {
                $this->logger->debug('SecurityContext populated with remember-me token.');
            }
        } catch (AuthenticationException $failed) {
            if (null !== $this->logger) {
                $this->logger->debug(
                    'SecurityContext not populated with remember-me token as the'
                   .' AuthenticationManager rejected the AuthenticationToken returned'
                   .' by the RememberMeServices: '.$failed->getMessage()
                );
            }

            $this->rememberMeServices->loginFail($request);
        }
    }
}