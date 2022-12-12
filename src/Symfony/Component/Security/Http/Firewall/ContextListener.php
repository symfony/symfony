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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\LegacyPasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Event\TokenDeauthenticatedEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * ContextListener manages the SecurityContext persistence through a session.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @final
 */
class ContextListener extends AbstractListener
{
    private TokenStorageInterface $tokenStorage;
    private string $sessionKey;
    private ?LoggerInterface $logger;
    private iterable $userProviders;
    private ?EventDispatcherInterface $dispatcher;
    private bool $registered = false;
    private AuthenticationTrustResolverInterface $trustResolver;
    private ?\Closure $sessionTrackerEnabler;

    /**
     * @param iterable<mixed, UserProviderInterface> $userProviders
     */
    public function __construct(TokenStorageInterface $tokenStorage, iterable $userProviders, string $contextKey, LoggerInterface $logger = null, EventDispatcherInterface $dispatcher = null, AuthenticationTrustResolverInterface $trustResolver = null, callable $sessionTrackerEnabler = null)
    {
        if (empty($contextKey)) {
            throw new \InvalidArgumentException('$contextKey must not be empty.');
        }

        $this->tokenStorage = $tokenStorage;
        $this->userProviders = $userProviders;
        $this->sessionKey = '_security_'.$contextKey;
        $this->logger = $logger;
        $this->dispatcher = $dispatcher;

        $this->trustResolver = $trustResolver ?? new AuthenticationTrustResolver();
        $this->sessionTrackerEnabler = null === $sessionTrackerEnabler ? null : $sessionTrackerEnabler(...);
    }

    public function supports(Request $request): ?bool
    {
        return null; // always run authenticate() lazily with lazy firewalls
    }

    /**
     * Reads the Security Token from the session.
     */
    public function authenticate(RequestEvent $event)
    {
        if (!$this->registered && null !== $this->dispatcher && $event->isMainRequest()) {
            $this->dispatcher->addListener(KernelEvents::RESPONSE, $this->onKernelResponse(...));
            $this->registered = true;
        }

        $request = $event->getRequest();
        $session = $request->hasPreviousSession() ? $request->getSession() : null;

        $request->attributes->set('_security_firewall_run', $this->sessionKey);

        if (null !== $session) {
            $usageIndexValue = $session instanceof Session ? $usageIndexReference = &$session->getUsageIndex() : 0;
            $usageIndexReference = \PHP_INT_MIN;
            $sessionId = $request->cookies->all()[$session->getName()] ?? null;
            $token = $session->get($this->sessionKey);

            // sessionId = true is used in the tests
            if ($this->sessionTrackerEnabler && \in_array($sessionId, [true, $session->getId()], true)) {
                $usageIndexReference = $usageIndexValue;
            } else {
                $usageIndexReference = $usageIndexReference - \PHP_INT_MIN + $usageIndexValue;
            }
        }

        if (null === $session || null === $token) {
            if ($this->sessionTrackerEnabler) {
                ($this->sessionTrackerEnabler)();
            }

            $this->tokenStorage->setToken(null);

            return;
        }

        $token = $this->safelyUnserialize($token);

        $this->logger?->debug('Read existing security token from the session.', [
            'key' => $this->sessionKey,
            'token_class' => \is_object($token) ? $token::class : null,
        ]);

        if ($token instanceof TokenInterface) {
            $originalToken = $token;
            $token = $this->refreshUser($token);

            if (!$token) {
                $this->logger?->debug('Token was deauthenticated after trying to refresh it.');

                $this->dispatcher?->dispatch(new TokenDeauthenticatedEvent($originalToken, $request));
            }
        } elseif (null !== $token) {
            $this->logger?->warning('Expected a security token from the session, got something else.', ['key' => $this->sessionKey, 'received' => $token]);

            $token = null;
        }

        if ($this->sessionTrackerEnabler) {
            ($this->sessionTrackerEnabler)();
        }

        $this->tokenStorage->setToken($token);
    }

