<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Guard;

use Symphony\Component\EventDispatcher\EventDispatcherInterface;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symphony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symphony\Component\Security\Core\Exception\AuthenticationException;
use Symphony\Component\Security\Core\User\UserInterface;
use Symphony\Component\Security\Guard\Token\PostAuthenticationGuardToken;
use Symphony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symphony\Component\Security\Http\SecurityEvents;

/**
 * A utility class that does much of the *work* during the guard authentication process.
 *
 * By having the logic here instead of the listener, more of the process
 * can be called directly (e.g. for manual authentication) or overridden.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 *
 * @final
 */
class GuardAuthenticatorHandler
{
    private $tokenStorage;

    private $dispatcher;

    public function __construct(TokenStorageInterface $tokenStorage, EventDispatcherInterface $eventDispatcher = null)
    {
        $this->tokenStorage = $tokenStorage;
        $this->dispatcher = $eventDispatcher;
    }

    /**
     * Authenticates the given token in the system.
     */
    public function authenticateWithToken(TokenInterface $token, Request $request)
    {
        $this->tokenStorage->setToken($token);

        if (null !== $this->dispatcher) {
            $loginEvent = new InteractiveLoginEvent($request, $token);
            $this->dispatcher->dispatch(SecurityEvents::INTERACTIVE_LOGIN, $loginEvent);
        }
    }

    /**
     * Returns the "on success" response for the given GuardAuthenticator.
     */
    public function handleAuthenticationSuccess(TokenInterface $token, Request $request, AuthenticatorInterface $guardAuthenticator, string $providerKey): ?Response
    {
        $response = $guardAuthenticator->onAuthenticationSuccess($request, $token, $providerKey);

        // check that it's a Response or null
        if ($response instanceof Response || null === $response) {
            return $response;
        }

        throw new \UnexpectedValueException(sprintf(
            'The %s::onAuthenticationSuccess method must return null or a Response object. You returned %s.',
            get_class($guardAuthenticator),
            is_object($response) ? get_class($response) : gettype($response)
        ));
    }

    /**
     * Convenience method for authenticating the user and returning the
     * Response *if any* for success.
     */
    public function authenticateUserAndHandleSuccess(UserInterface $user, Request $request, AuthenticatorInterface $authenticator, string $providerKey): ?Response
    {
        // create an authenticated token for the User
        $token = $authenticator->createAuthenticatedToken($user, $providerKey);
        // authenticate this in the system
        $this->authenticateWithToken($token, $request);

        // return the success metric
        return $this->handleAuthenticationSuccess($token, $request, $authenticator, $providerKey);
    }

    /**
     * Handles an authentication failure and returns the Response for the
     * GuardAuthenticator.
     */
    public function handleAuthenticationFailure(AuthenticationException $authenticationException, Request $request, AuthenticatorInterface $guardAuthenticator, string $providerKey): ?Response
    {
        $token = $this->tokenStorage->getToken();
        if ($token instanceof PostAuthenticationGuardToken && $providerKey === $token->getProviderKey()) {
            $this->tokenStorage->setToken(null);
        }

        $response = $guardAuthenticator->onAuthenticationFailure($request, $authenticationException);
        if ($response instanceof Response || null === $response) {
            // returning null is ok, it means they want the request to continue
            return $response;
        }

        throw new \UnexpectedValueException(sprintf(
            'The %s::onAuthenticationFailure method must return null or a Response object. You returned %s.',
            get_class($guardAuthenticator),
            is_object($response) ? get_class($response) : gettype($response)
        ));
    }
}
