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
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\LegacyEventDispatcherProxy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Event\DeauthenticatedEvent;
use Symfony\Component\Security\Http\Event\TokenDeauthenticatedEvent;
use Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface;
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
    private $tokenStorage;
    private $sessionKey;
    private $logger;
    private $userProviders;
    private $dispatcher;
    private $registered;
    private $trustResolver;
    private $rememberMeServices;
    private $sessionTrackerEnabler;

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
        $this->dispatcher = class_exists(Event::class) ? LegacyEventDispatcherProxy::decorate($dispatcher) : $dispatcher;

        $this->trustResolver = $trustResolver ?? new AuthenticationTrustResolver(AnonymousToken::class, RememberMeToken::class);
        $this->sessionTrackerEnabler = $sessionTrackerEnabler;
    }

    /**
     * {@inheritdoc}
     */
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
            $this->dispatcher->addListener(KernelEvents::RESPONSE, [$this, 'onKernelResponse']);
            $this->registered = true;
        }

        $request = $event->getRequest();
        $session = $request->hasPreviousSession() && $request->hasSession() ? $request->getSession() : null;

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

        if (null !== $this->logger) {
            $this->logger->debug('Read existing security token from the session.', [
                'key' => $this->sessionKey,
                'token_class' => \is_object($token) ? \get_class($token) : null,
            ]);
        }

        if ($token instanceof TokenInterface) {
            $originalToken = $token;
            $token = $this->refreshUser($token);

            if (!$token) {
                if ($this->logger) {
                    $this->logger->debug('Token was deauthenticated after trying to refresh it.');
                }

                if ($this->dispatcher) {
                    $this->dispatcher->dispatch(new TokenDeauthenticatedEvent($originalToken, $request));
                }

                if ($this->rememberMeServices) {
                    $this->rememberMeServices->loginFail($request);
                }
            }
        } elseif (null !== $token) {
            if (null !== $this->logger) {
                $this->logger->warning('Expected a security token from the session, got something else.', ['key' => $this->sessionKey, 'received' => $token]);
            }

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

        if ($this->dispatcher) {
            $this->dispatcher->removeListener(KernelEvents::RESPONSE, [$this, 'onKernelResponse']);
        }
        $this->registered = false;
        $session = $request->getSession();
        $sessionId = $session->getId();
        $usageIndexValue = $session instanceof Session ? $usageIndexReference = &$session->getUsageIndex() : null;
        $token = $this->tokenStorage->getToken();

        // @deprecated always use isAuthenticated() in 6.0
        $notAuthenticated = method_exists($this->trustResolver, 'isAuthenticated') ? !$this->trustResolver->isAuthenticated($token) : (null === $token || $this->trustResolver->isAnonymous($token));
        if ($notAuthenticated) {
            if ($request->hasPreviousSession()) {
                $session->remove($this->sessionKey);
            }
        } else {
            $session->set($this->sessionKey, serialize($token));

            if (null !== $this->logger) {
                $this->logger->debug('Stored the security token in the session.', ['key' => $this->sessionKey]);
            }
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
        if (!$user instanceof UserInterface) {
            return $token;
        }

        $userNotFoundByProvider = false;
        $userDeauthenticated = false;
        $userClass = \get_class($user);

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
                    // @deprecated since Symfony 5.4
                    if (method_exists($newToken, 'setAuthenticated')) {
                        $newToken->setAuthenticated(false, false);
                    }

                    if (null !== $this->logger) {
                        // @deprecated since Symfony 5.3, change to $refreshedUser->getUserIdentifier() in 6.0
                        $this->logger->debug('Cannot refresh token because user has changed.', ['username' => method_exists($refreshedUser, 'getUserIdentifier') ? $refreshedUser->getUserIdentifier() : $refreshedUser->getUsername(), 'provider' => \get_class($provider)]);
                    }

                    continue;
                }

                $token->setUser($refreshedUser);

                if (null !== $this->logger) {
                    // @deprecated since Symfony 5.3, change to $refreshedUser->getUserIdentifier() in 6.0
                    $context = ['provider' => \get_class($provider), 'username' => method_exists($refreshedUser, 'getUserIdentifier') ? $refreshedUser->getUserIdentifier() : $refreshedUser->getUsername()];

                    if ($token instanceof SwitchUserToken) {
                        $originalToken = $token->getOriginalToken();
                        // @deprecated since Symfony 5.3, change to $originalToken->getUserIdentifier() in 6.0
                        $context['impersonator_username'] = method_exists($originalToken, 'getUserIdentifier') ? $originalToken->getUserIdentifier() : $originalToken->getUsername();
                    }

                    $this->logger->debug('User was reloaded from a user provider.', $context);
                }

                return $token;
            } catch (UnsupportedUserException $e) {
                // let's try the next user provider
            } catch (UserNotFoundException $e) {
                if (null !== $this->logger) {
                    $this->logger->warning('Username could not be found in the selected user provider.', ['username' => method_exists($e, 'getUserIdentifier') ? $e->getUserIdentifier() : $e->getUsername(), 'provider' => \get_class($provider)]);
                }

                $userNotFoundByProvider = true;
            }
        }

        if ($userDeauthenticated) {
            // @deprecated since Symfony 5.4
            if ($this->dispatcher) {
                $this->dispatcher->dispatch(new DeauthenticatedEvent($token, $newToken, false), DeauthenticatedEvent::class);
            }

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
            if ($this->logger) {
                $this->logger->warning('Failed to unserialize the security token from the session.', ['key' => $this->sessionKey, 'received' => $serializedToken, 'exception' => $e]);
            }
        } finally {
            restore_error_handler();
            ini_set('unserialize_callback_func', $prevUnserializeHandler);
        }

        return $token;
    }

    /**
     * @param string|\Stringable|UserInterface $originalUser
     */
    private static function hasUserChanged($originalUser, TokenInterface $refreshedToken): bool
    {
        $refreshedUser = $refreshedToken->getUser();

        if ($originalUser instanceof UserInterface) {
            if (!$refreshedUser instanceof UserInterface) {
                return true;
            } else {
                // noop
            }
        } elseif ($refreshedUser instanceof UserInterface) {
            return true;
        } else {
            return (string) $originalUser !== (string) $refreshedUser;
        }

        if ($originalUser instanceof EquatableInterface) {
            return !(bool) $originalUser->isEqualTo($refreshedUser);
        }

        // @deprecated since Symfony 5.3, check for PasswordAuthenticatedUserInterface on both user objects before comparing passwords
        if ($originalUser->getPassword() !== $refreshedUser->getPassword()) {
            return true;
        }

        // @deprecated since Symfony 5.3, check for LegacyPasswordAuthenticatedUserInterface on both user objects before comparing salts
        if ($originalUser->getSalt() !== $refreshedUser->getSalt()) {
            return true;
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

        // @deprecated since Symfony 5.3, drop getUsername() in 6.0
        $userIdentifier = function ($refreshedUser) {
            return method_exists($refreshedUser, 'getUserIdentifier') ? $refreshedUser->getUserIdentifier() : $refreshedUser->getUsername();
        };
        if ($userIdentifier($originalUser) !== $userIdentifier($refreshedUser)) {
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

    /**
     * @deprecated since Symfony 5.4
     */
    public function setRememberMeServices(RememberMeServicesInterface $rememberMeServices)
    {
        trigger_deprecation('symfony/security-http', '5.4', 'Method "%s()" is deprecated, use the new remember me handlers instead.', __METHOD__);

        $this->rememberMeServices = $rememberMeServices;
    }
}
