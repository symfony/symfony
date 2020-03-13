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
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\VerifyAuthenticatorCredentialsEvent;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 * @author Ryan Weaver <ryan@symfonycasts.com>
 * @author Amaury Leroux de Lens <amaury@lerouxdelens.com>
 *
 * @experimental in 5.1
 */
class AuthenticatorManager implements AuthenticatorManagerInterface, UserAuthenticatorInterface
{
    private $authenticators;
    private $tokenStorage;
    private $eventDispatcher;
    private $eraseCredentials;
    private $logger;
    private $providerKey;

    /**
     * @param AuthenticatorInterface[] $authenticators The authenticators, with their unique providerKey as key
     */
    public function __construct(iterable $authenticators, TokenStorageInterface $tokenStorage, EventDispatcherInterface $eventDispatcher, string $providerKey, ?LoggerInterface $logger = null, bool $eraseCredentials = true)
    {
        $this->authenticators = $authenticators;
        $this->tokenStorage = $tokenStorage;
        $this->eventDispatcher = $eventDispatcher;
        $this->providerKey = $providerKey;
        $this->logger = $logger;
        $this->eraseCredentials = $eraseCredentials;
    }

    public function authenticateUser(UserInterface $user, AuthenticatorInterface $authenticator, Request $request): ?Response
    {
        // create an authenticated token for the User
        $token = $authenticator->createAuthenticatedToken($user, $this->providerKey);

        // authenticate this in the system
        return $this->handleAuthenticationSuccess($token, $request, $authenticator);
    }

    public function supports(Request $request): ?bool
    {
        if (null !== $this->logger) {
            $context = ['firewall_key' => $this->providerKey];

            if ($this->authenticators instanceof \Countable || \is_array($this->authenticators)) {
                $context['authenticators'] = \count($this->authenticators);
            }

            $this->logger->debug('Checking for guard authentication credentials.', $context);
        }

        $authenticators = [];
        $lazy = true;
        foreach ($this->authenticators as $key => $authenticator) {
            if (null !== $this->logger) {
                $this->logger->debug('Checking support on authenticator.', ['firewall_key' => $this->providerKey, 'authenticator' => \get_class($authenticator)]);
            }

            if (false !== $supports = $authenticator->supports($request)) {
                $authenticators[$key] = $authenticator;
                $lazy = $lazy && null === $supports;
            } elseif (null !== $this->logger) {
                $this->logger->debug('Authenticator does not support the request.', ['firewall_key' => $this->providerKey, 'authenticator' => \get_class($authenticator)]);
            }
        }

        if (!$authenticators) {
            return false;
        }

        $request->attributes->set('_guard_authenticators', $authenticators);

        return $lazy ? null : true;
    }

    public function authenticateRequest(Request $request): ?Response
    {
        $authenticators = $request->attributes->get('_guard_authenticators');
        $request->attributes->remove('_guard_authenticators');
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
        foreach ($authenticators as $key => $authenticator) {
            // recheck if the authenticator still supports the listener. support() is called
            // eagerly (before token storage is initialized), whereas authenticate() is called
            // lazily (after initialization). This is important for e.g. the AnonymousAuthenticator
            // as its support is relying on the (initialized) token in the TokenStorage.
            if (false === $authenticator->supports($request)) {
                if (null !== $this->logger) {
                    $this->logger->debug('Skipping the "{authenticator}" authenticator as it did not support the request.', ['authenticator' => \get_class($authenticator)]);
                }
                continue;
            }

            $response = $this->executeAuthenticator($key, $authenticator, $request);
            if (null !== $response) {
                if (null !== $this->logger) {
                    $this->logger->debug('The "{authenticator}" authenticator set the response. Any later authenticator will not be called', ['authenticator' => \get_class($authenticator)]);
                }

                return $response;
            }
        }

        return null;
    }

