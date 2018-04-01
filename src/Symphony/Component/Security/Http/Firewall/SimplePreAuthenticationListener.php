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

use Symphony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Psr\Log\LoggerInterface;
use Symphony\Component\HttpKernel\Event\GetResponseEvent;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\Security\Http\Authentication\SimplePreAuthenticatorInterface;
use Symphony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symphony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symphony\Component\Security\Core\Exception\AuthenticationException;
use Symphony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symphony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symphony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symphony\Component\Security\Http\SecurityEvents;
use Symphony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * SimplePreAuthenticationListener implements simple proxying to an authenticator.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class SimplePreAuthenticationListener implements ListenerInterface
{
    private $tokenStorage;
    private $authenticationManager;
    private $providerKey;
    private $simpleAuthenticator;
    private $logger;
    private $dispatcher;

    public function __construct(TokenStorageInterface $tokenStorage, AuthenticationManagerInterface $authenticationManager, string $providerKey, SimplePreAuthenticatorInterface $simpleAuthenticator, LoggerInterface $logger = null, EventDispatcherInterface $dispatcher = null)
    {
        if (empty($providerKey)) {
            throw new \InvalidArgumentException('$providerKey must not be empty.');
        }

        $this->tokenStorage = $tokenStorage;
        $this->authenticationManager = $authenticationManager;
        $this->providerKey = $providerKey;
        $this->simpleAuthenticator = $simpleAuthenticator;
        $this->logger = $logger;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Handles basic authentication.
     */
    public function handle(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        if (null !== $this->logger) {
            $this->logger->info('Attempting SimplePreAuthentication.', array('key' => $this->providerKey, 'authenticator' => get_class($this->simpleAuthenticator)));
        }

        if (null !== $this->tokenStorage->getToken() && !$this->tokenStorage->getToken() instanceof AnonymousToken) {
            return;
        }

        try {
            $token = $this->simpleAuthenticator->createToken($request, $this->providerKey);

            // allow null to be returned to skip authentication
            if (null === $token) {
                return;
            }

            $token = $this->authenticationManager->authenticate($token);
            $this->tokenStorage->setToken($token);

            if (null !== $this->dispatcher) {
                $loginEvent = new InteractiveLoginEvent($request, $token);
                $this->dispatcher->dispatch(SecurityEvents::INTERACTIVE_LOGIN, $loginEvent);
            }
        } catch (AuthenticationException $e) {
            $this->tokenStorage->setToken(null);

            if (null !== $this->logger) {
                $this->logger->info('SimplePreAuthentication request failed.', array('exception' => $e, 'authenticator' => get_class($this->simpleAuthenticator)));
            }

            if ($this->simpleAuthenticator instanceof AuthenticationFailureHandlerInterface) {
                $response = $this->simpleAuthenticator->onAuthenticationFailure($request, $e);
                if ($response instanceof Response) {
                    $event->setResponse($response);
                } elseif (null !== $response) {
                    throw new \UnexpectedValueException(sprintf('The %s::onAuthenticationFailure method must return null or a Response object', get_class($this->simpleAuthenticator)));
                }
            }

            return;
        }

        if ($this->simpleAuthenticator instanceof AuthenticationSuccessHandlerInterface) {
            $response = $this->simpleAuthenticator->onAuthenticationSuccess($request, $token);
            if ($response instanceof Response) {
                $event->setResponse($response);
            } elseif (null !== $response) {
                throw new \UnexpectedValueException(sprintf('The %s::onAuthenticationSuccess method must return null or a Response object', get_class($this->simpleAuthenticator)));
            }
        }
    }
}
