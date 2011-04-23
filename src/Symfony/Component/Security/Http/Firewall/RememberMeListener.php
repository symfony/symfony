<?php

namespace Symfony\Component\Security\Http\Firewall;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Events as KernelEvents;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CookieTheftException;
use Symfony\Component\Security\Core\SecurityContext;
use Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Events;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
    private $dispatcher;

    /**
     * Constructor
     *
     * @param SecurityContext                $securityContext
     * @param RememberMeServicesInterface    $rememberMeServices
     * @param AuthenticationManagerInterface $authenticationManager
     * @param LoggerInterface                $logger
     * @param EventDispatcherInterface       $dispatcher
     */
    public function __construct(SecurityContext $securityContext, RememberMeServicesInterface $rememberMeServices, AuthenticationManagerInterface $authenticationManager, LoggerInterface $logger = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->securityContext = $securityContext;
        $this->rememberMeServices = $rememberMeServices;
        $this->authenticationManager = $authenticationManager;
        $this->logger = $logger;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Handles remember-me cookie based authentication.
     *
     * @param GetResponseEvent $event A GetResponseEvent instance
     */
    public function handle(GetResponseEvent $event)
    {
        if (null !== $this->securityContext->getToken()) {
            return;
        }

        $request = $event->getRequest();
        if (null === $token = $this->rememberMeServices->autoLogin($request)) {
            return;
        }

        try {
            $token = $this->authenticationManager->authenticate($token);
            $this->securityContext->setToken($token);

            if (null !== $this->dispatcher) {
                $loginEvent = new InteractiveLoginEvent($request, $token);
                $this->dispatcher->dispatch(Events::onSecurityInteractiveLogin, $loginEvent);
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