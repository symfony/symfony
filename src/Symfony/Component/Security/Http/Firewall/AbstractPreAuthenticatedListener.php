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

use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

/**
 * AbstractPreAuthenticatedListener is the base class for all listener that
 * authenticates users based on a pre-authenticated request (like a certificate
 * for instance).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class AbstractPreAuthenticatedListener implements ListenerInterface
{
    protected $logger;
    private $tokenStorage;
    private $authenticationManager;
    private $providerKey;
    private $dispatcher;

    public function __construct(TokenStorageInterface $tokenStorage, AuthenticationManagerInterface $authenticationManager, string $providerKey, LoggerInterface $logger = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->tokenStorage = $tokenStorage;
        $this->authenticationManager = $authenticationManager;
        $this->providerKey = $providerKey;
        $this->logger = $logger;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Handles pre-authentication.
     */
    final public function handle(GetResponseEvent $event): void
    {
        $request = $event->getRequest();

        try {
            list($user, $credentials) = $this->getPreAuthenticatedData($request);
        } catch (BadCredentialsException $e) {
            $this->clearToken($e);

            return;
        }

        if (null !== $this->logger) {
            $this->logger->debug('Checking current security token.', array('token' => (string) $this->tokenStorage->getToken()));
        }

        if (null !== $token = $this->tokenStorage->getToken()) {
            if ($token instanceof PreAuthenticatedToken && $this->providerKey == $token->getProviderKey() && $token->isAuthenticated() && $token->getUsername() === $user) {
                return;
            }
        }

        if (null !== $this->logger) {
            $this->logger->debug('Trying to pre-authenticate user.', array('username' => (string) $user));
        }

        try {
            $token = $this->authenticationManager->authenticate(new PreAuthenticatedToken($user, $credentials, $this->providerKey));

            if (null !== $this->logger) {
                $this->logger->info('Pre-authentication successful.', array('token' => (string) $token));
            }
            $this->tokenStorage->setToken($token);

            if (null !== $this->dispatcher) {
                $loginEvent = new InteractiveLoginEvent($request, $token);
                $this->dispatcher->dispatch(SecurityEvents::INTERACTIVE_LOGIN, $loginEvent);
            }
        } catch (AuthenticationException $e) {
            $this->clearToken($e);
        }
    }

    /**
     * Clears a PreAuthenticatedToken for this provider (if present).
     */
    private function clearToken(AuthenticationException $exception): void
    {
        $token = $this->tokenStorage->getToken();
        if ($token instanceof PreAuthenticatedToken && $this->providerKey === $token->getProviderKey()) {
            $this->tokenStorage->setToken(null);

            if (null !== $this->logger) {
                $this->logger->info('Cleared security token due to an exception.', array('exception' => $exception));
            }
        }
    }

    /**
     * Gets the user and credentials from the Request.
     *
     * @return array An array composed of the user and the credentials
     */
    abstract protected function getPreAuthenticatedData(Request $request);
}
