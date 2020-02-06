<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authentication;

use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PreAuthenticationToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\AuthenticationEvents;
use Symfony\Component\Security\Core\Event\AuthenticationFailureEvent;
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\AuthenticationExpiredException;
use Symfony\Component\Security\Core\Exception\ProviderNotFoundException;
use Symfony\Component\Security\Http\Event\VerifyAuthenticatorCredentialsEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 * @author Ryan Weaver <ryan@knpuniversity.com>
 *
 * @experimental in 5.1
 */
class AuthenticatorManager implements AuthenticationManagerInterface
{
    use AuthenticatorManagerTrait;

    private $authenticators;
    private $eventDispatcher;
    private $eraseCredentials;

    /**
     * @param AuthenticatorInterface[] $authenticators The authenticators, with keys that match what's passed to AuthenticatorManagerListener
     */
    public function __construct(iterable $authenticators, EventDispatcherInterface $eventDispatcher, bool $eraseCredentials = true)
    {
        $this->authenticators = $authenticators;
        $this->eventDispatcher = $eventDispatcher;
        $this->eraseCredentials = $eraseCredentials;
    }

    public function setEventDispatcher(EventDispatcherInterface $dispatcher)
    {
        $this->eventDispatcher = $dispatcher;
    }

    public function authenticate(TokenInterface $token)
    {
        if (!$token instanceof PreAuthenticationToken) {
            /*
             * The listener *only* passes PreAuthenticationToken instances.
             * This means that an authenticated token (e.g. PostAuthenticationToken)
             * is being passed here, which happens if that token becomes
             * "not authenticated" (e.g. happens if the user changes between
             * requests). In this case, the user should be logged out.
             */

            // this should never happen - but technically, the token is
            // authenticated... so it could just be returned
            if ($token->isAuthenticated()) {
                return $token;
            }

            // this AccountStatusException causes the user to be logged out
            throw new AuthenticationExpiredException();
        }

        $authenticator = $this->findOriginatingAuthenticator($token);
        if (null === $authenticator) {
            $this->handleFailure(new ProviderNotFoundException(sprintf('Token with provider key "%s" did not originate from any of the authenticators.', $token->getAuthenticatorKey())), $token);
        }

        try {
            $result = $this->authenticateViaAuthenticator($authenticator, $token, $token->getProviderKey());
        } catch (AuthenticationException $exception) {
            $this->handleFailure($exception, $token);
        }

        if (null !== $result) {
            if (true === $this->eraseCredentials) {
                $result->eraseCredentials();
            }

            if (null !== $this->eventDispatcher) {
                $this->eventDispatcher->dispatch(new AuthenticationSuccessEvent($result), AuthenticationEvents::AUTHENTICATION_SUCCESS);
            }
        }

        return $result;
    }

    protected function getAuthenticatorKey(string $key): string
    {
        // Authenticators in the AuthenticatorManager are already indexed
        // by an unique key
        return $key;
    }

    private function authenticateViaAuthenticator(AuthenticatorInterface $authenticator, PreAuthenticationToken $token, string $providerKey): TokenInterface
    {
        // get the user from the Authenticator
        $user = $authenticator->getUser($token->getCredentials());
        if (null === $user) {
            throw new UsernameNotFoundException(sprintf('Null returned from "%s::getUser()".', \get_class($authenticator)));
        }

        if (!$user instanceof UserInterface) {
            throw new \UnexpectedValueException(sprintf('The %s::getUser() method must return a UserInterface. You returned %s.', \get_class($authenticator), \is_object($user) ? \get_class($user) : \gettype($user)));
        }

        $event = new VerifyAuthenticatorCredentialsEvent($authenticator, $token, $user);
        $this->eventDispatcher->dispatch($event);
        if (true !== $event->areCredentialsValid()) {
            throw new BadCredentialsException(sprintf('Authentication failed because %s did not approve the credentials.', \get_class($authenticator)));
        }

        // turn the UserInterface into a TokenInterface
        $authenticatedToken = $authenticator->createAuthenticatedToken($user, $providerKey);
        if (!$authenticatedToken instanceof TokenInterface) {
            throw new \UnexpectedValueException(sprintf('The %s::createAuthenticatedToken() method must return a TokenInterface. You returned %s.', \get_class($authenticator), \is_object($authenticatedToken) ? \get_class($authenticatedToken) : \gettype($authenticatedToken)));
        }

        return $authenticatedToken;
    }

    private function handleFailure(AuthenticationException $exception, TokenInterface $token)
    {
        if (null !== $this->eventDispatcher) {
            $this->eventDispatcher->dispatch(new AuthenticationFailureEvent($token, $exception), AuthenticationEvents::AUTHENTICATION_FAILURE);
        }

        $exception->setToken($token);

        throw $exception;
    }
}
