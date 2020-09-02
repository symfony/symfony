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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Role\SwitchUserRole;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface;

/**
 * ContextListener manages the SecurityContext persistence through a session.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ContextListener implements ListenerInterface
{
    private $tokenStorage;
    private $sessionKey;
    private $logger;
    private $userProviders;
    private $dispatcher;
    private $registered;
    private $trustResolver;
    private $logoutOnUserChange = false;
    private $rememberMeServices;

    /**
     * @param iterable|UserProviderInterface[] $userProviders
     * @param string                           $contextKey
     */
    public function __construct(TokenStorageInterface $tokenStorage, $userProviders, $contextKey, LoggerInterface $logger = null, EventDispatcherInterface $dispatcher = null, AuthenticationTrustResolverInterface $trustResolver = null)
    {
        if (empty($contextKey)) {
            throw new \InvalidArgumentException('$contextKey must not be empty.');
        }

        $this->tokenStorage = $tokenStorage;
        $this->userProviders = $userProviders;
        $this->sessionKey = '_security_'.$contextKey;
        $this->logger = $logger;
        $this->dispatcher = $dispatcher;
        $this->trustResolver = $trustResolver ?: new AuthenticationTrustResolver(AnonymousToken::class, RememberMeToken::class);
    }

    /**
     * Enables deauthentication during refreshUser when the user has changed.
     *
     * @param bool $logoutOnUserChange
     */
    public function setLogoutOnUserChange($logoutOnUserChange)
    {
        $this->logoutOnUserChange = (bool) $logoutOnUserChange;
    }

    /**
     * Reads the Security Token from the session.
     */
    public function handle(GetResponseEvent $event)
    {
        if (!$this->registered && null !== $this->dispatcher && $event->isMasterRequest()) {
            $this->dispatcher->addListener(KernelEvents::RESPONSE, [$this, 'onKernelResponse']);
            $this->registered = true;
        }

        $request = $event->getRequest();
        $session = $request->hasPreviousSession() ? $request->getSession() : null;

        if (null === $session || null === $token = $session->get($this->sessionKey)) {
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
            $token = $this->refreshUser($token);

            if (!$token && $this->logoutOnUserChange && $this->rememberMeServices) {
                $this->rememberMeServices->loginFail($request);
            }
        } elseif (null !== $token) {
            if (null !== $this->logger) {
                $this->logger->warning('Expected a security token from the session, got something else.', ['key' => $this->sessionKey, 'received' => $token]);
            }

            $token = null;
        }

        $this->tokenStorage->setToken($token);
    }

    /**
     * Writes the security token into the session.
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!$request->hasSession()) {
            return;
        }

        $this->dispatcher->removeListener(KernelEvents::RESPONSE, [$this, 'onKernelResponse']);
        $this->registered = false;
        $session = $request->getSession();

        if ((null === $token = $this->tokenStorage->getToken()) || $this->trustResolver->isAnonymous($token)) {
            if ($request->hasPreviousSession()) {
                $session->remove($this->sessionKey);
            }
        } else {
            $session->set($this->sessionKey, serialize($token));

            if (null !== $this->logger) {
                $this->logger->debug('Stored the security token in the session.', ['key' => $this->sessionKey]);
            }
        }
    }

    /**
     * Refreshes the user by reloading it from the user provider.
     *
     * @return TokenInterface|null
     *
     * @throws \RuntimeException
     */
    protected function refreshUser(TokenInterface $token)
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
                throw new \InvalidArgumentException(sprintf('User provider "%s" must implement "%s".', \get_class($provider), UserProviderInterface::class));
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
                    if ($this->logoutOnUserChange) {
                        $userDeauthenticated = true;

                        if (null !== $this->logger) {
                            $this->logger->debug('Cannot refresh token because user has changed.', ['username' => $refreshedUser->getUsername(), 'provider' => \get_class($provider)]);
                        }

                        continue;
                    }

                    @trigger_error('Refreshing a deauthenticated user is deprecated as of 3.4 and will trigger a logout in 4.0.', \E_USER_DEPRECATED);
                }

                $token->setUser($refreshedUser);

                if (null !== $this->logger) {
                    $context = ['provider' => \get_class($provider), 'username' => $refreshedUser->getUsername()];

                    foreach ($token->getRoles() as $role) {
                        if ($role instanceof SwitchUserRole) {
                            $context['impersonator_username'] = $role->getSource()->getUsername();
                            break;
                        }
                    }

                    $this->logger->debug('User was reloaded from a user provider.', $context);
                }

                return $token;
            } catch (UnsupportedUserException $e) {
                // let's try the next user provider
            } catch (UsernameNotFoundException $e) {
                if (null !== $this->logger) {
                    $this->logger->warning('Username could not be found in the selected user provider.', ['username' => $e->getUsername(), 'provider' => \get_class($provider)]);
                }

                $userNotFoundByProvider = true;
            }
        }

        if ($userDeauthenticated) {
            if (null !== $this->logger) {
                $this->logger->debug('Token was deauthenticated after trying to refresh it.');
            }

            return null;
        }

        if ($userNotFoundByProvider) {
            return null;
        }

        throw new \RuntimeException(sprintf('There is no user provider for user "%s". Shouldn\'t the "supportsClass()" method of your user provider return true for this classname?', $userClass));
    }

    private function safelyUnserialize($serializedToken)
    {
        $e = $token = null;
        $prevUnserializeHandler = ini_set('unserialize_callback_func', __CLASS__.'::handleUnserializeCallback');
        $prevErrorHandler = set_error_handler(function ($type, $msg, $file, $line, $context = []) use (&$prevErrorHandler) {
            if (__FILE__ === $file) {
                throw new \UnexpectedValueException($msg, 0x37313bc);
            }

            return $prevErrorHandler ? $prevErrorHandler($type, $msg, $file, $line, $context) : false;
        });

        try {
            $token = unserialize($serializedToken);
        } catch (\Error $e) {
        } catch (\Exception $e) {
        }
        restore_error_handler();
        ini_set('unserialize_callback_func', $prevUnserializeHandler);
        if ($e) {
            if (!$e instanceof \UnexpectedValueException || 0x37313bc !== $e->getCode()) {
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
    public static function handleUnserializeCallback($class)
    {
        throw new \UnexpectedValueException('Class not found: '.$class, 0x37313bc);
    }

    public function setRememberMeServices(RememberMeServicesInterface $rememberMeServices)
    {
        $this->rememberMeServices = $rememberMeServices;
    }
}
