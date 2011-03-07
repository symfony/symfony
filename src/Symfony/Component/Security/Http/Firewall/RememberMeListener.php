<?php

namespace Symfony\Component\Security\Http\Firewall;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\RequestEventArgs;
use Symfony\Component\HttpKernel\Events as KernelEvents;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CookieTheftException;
use Symfony\Component\Security\Core\SecurityContext;
use Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEventArgs;
use Symfony\Component\Security\Http\Events;
use Doctrine\Common\EventManager;

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
    protected $securityContext;
    protected $rememberMeServices;
    protected $authenticationManager;
    protected $logger;
    protected $lastState;
    protected $evm;

    /**
     * Constructor
     *
     * @param SecurityContext $securityContext
     * @param RememberMeServicesInterface $rememberMeServices
     * @param AuthenticationManagerInterface $authenticationManager
     * @param LoggerInterface $logger
     */
    public function __construct(SecurityContext $securityContext, RememberMeServicesInterface $rememberMeServices, AuthenticationManagerInterface $authenticationManager, LoggerInterface $logger = null)
    {
        $this->securityContext = $securityContext;
        $this->rememberMeServices = $rememberMeServices;
        $this->authenticationManager = $authenticationManager;
        $this->logger = $logger;
    }

    /**
     * Listen to onCoreSecurity and filterCoreResponse event
     *
     * @param EventManager $evm An EventManager instance
     */
    public function register(EventManager $evm)
    {
        $evm->addEventListener(
            array(KernelEvents::onCoreSecurity, KernelEvents::filterCoreResponse),
            $this
        );

        $this->evm = $evm;
    }

    /**
     * {@inheritDoc}
     */
    public function unregister(EventManager $evm)
    {
        $evm->removeEventListener(KernelEvents::onCoreSecurity, $this);
    }

    /**
     * Handles remember-me cookie based authentication.
     *
     * @param RequestEventArgs $eventArgs A RequestEventArgs instance
     */
    public function onCoreSecurity(RequestEventArgs $eventArgs)
    {
        $this->lastState = null;

        if (null !== $this->securityContext->getToken()) {
            return;
        }

        try {
            if (null === $token = $this->rememberMeServices->autoLogin($eventArgs->getRequest())) {
                return;
            }

            try {
                if (null === $token = $this->authenticationManager->authenticate($token)) {
                    return;
                }

                $this->securityContext->setToken($token);

                if (null !== $this->evm) {
                    $loginEventArgs = new InteractiveLoginEventArgs($eventArgs->getRequest(), $token);
                    $this->evm->dispatchEvent(Events::onSecurityInteractiveLogin, $loginEventArgs);
                }

                if (null !== $this->logger) {
                    $this->logger->debug('SecurityContext populated with remember-me token.');
                }

                $this->lastState = $token;
            } catch (AuthenticationException $failed) {
                if (null !== $this->logger) {
                    $this->logger->debug(
                        'SecurityContext not populated with remember-me token as the'
                       .' AuthenticationManager rejected the AuthenticationToken returned'
                       .' by the RememberMeServices: '.$failed->getMessage()
                    );
                }

                $this->lastState = $failed;
            }
        } catch (AuthenticationException $cookieInvalid) {
            $this->lastState = $cookieInvalid;

            if (null !== $this->logger) {
                $this->logger->debug('The presented cookie was invalid: '.$cookieInvalid->getMessage());
            }

            // silently ignore everything except a cookie theft exception
            if ($cookieInvalid instanceof CookieTheftException) {
                throw $cookieInvalid;
            }
        }
    }

    /**
     * Update cookies
     * @param Event $event
     */
    public function filterCoreResponse(RequestEventArgs $eventArgs)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $eventArgs->getRequestType()) {
            return;
        }

        if ($this->lastState instanceof TokenInterface) {
            $this->rememberMeServices->loginSuccess($eventArgs->getRequest(), $eventArgs->getResponse(), $this->lastState);
        } else if ($this->lastState instanceof AuthenticationException) {
            $this->rememberMeServices->loginFail($eventArgs->getRequest(), $eventArgs->getResponse());
        }
    }
}