    /**
     * Writes the security token into the session.
     */
    public function onKernelResponse(ResponseEvent $event)
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!$request->hasSession() || $request->attributes->get('_security_firewall_run') !== $this->sessionKey) {
            return;
        }

        $this->dispatcher?->removeListener(KernelEvents::RESPONSE, $this->onKernelResponse(...));
        $this->registered = false;
        $session = $request->getSession();
        $sessionId = $session->getId();
        $usageIndexValue = $session instanceof Session ? $usageIndexReference = &$session->getUsageIndex() : null;
        $token = $this->tokenStorage->getToken();

        if (!$this->trustResolver->isAuthenticated($token)) {
            if ($request->hasPreviousSession()) {
                $session->remove($this->sessionKey);
            }
        } else {
            $session->set($this->sessionKey, serialize($token));

            $this->logger?->debug('Stored the security token in the session.', ['key' => $this->sessionKey]);
        }

        if ($this->sessionTrackerEnabler && $session->getId() === $sessionId) {
            $usageIndexReference = $usageIndexValue;
        }
    }

    /**
     * Refreshes the user by reloading it from the user provider.
     *
     * @throws \RuntimeException
     */
    protected function refreshUser(TokenInterface $token): ?TokenInterface
    {
        $user = $token->getUser();

        $userNotFoundByProvider = false;
        $userDeauthenticated = false;
        $userClass = $user::class;

        foreach ($this->userProviders as $provider) {
            if (!$provider instanceof UserProviderInterface) {
                throw new \InvalidArgumentException(sprintf('User provider "%s" must implement "%s".', get_debug_type($provider), UserProviderInterface::class));
            }

            if (!$provider->supportsClass($userClass)) {
                continue;
            }

            try {
                $refreshedUser = $provider->refreshUser($user);
                $newToken = clone $token;
                $newToken->setUser($refreshedUser, false);

                // tokens can be deauthenticated if the user has been changed.
                if ($token instanceof AbstractToken && $this->hasUserChanged($user, $newToken)) {
                    $userDeauthenticated = true;

                    $this->logger?->debug('Cannot refresh token because user has changed.', ['username' => $refreshedUser->getUserIdentifier(), 'provider' => $provider::class]);

                    continue;
                }

                $token->setUser($refreshedUser);

                if (null !== $this->logger) {
                    $context = ['provider' => $provider::class, 'username' => $refreshedUser->getUserIdentifier()];

                    if ($token instanceof SwitchUserToken) {
                        $originalToken = $token->getOriginalToken();
                        $context['impersonator_username'] = $originalToken->getUserIdentifier();
                    }

                    $this->logger->debug('User was reloaded from a user provider.', $context);
                }

                return $token;
            } catch (UnsupportedUserException) {
                // let's try the next user provider
            } catch (UserNotFoundException $e) {
                $this->logger?->warning('Username could not be found in the selected user provider.', ['username' => $e->getUserIdentifier(), 'provider' => $provider::class]);

                $userNotFoundByProvider = true;
            }
        }

        if ($userDeauthenticated) {
            return null;
        }

        if ($userNotFoundByProvider) {
            return null;
        }

        throw new \RuntimeException(sprintf('There is no user provider for user "%s". Shouldn\'t the "supportsClass()" method of your user provider return true for this classname?', $userClass));
    }

    private function safelyUnserialize(string $serializedToken)
    {
        $token = null;
        $prevUnserializeHandler = ini_set('unserialize_callback_func', __CLASS__.'::handleUnserializeCallback');
        $prevErrorHandler = set_error_handler(function ($type, $msg, $file, $line, $context = []) use (&$prevErrorHandler) {
            if (__FILE__ === $file) {
                throw new \ErrorException($msg, 0x37313BC, $type, $file, $line);
            }

            return $prevErrorHandler ? $prevErrorHandler($type, $msg, $file, $line, $context) : false;
        });

        try {
            $token = unserialize($serializedToken);
        } catch (\ErrorException $e) {
            if (0x37313BC !== $e->getCode()) {
                throw $e;
            }
            $this->logger?->warning('Failed to unserialize the security token from the session.', ['key' => $this->sessionKey, 'received' => $serializedToken, 'exception' => $e]);
        } finally {
            restore_error_handler();
            ini_set('unserialize_callback_func', $prevUnserializeHandler);
        }

        return $token;
    }

    private static function hasUserChanged(UserInterface $originalUser, TokenInterface $refreshedToken): bool
    {
        $refreshedUser = $refreshedToken->getUser();

        if ($originalUser instanceof EquatableInterface) {
            return !(bool) $originalUser->isEqualTo($refreshedUser);
        }

        if ($originalUser instanceof PasswordAuthenticatedUserInterface || $refreshedUser instanceof PasswordAuthenticatedUserInterface) {
            if (!$originalUser instanceof PasswordAuthenticatedUserInterface || !$refreshedUser instanceof PasswordAuthenticatedUserInterface || $originalUser->getPassword() !== $refreshedUser->getPassword()) {
                return true;
            }

            if ($originalUser instanceof LegacyPasswordAuthenticatedUserInterface xor $refreshedUser instanceof LegacyPasswordAuthenticatedUserInterface) {
                return true;
            }

            if ($originalUser instanceof LegacyPasswordAuthenticatedUserInterface && $refreshedUser instanceof LegacyPasswordAuthenticatedUserInterface && $originalUser->getSalt() !== $refreshedUser->getSalt()) {
                return true;
            }
        }

        $userRoles = array_map('strval', (array) $refreshedUser->getRoles());

        if ($refreshedToken instanceof SwitchUserToken) {
            $userRoles[] = 'ROLE_PREVIOUS_ADMIN';
        }

        if (
            \count($userRoles) !== \count($refreshedToken->getRoleNames()) ||
            \count($userRoles) !== \count(array_intersect($userRoles, $refreshedToken->getRoleNames()))
        ) {
            return true;
        }

        if ($originalUser->getUserIdentifier() !== $refreshedUser->getUserIdentifier()) {
            return true;
        }

        return false;
    }

    /**
     * @internal
     */
    public static function handleUnserializeCallback(string $class)
    {
        throw new \ErrorException('Class not found: '.$class, 0x37313BC);
    }
}
