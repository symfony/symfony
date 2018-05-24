<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Guard;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Guard\Token\PostAuthenticationGuardToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

/**
 * A utility class that does much of the *work* during the guard authentication process.
 *
 * By having the logic here instead of the listener, more of the process
 * can be called directly (e.g. for manual authentication) or overridden.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
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
        $this->migrateSession($request);
        $this->tokenStorage->setToken($token);

        if (null !== $this->dispatcher) {
            $loginEvent = new InteractiveLoginEvent($request, $token);
            $this->dispatcher->dispatch(SecurityEvents::INTERACTIVE_LOGIN, $loginEvent);
        }
    }

    /**
     * Returns the "on success" response for the given GuardAuthenticator.
     *
     * @param TokenInterface              $token
     * @param Request                     $request
     * @param GuardAuthenticatorInterface $guardAuthenticator
     * @param string                      $providerKey        The provider (i.e. firewall) key
     *
     * @return null|Response
     */
    public function handleAuthenticationSuccess(TokenInterface $token, Request $request, GuardAuthenticatorInterface $guardAuthenticator, $providerKey)
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
     *
     * @param UserInterface               $user
     * @param Request                     $request
     * @param GuardAuthenticatorInterface $authenticator
     * @param string                      $providerKey   The provider (i.e. firewall) key
     *
     * @return Response|null
     */
    public function authenticateUserAndHandleSuccess(UserInterface $user, Request $request, GuardAuthenticatorInterface $authenticator, $providerKey)
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
     *
     * @param AuthenticationException     $authenticationException
     * @param Request                     $request
     * @param GuardAuthenticatorInterface $guardAuthenticator
     * @param string                      $providerKey             The key of the firewall
     *
     * @return null|Response
     */
    public function handleAuthenticationFailure(AuthenticationException $authenticationException, Request $request, GuardAuthenticatorInterface $guardAuthenticator, $providerKey)
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

    private function migrateSession(Request $request)
    {
        if (!$request->hasSession() || !$request->hasPreviousSession()) {
            return;
        }
        $request->getSession()->migrate(true);
    }
}
