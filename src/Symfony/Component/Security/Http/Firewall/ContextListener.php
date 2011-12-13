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

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\SecurityContext;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * ContextListener manages the SecurityContext persistence through a session.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ContextListener implements ListenerInterface
{
    private $context;
    private $contextKey;
    private $logger;
    private $userProviders;

    public function __construct(SecurityContext $context, array $userProviders, $contextKey, LoggerInterface $logger = null, EventDispatcherInterface $dispatcher = null)
    {
        if (empty($contextKey)) {
            throw new \InvalidArgumentException('$contextKey must not be empty.');
        }

        $this->context = $context;
        $this->userProviders = $userProviders;
        $this->contextKey = $contextKey;
        $this->logger = $logger;

        if (null !== $dispatcher) {
            $dispatcher->addListener(KernelEvents::RESPONSE, array($this, 'onKernelResponse'));
        }
    }

    /**
     * Reads the SecurityContext from the session.
     *
     * @param GetResponseEvent $event A GetResponseEvent instance
     */
    public function handle(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        $session = $request->hasPreviousSession() ? $request->getSession() : null;

        if (null === $session || null === $token = $session->get('_security_'.$this->contextKey)) {
            $this->context->setToken(null);
        } else {
            if (null !== $this->logger) {
                $this->logger->debug('Read SecurityContext from the session');
            }

            $token = unserialize($token);

            if (null !== $token) {
                $token = $this->refreshUser($token);
            }

            $this->context->setToken($token);
        }
    }

    /**
     * Writes the SecurityContext to the session.
     *
     * @param FilterResponseEvent $event A FilterResponseEvent instance
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->getRequestType()) {
            return;
        }

        if (!$event->getRequest()->hasSession()) {
            return;
        }

        if (null === $token = $this->context->getToken()) {
            return;
        }

        if (null === $token || $token instanceof AnonymousToken) {
            return;
        }

        if (null !== $this->logger) {
            $this->logger->debug('Write SecurityContext in the session');
        }

        $event->getRequest()->getSession()->set('_security_'.$this->contextKey, serialize($token));
    }

    /**
     * Refreshes the user by reloading it from the user provider
     *
     * @param TokenInterface $token
     *
     * @return TokenInterface|null
     */
    private function refreshUser(TokenInterface $token)
    {
        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            return $token;
        }

        if (null !== $this->logger) {
            $this->logger->debug(sprintf('Reloading user from user provider.'));
        }

        foreach ($this->userProviders as $provider) {
            try {
                $token->setUser($provider->refreshUser($user));

                if (null !== $this->logger) {
                    $this->logger->debug(sprintf('Username "%s" was reloaded from user provider.', $user->getUsername()));
                }

                return $token;
            } catch (UnsupportedUserException $unsupported) {
                // let's try the next user provider
            } catch (UsernameNotFoundException $notFound) {
                if (null !== $this->logger) {
                    $this->logger->warn(sprintf('Username "%s" could not be found.', $user->getUsername()));
                }

                return null;
            }
        }

        throw new \RuntimeException(sprintf('There is no user provider for user "%s".', get_class($user)));
    }
}
