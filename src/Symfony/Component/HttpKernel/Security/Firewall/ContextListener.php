<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Security\Firewall;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\Security\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Exception\UnsupportedAccountException;
use Symfony\Component\Security\SecurityContext;
use Symfony\Component\Security\User\AccountInterface;

/**
 * ContextListener manages the SecurityContext persistence through a session.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ContextListener implements ListenerInterface
{
    protected $context;
    protected $logger;
    protected $userProviders;

    public function __construct(SecurityContext $context, array $userProviders, LoggerInterface $logger = null)
    {
        $this->context = $context;
        $this->userProviders = $userProviders;
        $this->logger = $logger;
    }

    /**
     * Registers a core.security listener to load the SecurityContext from the
     * session.
     *
     * @param EventDispatcher $dispatcher An EventDispatcher instance
     * @param integer         $priority   The priority
     */
    public function register(EventDispatcher $dispatcher)
    {
        $dispatcher->connect('core.security', array($this, 'read'), 0);
        $dispatcher->connect('core.response', array($this, 'write'), 0);
    }

    /**
     * {@inheritDoc}
     */
    public function unregister(EventDispatcher $dispatcher)
    {
        $dispatcher->disconnect('core.response', array($this, 'write'));
    }

    /**
     * Reads the SecurityContext from the session.
     *
     * @param Event $event An Event instance
     */
    public function read(Event $event)
    {
        $request = $event->get('request');

        $session = $request->hasSession() ? $request->getSession() : null;

        if (null === $session || null === $token = $session->get('_security')) {
            $this->context->setToken(null);
        } else {
            if (null !== $this->logger) {
                $this->logger->debug('Read SecurityContext from the session');
            }

            $token = unserialize($token);

            if (null !== $token && false === $token->isImmutable()) {
                $token = $this->refreshUser($token);
            }

            $this->context->setToken($token);
        }
    }

    /**
     * Writes the SecurityContext to the session.
     *
     * @param Event $event An Event instance
     */
    public function write(Event $event, Response $response)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->get('request_type')) {
            return $response;
        }

        if (null === $token = $this->context->getToken()) {
            return $response;
        }

        if (null === $token || $token instanceof AnonymousToken) {
            return $response;
        }

        if (null !== $this->logger) {
            $this->logger->debug('Write SecurityContext in the session');
        }

        $event->get('request')->getSession()->set('_security', serialize($token));

        return $response;
    }

    /**
     * Refreshes the user by reloading it from the user provider
     *
     * @param TokenInterface $token
     *
     * @return TokenInterface|null
     */
    protected function refreshUser(TokenInterface $token)
    {
        $user = $token->getUser();
        if (!$user instanceof AccountInterface) {
            return $token;
        }

        if (null !== $this->logger) {
            $this->logger->debug(sprintf('Reloading user from user provider.'));
        }

        foreach ($this->userProviders as $provider) {
            try {
                $cUser = $provider->loadUserByAccount($user);

                $token->setRoles($cUser->getRoles());
                $token->setUser($cUser);

                if (false === $cUser->equals($user)) {
                    $token->setAuthenticated(false);
                }

                return $token;
            } catch (UnsupportedAccountException $unsupported) {
                // let's try the next user provider
            } catch (UsernameNotFoundException $notFound) {
                return null;
            }
        }

        throw new \RuntimeException(sprintf('There is no user provider for user "%s".', get_class($user)));
    }
}
