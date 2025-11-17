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
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Debug\TraceableAuthenticator;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
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
 */
class AuthenticatorManager implements AuthenticatorManagerInterface, UserAuthenticatorInterface
{
    /**
     * @param iterable<mixed, AuthenticatorInterface> $authenticators
     */
    public function __construct(
        private iterable $authenticators,
        private TokenStorageInterface $tokenStorage,
        private EventDispatcherInterface $eventDispatcher,
        private string $firewallName,
        private ?LoggerInterface $logger = null,
        private bool $eraseCredentials = true,
        private ExposeSecurityLevel $exposeSecurityErrors = ExposeSecurityLevel::None,
        private array $requiredBadges = [],
    ) {
    }

    /**
     * @param BadgeInterface[]     $badges     Optionally, pass some Passport badges to use for the manual login
     * @param array<string, mixed> $attributes Optionally, pass some Passport attributes to use for the manual login
     */
    public function authenticateUser(UserInterface $user, AuthenticatorInterface $authenticator, Request $request, array $badges = [], array $attributes = []): ?Response
    {
        // create an authentication token for the User
        $passport = new SelfValidatingPassport(new UserBadge($user->getUserIdentifier(), fn () => $user), $badges);
        foreach ($attributes as $k => $v) {
            $passport->setAttribute($k, $v);
        }

        $token = $authenticator->createToken($passport, $this->firewallName);

        // announce the authentication token
        $token = $this->eventDispatcher->dispatch(new AuthenticationTokenCreatedEvent($token, $passport))->getAuthenticatedToken();

        // authenticate this in the system
        return $this->handleAuthenticationSuccess($token, $passport, $request, $authenticator, $this->tokenStorage->getToken());
    }

    public function supports(Request $request): ?bool
    {
        if (null !== $this->logger) {
            $context = ['firewall_name' => $this->firewallName];

            if (is_countable($this->authenticators)) {
                $context['authenticators'] = \count($this->authenticators);
            }

            $this->logger->debug('Checking for authenticator support.', $context);
        }

        $authenticators = [];
        $skippedAuthenticators = [];
        $lazy = true;
        foreach ($this->authenticators as $authenticator) {
            $this->logger?->debug('Checking support on authenticator.', ['firewall_name' => $this->firewallName, 'authenticator' => $authenticator::class]);

            if (!$authenticator instanceof AuthenticatorInterface) {
                throw new \InvalidArgumentException(\sprintf('Authenticator "%s" must implement "%s".', get_debug_type($authenticator), AuthenticatorInterface::class));
            }

            if (false !== $supports = $authenticator->supports($request)) {
                $authenticators[] = $authenticator;
                $lazy = $lazy && null === $supports;
            } else {
                $this->logger?->debug('Authenticator does not support the request.', ['firewall_name' => $this->firewallName, 'authenticator' => $authenticator::class]);
                $skippedAuthenticators[] = $authenticator;
            }
        }

        $request->attributes->set('_security_skipped_authenticators', $skippedAuthenticators);
        $request->attributes->set('_security_authenticators', $authenticators);

        if (!$authenticators) {
            return false;
        }

        return $lazy ? null : true;
    }

    public function authenticateRequest(Request $request): ?Response
    {
        $authenticators = $request->attributes->get('_security_authenticators');
        $request->attributes->remove('_security_authenticators');
        $request->attributes->remove('_security_skipped_authenticators');

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
                $this->logger?->debug('Skipping the "{authenticator}" authenticator as it did not support the request.', ['authenticator' => ($authenticator instanceof TraceableAuthenticator ? $authenticator->getAuthenticator() : $authenticator)::class]);

                continue;
            }

