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

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\AuthenticationEvents;
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\AuthenticationTokenCreatedEvent;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 * @author Ryan Weaver <ryan@symfonycasts.com>
 * @author Amaury Leroux de Lens <amaury@lerouxdelens.com>
 *
 * @experimental in 5.2
 */
class AuthenticatorManager implements AuthenticatorManagerInterface, UserAuthenticatorInterface
{
    private $authenticators;
    private $tokenStorage;
    private $eventDispatcher;
    private $eraseCredentials;
    private $logger;
    private $firewallName;
    private $hideUserNotFoundExceptions;

    /**
     * @param AuthenticatorInterface[] $authenticators
     */
    public function __construct(iterable $authenticators, TokenStorageInterface $tokenStorage, EventDispatcherInterface $eventDispatcher, string $firewallName, ?LoggerInterface $logger = null, bool $eraseCredentials = true, bool $hideUserNotFoundExceptions = true)
    {
        $this->authenticators = $authenticators;
        $this->tokenStorage = $tokenStorage;
        $this->eventDispatcher = $eventDispatcher;
        $this->firewallName = $firewallName;
        $this->logger = $logger;
        $this->eraseCredentials = $eraseCredentials;
        $this->hideUserNotFoundExceptions = $hideUserNotFoundExceptions;
    }

    /**
     * @param BadgeInterface[] $badges Optionally, pass some Passport badges to use for the manual login
     */
    public function authenticateUser(UserInterface $user, AuthenticatorInterface $authenticator, Request $request, array $badges = []): ?Response
    {
        // create an authenticated token for the User
        $token = $authenticator->createAuthenticatedToken($passport = new SelfValidatingPassport(new UserBadge($user->getUsername(), function () use ($user) { return $user; }), $badges), $this->firewallName);

        // announce the authenticated token
        $token = $this->eventDispatcher->dispatch(new AuthenticationTokenCreatedEvent($token))->getAuthenticatedToken();

        // authenticate this in the system
        return $this->handleAuthenticationSuccess($token, $passport, $request, $authenticator);
    }

    public function supports(Request $request): ?bool
    {
        if (null !== $this->logger) {
            $context = ['firewall_name' => $this->firewallName];

            if ($this->authenticators instanceof \Countable || \is_array($this->authenticators)) {
                $context['authenticators'] = \count($this->authenticators);
            }

            $this->logger->debug('Checking for authenticator support.', $context);
        }

        $authenticators = [];
        $lazy = true;
        foreach ($this->authenticators as $authenticator) {
            if (null !== $this->logger) {
                $this->logger->debug('Checking support on authenticator.', ['firewall_name' => $this->firewallName, 'authenticator' => \get_class($authenticator)]);
            }

            if (false !== $supports = $authenticator->supports($request)) {
                $authenticators[] = $authenticator;
                $lazy = $lazy && null === $supports;
            } elseif (null !== $this->logger) {
                $this->logger->debug('Authenticator does not support the request.', ['firewall_name' => $this->firewallName, 'authenticator' => \get_class($authenticator)]);
            }
        }

        if (!$authenticators) {
            return false;
        }

        $request->attributes->set('_security_authenticators', $authenticators);

        return $lazy ? null : true;
    }

    public function authenticateRequest(Request $request): ?Response
    {
        $authenticators = $request->attributes->get('_security_authenticators');
        $request->attributes->remove('_security_authenticators');
        if (!$authenticators) {
            return null;
        }

        return $this->executeAuthenticators($authenticators, $request);
    }

    /**
     * @param AuthenticatorInterface[] $authenticators
     */
    private function executeAuthenticators(array $authenticators, Request $request): ?Response
    {
        foreach ($authenticators as $authenticator) {
            // recheck if the authenticator still supports the listener. supports() is called
            // eagerly (before token storage is initialized), whereas authenticate() is called
            // lazily (after initialization).
            if (false === $authenticator->supports($request)) {
                if (null !== $this->logger) {
                    $this->logger->debug('Skipping the "{authenticator}" authenticator as it did not support the request.', ['authenticator' => \get_class($authenticator)]);
                }

                continue;
            }

            $response = $this->executeAuthenticator($authenticator, $request);
            if (null !== $response) {
                if (null !== $this->logger) {
                    $this->logger->debug('The "{authenticator}" authenticator set the response. Any later authenticator will not be called', ['authenticator' => \get_class($authenticator)]);
                }

                return $response;
            }
        }

        return null;
    }

