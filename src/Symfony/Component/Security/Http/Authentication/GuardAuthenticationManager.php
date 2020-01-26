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
use Symfony\Component\Security\Http\Authentication\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticationGuardToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\AuthenticationEvents;
use Symfony\Component\Security\Core\Event\AuthenticationFailureEvent;
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\AuthenticationExpiredException;
use Symfony\Component\Security\Core\Exception\ProviderNotFoundException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 * @author Ryan Weaver <ryan@knpuniversity.com>
 *
 * @experimental in 5.1
 */
class GuardAuthenticationManager implements AuthenticationManagerInterface
{
    use GuardAuthenticationManagerTrait;

    private $guardAuthenticators;
    private $userChecker;
    private $eraseCredentials;
    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    /**
     * @param iterable|AuthenticatorInterface[] $guardAuthenticators The authenticators, with keys that match what's passed to GuardAuthenticationListener
     */
    public function __construct($guardAuthenticators, UserCheckerInterface $userChecker, bool $eraseCredentials = true)
    {
        $this->guardAuthenticators = $guardAuthenticators;
        $this->userChecker = $userChecker;
        $this->eraseCredentials = $eraseCredentials;
    }

    public function setEventDispatcher(EventDispatcherInterface $dispatcher)
    {
        $this->eventDispatcher = $dispatcher;
    }

    public function authenticate(TokenInterface $token)
    {
        if (!$token instanceof PreAuthenticationGuardToken) {
            /*
             * The listener *only* passes PreAuthenticationGuardToken instances.
             * This means that an authenticated token (e.g. PostAuthenticationGuardToken)
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

        $guard = $this->findOriginatingAuthenticator($token);
        if (null === $guard) {
            $this->handleFailure(new ProviderNotFoundException(sprintf('Token with provider key "%s" did not originate from any of the guard authenticators.', $token->getGuardProviderKey())), $token);
        }

        try {
            $result = $this->authenticateViaGuard($guard, $token, $token->getProviderKey());
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

    private function handleFailure(AuthenticationException $exception, TokenInterface $token)
    {
        if (null !== $this->eventDispatcher) {
            $this->eventDispatcher->dispatch(new AuthenticationFailureEvent($token, $exception), AuthenticationEvents::AUTHENTICATION_FAILURE);
        }

        $exception->setToken($token);

        throw $exception;
    }

    protected function getGuardKey(string $key): string
    {
        // Guard authenticators in the GuardAuthenticationManager are already indexed
        // by an unique key
        return $key;
    }
}
