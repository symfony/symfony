<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Firewall;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategy;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * RememberMeListener implements authentication capabilities via a cookie.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @final
 */
class RememberMeListener
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
    public function __invoke(RequestEvent $event)
    {
        if (null !== $this->tokenStorage->getToken()) {
            return;
        }

        $request = $event->getRequest();
        try {
            if (null === $token = $this->rememberMeServices->autoLogin($request)) {
                return;
            }
        } catch (AuthenticationException $e) {
            if (null !== $this->logger) {
                $this->logger->warning(
                    'The token storage was not populated with remember-me token as the'
                   .' RememberMeServices was not able to create a token from the remember'
                   .' me information.', ['exception' => $e]
                );
            }

            $this->rememberMeServices->loginFail($request);

            if (!$this->catchExceptions) {
                throw $e;
            }

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
                $this->dispatcher->dispatch($loginEvent, SecurityEvents::INTERACTIVE_LOGIN);
            }

            if (null !== $this->logger) {
                $this->logger->debug('Populated the token storage with a remember-me token.');
            }
        } catch (AuthenticationException $e) {
            if (null !== $this->logger) {
                $this->logger->warning(
                    'The token storage was not populated with remember-me token as the'
                   .' AuthenticationManager rejected the AuthenticationToken returned'
                   .' by the RememberMeServices.', ['exception' => $e]
                );
            }

            $this->rememberMeServices->loginFail($request, $e);

            if (!$this->catchExceptions) {
                throw $e;
            }
        }
    }
}
