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
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
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
    private $contextKey;
    private $sessionKey;
    private $logger;
    private $userProviders;
    private $dispatcher;
    private $registered;

    public function __construct(TokenStorageInterface $tokenStorage, array $userProviders, $contextKey, LoggerInterface $logger = null, EventDispatcherInterface $dispatcher = null)
    {
        if (empty($contextKey)) {
            throw new \InvalidArgumentException('$contextKey must not be empty.');
        }

        foreach ($userProviders as $userProvider) {
            if (!$userProvider instanceof UserProviderInterface) {
                throw new \InvalidArgumentException(sprintf('User provider "%s" must implement "Symfony\Component\Security\Core\User\UserProviderInterface".', get_class($userProvider)));
            }
        }

        $this->tokenStorage = $tokenStorage;
        $this->userProviders = $userProviders;
        $this->contextKey = $contextKey;
        $this->sessionKey = '_security_'.$contextKey;
        $this->logger = $logger;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Reads the Security Token from the session.
     *
     * @param GetResponseEvent $event A GetResponseEvent instance
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
            $this->logger->debug('Read existing security token from the session', array('key' => $this->sessionKey));
        }

        if ($token instanceof TokenInterface) {
            $token = $this->refreshUser($token);
        } elseif (null !== $token) {
            if (null !== $this->logger) {
                $this->logger->warning('Expected a security token from the session, got something else', array('key' => $this->sessionKey, 'received' => $token));
            }

            $token = null;
        }

        $this->tokenStorage->setToken($token);
    }

    /**
     * Writes the SecurityContext to the session.
     *
     * @param FilterResponseEvent $event A FilterResponseEvent instance
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        if (!$event->getRequest()->hasSession()) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();

        if (null === $session) {
            return;
        }

        if ((null === $token = $this->tokenStorage->getToken()) || ($token instanceof AnonymousToken)) {
            if ($request->hasPreviousSession()) {
                $session->remove($this->sessionKey);
            }
        } else {
            $session->set($this->sessionKey, serialize($token));

            if (null !== $this->logger) {
                $this->logger->debug('Stored the security token in the session', array('key' => $this->sessionKey));
            }
        }
    }

    /**
     * Refreshes the user by reloading it from the user provider.
     *
     * @param TokenInterface $token
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

        foreach ($this->userProviders as $provider) {
            try {
                $refreshedUser = $provider->refreshUser($user);
                $token->setUser($refreshedUser);

                if (null !== $this->logger) {
                    $this->logger->debug('User was reloaded from a user provider.', array('username' => $refreshedUser->getUsername(), 'provider' => get_class($provider)));
                }

                return $token;
            } catch (UnsupportedUserException $unsupported) {
                // let's try the next user provider
            } catch (UsernameNotFoundException $notFound) {
                if (null !== $this->logger) {
                    $this->logger->warning('Username could not be found in the selected user provider.', array('username' => $notFound->getUsername(), 'provider' => get_class($provider)));
                }

                return;
            }
        }

        throw new \RuntimeException(sprintf('There is no user provider for user "%s".', get_class($user)));
    }
}
