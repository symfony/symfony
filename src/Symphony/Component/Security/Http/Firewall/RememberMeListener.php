<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Http\Firewall;

use Psr\Log\LoggerInterface;
use Symphony\Component\HttpKernel\Event\GetResponseEvent;
use Symphony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symphony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symphony\Component\Security\Core\Exception\AuthenticationException;
use Symphony\Component\Security\Http\RememberMe\RememberMeServicesInterface;
use Symphony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symphony\Component\Security\Http\SecurityEvents;
use Symphony\Component\EventDispatcher\EventDispatcherInterface;
use Symphony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface;
use Symphony\Component\Security\Http\Session\SessionAuthenticationStrategy;

/**
 * RememberMeListener implements authentication capabilities via a cookie.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class RememberMeListener implements ListenerInterface
{
    private $tokenStorage;
    private $rememberMeServices;
    private $authenticationManager;
    private $logger;
    private $dispatcher;
    private $catchExceptions = true;
    private $sessionStrategy;

    public function __construct(TokenStorageInterface $tokenStorage, RememberMeServicesInterface $rememberMeServices, AuthenticationManagerInterface $authenticationManager, LoggerInterface $logger = null, EventDispatcherInterface $dispatcher = null, bool $catchExceptions = true, SessionAuthenticationStrategyInterface $sessionStrategy = null)
    {
        $this->tokenStorage = $tokenStorage;
        $this->rememberMeServices = $rememberMeServices;
        $this->authenticationManager = $authenticationManager;
        $this->logger = $logger;
        $this->dispatcher = $dispatcher;
        $this->catchExceptions = $catchExceptions;
        $this->sessionStrategy = null === $sessionStrategy ? new SessionAuthenticationStrategy(SessionAuthenticationStrategy::MIGRATE) : $sessionStrategy;
    }

    /**
     * Handles remember-me cookie based authentication.
     */
    public function handle(GetResponseEvent $event)
    {
        if (null !== $this->tokenStorage->getToken()) {
            return;
        }

        $request = $event->getRequest();
        if (null === $token = $this->rememberMeServices->autoLogin($request)) {
            return;
        }

        try {
            $token = $this->authenticationManager->authenticate($token);
            if ($request->hasSession() && $request->getSession()->isStarted()) {
                $this->sessionStrategy->onAuthentication($request, $token);
            }
            $this->tokenStorage->setToken($token);

            if (null !== $this->dispatcher) {
                $loginEvent = new InteractiveLoginEvent($request, $token);
                $this->dispatcher->dispatch(SecurityEvents::INTERACTIVE_LOGIN, $loginEvent);
            }

            if (null !== $this->logger) {
                $this->logger->debug('Populated the token storage with a remember-me token.');
            }
        } catch (AuthenticationException $e) {
            if (null !== $this->logger) {
                $this->logger->warning(
                    'The token storage was not populated with remember-me token as the'
                   .' AuthenticationManager rejected the AuthenticationToken returned'
                   .' by the RememberMeServices.', array('exception' => $e)
                );
            }

            $this->rememberMeServices->loginFail($request, $e);

            if (!$this->catchExceptions) {
                throw $e;
            }
        }
    }
}