            $response = $this->executeAuthenticator($authenticator, $request);
            if (null !== $response) {
                $this->logger?->debug('The "{authenticator}" authenticator set the response. Any later authenticator will not be called', ['authenticator' => ($authenticator instanceof TraceableAuthenticator ? $authenticator->getAuthenticator() : $authenticator)::class]);

                return $response;
            }
        }

        return null;
    }

    private function executeAuthenticator(AuthenticatorInterface $authenticator, Request $request): ?Response
    {
        $passport = null;
        $previousToken = $this->tokenStorage->getToken();

        try {
            // get the passport from the Authenticator
            $passport = $authenticator->authenticate($request);

            // check the passport (e.g. password checking)
            $event = new CheckPassportEvent($authenticator, $passport);
            $this->eventDispatcher->dispatch($event);

            // check if all badges are resolved
            $resolvedBadges = [];
            foreach ($passport->getBadges() as $badge) {
                if (!$badge->isResolved()) {
                    throw new BadCredentialsException(\sprintf('Authentication failed: Security badge "%s" is not resolved, did you forget to register the correct listeners?', get_debug_type($badge)));
                }

                $resolvedBadges[] = $badge::class;
            }

            $missingRequiredBadges = array_diff($this->requiredBadges, $resolvedBadges);
            if ($missingRequiredBadges) {
                throw new BadCredentialsException(\sprintf('Authentication failed; Some badges marked as required by the firewall config are not available on the passport: "%s".', implode('", "', $missingRequiredBadges)));
            }

            // create the authentication token
            $authenticatedToken = $authenticator->createToken($passport, $this->firewallName);

            // announce the authentication token
            $authenticatedToken = $this->eventDispatcher->dispatch(new AuthenticationTokenCreatedEvent($authenticatedToken, $passport))->getAuthenticatedToken();

            $this->eventDispatcher->dispatch(new AuthenticationSuccessEvent($authenticatedToken), AuthenticationEvents::AUTHENTICATION_SUCCESS);

            $this->logger?->info('Authenticator successful!', ['token' => $authenticatedToken, 'authenticator' => ($authenticator instanceof TraceableAuthenticator ? $authenticator->getAuthenticator() : $authenticator)::class]);
        } catch (AuthenticationException $e) {
            // oh no! Authentication failed!
            $response = $this->handleAuthenticationFailure($e, $request, $authenticator, $passport);
            if ($response instanceof Response) {
                return $response;
            }

            return null;
        }

        // success! (sets the token on the token storage, etc)
        $response = $this->handleAuthenticationSuccess($authenticatedToken, $passport, $request, $authenticator, $previousToken);
        if ($response instanceof Response) {
            return $response;
        }

        $this->logger?->debug('Authenticator set no success response: request continues.', ['authenticator' => ($authenticator instanceof TraceableAuthenticator ? $authenticator->getAuthenticator() : $authenticator)::class]);

        return null;
    }

    private function handleAuthenticationSuccess(TokenInterface $authenticatedToken, Passport $passport, Request $request, AuthenticatorInterface $authenticator, ?TokenInterface $previousToken): ?Response
    {
        $this->tokenStorage->setToken($authenticatedToken);

        $response = $authenticator->onAuthenticationSuccess($request, $authenticatedToken, $this->firewallName);
        if ($authenticator instanceof InteractiveAuthenticatorInterface && $authenticator->isInteractive()) {
            $loginEvent = new InteractiveLoginEvent($request, $authenticatedToken);
            $this->eventDispatcher->dispatch($loginEvent, SecurityEvents::INTERACTIVE_LOGIN);
        }

        $this->eventDispatcher->dispatch($loginSuccessEvent = new LoginSuccessEvent($authenticator, $passport, $authenticatedToken, $request, $response, $this->firewallName, $previousToken));

        return $loginSuccessEvent->getResponse();
    }

    /**
     * Handles an authentication failure and returns the Response for the authenticator.
     */
    private function handleAuthenticationFailure(AuthenticationException $authenticationException, Request $request, AuthenticatorInterface $authenticator, ?Passport $passport): ?Response
    {
        $this->logger?->info('Authenticator failed.', ['exception' => $authenticationException, 'authenticator' => ($authenticator instanceof TraceableAuthenticator ? $authenticator->getAuthenticator() : $authenticator)::class]);

        // Avoid leaking error details in case of invalid user (e.g. user not found or invalid account status)
        // to prevent user enumeration via response content comparison
        if ($this->isSensitiveException($authenticationException)) {
            $authenticationException = new BadCredentialsException('Bad credentials.', 0, $authenticationException);
        }

        $response = $authenticator->onAuthenticationFailure($request, $authenticationException);
        if (null !== $response && null !== $this->logger) {
            $this->logger->debug('The "{authenticator}" authenticator set the failure response.', ['authenticator' => ($authenticator instanceof TraceableAuthenticator ? $authenticator->getAuthenticator() : $authenticator)::class]);
        }

        $this->eventDispatcher->dispatch($loginFailureEvent = new LoginFailureEvent($authenticationException, $authenticator, $request, $response, $this->firewallName, $passport));

        // returning null is ok, it means they want the request to continue
        return $loginFailureEvent->getResponse();
    }

    private function isSensitiveException(AuthenticationException $exception): bool
    {
        if (ExposeSecurityLevel::All !== $this->exposeSecurityErrors && $exception instanceof UserNotFoundException) {
            return true;
        }

        if (ExposeSecurityLevel::None === $this->exposeSecurityErrors && $exception instanceof AccountStatusException && !$exception instanceof CustomUserMessageAccountStatusException) {
            return true;
        }

        return false;
    }
}
