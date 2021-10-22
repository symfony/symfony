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
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
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
     * @param iterable|UserProviderInterface[] $userProviders
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

        if (null === $token || $this->trustResolver->isAnonymous($token)) {
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
                $newToken->setUser($refreshedUser);

                // tokens can be deauthenticated if the user has been changed.
                if (!$newToken->isAuthenticated()) {
                    $userDeauthenticated = true;

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
            if (null !== $this->logger) {
                $this->logger->debug('Token was deauthenticated after trying to refresh it.');
            }

            if ($this->dispatcher) {
                $this->dispatcher->dispatch(new DeauthenticatedEvent($token, $newToken), DeauthenticatedEvent::class);
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
        $e = $token = null;
        $prevUnserializeHandler = ini_set('unserialize_callback_func', __CLASS__.'::handleUnserializeCallback');
        $prevErrorHandler = set_error_handler(function ($type, $msg, $file, $line, $context = []) use (&$prevErrorHandler) {
            if (__FILE__ === $file) {
                throw new \ErrorException($msg, 0x37313bc, $type, $file, $line);
            }

            return $prevErrorHandler ? $prevErrorHandler($type, $msg, $file, $line, $context) : false;
        });

        try {
            $token = unserialize($serializedToken);
        } catch (\Throwable $e) {
        }
        restore_error_handler();
        ini_set('unserialize_callback_func', $prevUnserializeHandler);
        if ($e) {
            if (!$e instanceof \ErrorException || 0x37313bc !== $e->getCode()) {
                throw $e;
            }
            if ($this->logger) {
                $this->logger->warning('Failed to unserialize the security token from the session.', ['key' => $this->sessionKey, 'received' => $serializedToken, 'exception' => $e]);
            }
        }

        return $token;
    }

    /**
     * @internal
     */
    public static function handleUnserializeCallback(string $class)
    {
        throw new \ErrorException('Class not found: '.$class, 0x37313bc);
    }

    public function setRememberMeServices(RememberMeServicesInterface $rememberMeServices)
    {
        $this->rememberMeServices = $rememberMeServices;
    }
}