    private function executeAuthenticator(string $uniqueAuthenticatorKey, AuthenticatorInterface $authenticator, Request $request): ?Response
    {
        try {
            if (null !== $this->logger) {
                $this->logger->debug('Calling getCredentials() on authenticator.', ['firewall_key' => $this->providerKey, 'authenticator' => \get_class($authenticator)]);
            }

            // allow the authenticator to fetch authentication info from the request
            $credentials = $authenticator->getCredentials($request);

            if (null === $credentials) {
                throw new \UnexpectedValueException(sprintf('The return value of "%1$s::getCredentials()" must not be null. Return false from "%1$s::supports()" instead.', \get_class($authenticator)));
            }

            // authenticate the credentials (e.g. check password)
            $token = $this->authenticateViaAuthenticator($authenticator, $credentials);

            if (null !== $this->logger) {
                $this->logger->info('Authenticator successful!', ['token' => $token, 'authenticator' => \get_class($authenticator)]);
            }

            // success! (sets the token on the token storage, etc)
            $response = $this->handleAuthenticationSuccess($token, $request, $authenticator);
            if ($response instanceof Response) {
                return $response;
            }

            if (null !== $this->logger) {
                $this->logger->debug('Authenticator set no success response: request continues.', ['authenticator' => \get_class($authenticator)]);
            }

            return null;
        } catch (AuthenticationException $e) {
            // oh no! Authentication failed!
            $response = $this->handleAuthenticationFailure($e, $request, $authenticator);
            if ($response instanceof Response) {
                return $response;
            }

            return null;
        }
    }

    private function authenticateViaAuthenticator(AuthenticatorInterface $authenticator, $credentials): TokenInterface
    {
        // get the user from the Authenticator
        $user = $authenticator->getUser($credentials);
        if (null === $user) {
            throw new UsernameNotFoundException(sprintf('Null returned from "%s::getUser()".', \get_class($authenticator)));
        }

        $event = new VerifyAuthenticatorCredentialsEvent($authenticator, $credentials, $user);
        $this->eventDispatcher->dispatch($event);
        if (true !== $event->areCredentialsValid()) {
            throw new BadCredentialsException(sprintf('Authentication failed because "%s" did not approve the credentials.', \get_class($authenticator)));
        }

        // turn the UserInterface into a TokenInterface
        $authenticatedToken = $authenticator->createAuthenticatedToken($user, $this->providerKey);

        if (true === $this->eraseCredentials) {
            $authenticatedToken->eraseCredentials();
        }

        if (null !== $this->eventDispatcher) {
            $this->eventDispatcher->dispatch(new AuthenticationSuccessEvent($authenticatedToken), AuthenticationEvents::AUTHENTICATION_SUCCESS);
        }

        return $authenticatedToken;
    }

    private function handleAuthenticationSuccess(TokenInterface $authenticatedToken, Request $request, AuthenticatorInterface $authenticator): ?Response
    {
        $this->tokenStorage->setToken($authenticatedToken);

        $response = $authenticator->onAuthenticationSuccess($request, $authenticatedToken, $this->providerKey);
        if ($authenticator instanceof InteractiveAuthenticatorInterface && $authenticator->isInteractive()) {
            $loginEvent = new InteractiveLoginEvent($request, $authenticatedToken);
            $this->eventDispatcher->dispatch($loginEvent, SecurityEvents::INTERACTIVE_LOGIN);
        }

        $this->eventDispatcher->dispatch($loginSuccessEvent = new LoginSuccessEvent($authenticator, $authenticatedToken, $request, $response, $this->providerKey));

        return $loginSuccessEvent->getResponse();
    }

    /**
     * Handles an authentication failure and returns the Response for the authenticator.
     */
    private function handleAuthenticationFailure(AuthenticationException $authenticationException, Request $request, AuthenticatorInterface $authenticator): ?Response
    {
        if (null !== $this->logger) {
            $this->logger->info('Authenticator failed.', ['exception' => $authenticationException, 'authenticator' => \get_class($authenticator)]);
        }

        $response = $authenticator->onAuthenticationFailure($request, $authenticationException);
        if (null !== $response && null !== $this->logger) {
            $this->logger->debug('The "{authenticator}" authenticator set the failure response.', ['authenticator' => \get_class($authenticator)]);
        }

        $this->eventDispatcher->dispatch($loginFailureEvent = new LoginFailureEvent($authenticationException, $authenticator, $request, $response, $this->providerKey));

        // returning null is ok, it means they want the request to continue
        return $loginFailureEvent->getResponse();
    }
}
