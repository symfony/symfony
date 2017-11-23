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
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Role\SwitchUserRole;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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

    /**
     * @param TokenStorageInterface                     $tokenStorage
     * @param iterable|UserProviderInterface[]          $userProviders
     */
    public function __construct(TokenStorageInterface $tokenStorage, iterable $userProviders, string $contextKey, LoggerInterface $logger = null, EventDispatcherInterface $dispatcher = null, AuthenticationTrustResolverInterface $trustResolver = null)
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
        // no-op, method to be deprecated in 4.1
    }

    /**
     * Reads the Security Token from the session.
     */
    public function handle(GetResponseEvent $event)
    {
        if (!$this->registered && null !== $this->dispatcher && $event->isMasterRequest()) {
            $this->dispatcher->addListener(KernelEvents::RESPONSE, array($this, 'onKernelResponse'));
            $this->registered = true;
        }

        $request = $event->getRequest();
        $session = $request->hasPreviousSession() ? $request->getSession() : null;

        if (null === $session || null === $token = $session->get($this->sessionKey)) {
            $this->tokenStorage->setToken(null);

            return;
        }

        $token = unserialize($token);

        if (null !== $this->logger) {
            $this->logger->debug('Read existing security token from the session.', array(
                'key' => $this->sessionKey,
                'token_class' => is_object($token) ? get_class($token) : null,
            ));
        }

        if ($token instanceof TokenInterface) {
            $token = $this->refreshUser($token);
        } elseif (null !== $token) {
            if (null !== $this->logger) {
                $this->logger->warning('Expected a security token from the session, got something else.', array('key' => $this->sessionKey, 'received' => $token));
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

        $this->dispatcher->removeListener(KernelEvents::RESPONSE, array($this, 'onKernelResponse'));
        $this->registered = false;
        $session = $request->getSession();

        if ((null === $token = $this->tokenStorage->getToken()) || $this->trustResolver->isAnonymous($token)) {
            if ($request->hasPreviousSession()) {
                $session->remove($this->sessionKey);
            }
        } else {
            $session->set($this->sessionKey, serialize($token));

            if (null !== $this->logger) {
                $this->logger->debug('Stored the security token in the session.', array('key' => $this->sessionKey));
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

        foreach ($this->userProviders as $provider) {
            if (!$provider instanceof UserProviderInterface) {
                throw new \InvalidArgumentException(sprintf('User provider "%s" must implement "%s".', get_class($provider), UserProviderInterface::class));
            }

            try {
                $refreshedUser = $provider->refreshUser($user);
                $token->setUser($refreshedUser);

                // tokens can be deauthenticated if the user has been changed.
                if (!$token->isAuthenticated()) {
                    if (null !== $this->logger) {
                        $this->logger->debug('Token was deauthenticated after trying to refresh it.', array('username' => $refreshedUser->getUsername(), 'provider' => get_class($provider)));
                    }

                    return null;
                }

                if (null !== $this->logger) {
                    $context = array('provider' => get_class($provider), 'username' => $refreshedUser->getUsername());

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
                    $this->logger->warning('Username could not be found in the selected user provider.', array('username' => $e->getUsername(), 'provider' => get_class($provider)));
                }

                $userNotFoundByProvider = true;
            }
        }

        if ($userNotFoundByProvider) {
            return null;
        }

        throw new \RuntimeException(sprintf('There is no user provider for user "%s".', get_class($user)));
    }
}