    private function executeAuthenticator(AuthenticatorInterface $authenticator, Request $request): ?Response
    {
        $passport = null;

        try {
            // get the passport from the Authenticator
            $passport = $authenticator->authenticate($request);

            // check the passport (e.g. password checking)
            $event = new CheckPassportEvent($authenticator, $passport);
            $this->eventDispatcher->dispatch($event);

            // check if all badges are resolved
            $passport->checkIfCompletelyResolved();

            // create the authenticated token
            $authenticatedToken = $authenticator->createAuthenticatedToken($passport, $this->firewallName);

            // announce the authenticated token
            $authenticatedToken = $this->eventDispatcher->dispatch(new AuthenticationTokenCreatedEvent($authenticatedToken))->getAuthenticatedToken();

            if (true === $this->eraseCredentials) {
                $authenticatedToken->eraseCredentials();
            }

            $this->eventDispatcher->dispatch(new AuthenticationSuccessEvent($authenticatedToken), AuthenticationEvents::AUTHENTICATION_SUCCESS);

            if (null !== $this->logger) {
                $this->logger->info('Authenticator successful!', ['token' => $authenticatedToken, 'authenticator' => \get_class($authenticator)]);
            }
        } catch (AuthenticationException $e) {
            // oh no! Authentication failed!
            $response = $this->handleAuthenticationFailure($e, $request, $authenticator, $passport);
            if ($response instanceof Response) {
                return $response;
            }

            return null;
        }

        // success! (sets the token on the token storage, etc)
        $response = $this->handleAuthenticationSuccess($authenticatedToken, $passport, $request, $authenticator);
        if ($response instanceof Response) {
            return $response;
        }

        if (null !== $this->logger) {
            $this->logger->debug('Authenticator set no success response: request continues.', ['authenticator' => \get_class($authenticator)]);
        }

        return null;
    }

    private function handleAuthenticationSuccess(TokenInterface $authenticatedToken, PassportInterface $passport, Request $request, AuthenticatorInterface $authenticator): ?Response
    {
        $this->tokenStorage->setToken($authenticatedToken);

        $response = $authenticator->onAuthenticationSuccess($request, $authenticatedToken, $this->firewallName);
        if ($authenticator instanceof InteractiveAuthenticatorInterface && $authenticator->isInteractive()) {
            $loginEvent = new InteractiveLoginEvent($request, $authenticatedToken);
            $this->eventDispatcher->dispatch($loginEvent, SecurityEvents::INTERACTIVE_LOGIN);
        }

        $this->eventDispatcher->dispatch($loginSuccessEvent = new LoginSuccessEvent($authenticator, $passport, $authenticatedToken, $request, $response, $this->firewallName));

        return $loginSuccessEvent->getResponse();
    }

    /**
     * Handles an authentication failure and returns the Response for the authenticator.
     */
    private function handleAuthenticationFailure(AuthenticationException $authenticationException, Request $request, AuthenticatorInterface $authenticator, ?PassportInterface $passport): ?Response
    {
        if (null !== $this->logger) {
            $this->logger->info('Authenticator failed.', ['exception' => $authenticationException, 'authenticator' => \get_class($authenticator)]);
        }

        // Avoid leaking error details in case of invalid user (e.g. user not found or invalid account status)
        // to prevent user enumeration via response content comparison
        if ($this->hideUserNotFoundExceptions && ($authenticationException instanceof UsernameNotFoundException || ($authenticationException instanceof AccountStatusException && !$authenticationException instanceof CustomUserMessageAccountStatusException))) {
            $authenticationException = new BadCredentialsException('Bad credentials.', 0, $authenticationException);
        }

        $response = $authenticator->onAuthenticationFailure($request, $authenticationException);
        if (null !== $response && null !== $this->logger) {
            $this->logger->debug('The "{authenticator}" authenticator set the failure response.', ['authenticator' => \get_class($authenticator)]);
        }

        $this->eventDispatcher->dispatch($loginFailureEvent = new LoginFailureEvent($authenticationException, $authenticator, $request, $response, $this->firewallName, $passport));

        // returning null is ok, it means they want the request to continue
        return $loginFailureEvent->getResponse();
    }
}
