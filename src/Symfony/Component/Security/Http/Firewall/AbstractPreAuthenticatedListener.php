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

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventInterface;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\HttpFoundation\Request;

/**
 * AbstractPreAuthenticatedListener is the base class for all listener that
 * authenticates users based on a pre-authenticated request (like a certificate
 * for instance).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class AbstractPreAuthenticatedListener implements ListenerInterface
{
    protected $logger;
    private $securityContext;
    private $authenticationManager;
    private $providerKey;
    private $eventDispatcher;

    public function __construct(SecurityContextInterface $securityContext, AuthenticationManagerInterface $authenticationManager, $providerKey, LoggerInterface $logger = null, EventDispatcherInterface $eventDispatcher = null)
    {
        $this->securityContext = $securityContext;
        $this->authenticationManager = $authenticationManager;
        $this->providerKey = $providerKey;
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Handles X509 authentication.
     *
     * @param EventInterface $event An EventInterface instance
     */
    public function handle(EventInterface $event)
    {
        $request = $event->get('request');

        if (null !== $this->logger) {
            $this->logger->debug(sprintf('Checking secure context token: %s', $this->securityContext->getToken()->getUserName()));
        }

        list($user, $credentials) = $this->getPreAuthenticatedData($request);

        if (null !== $token = $this->securityContext->getToken()) {
            if ($token instanceof PreAuthenticatedToken && $token->isAuthenticated() && $token->getUsername() === $user) {
                return;
            }
        }

        if (null !== $this->logger) {
            $this->logger->debug(sprintf('Trying to pre-authenticate user "%s"', $user));
        }

        try {
            $token = $this->authenticationManager->authenticate(new PreAuthenticatedToken($user, $credentials, $this->providerKey));

            if (null !== $this->logger) {
                $this->logger->debug(sprintf('Authentication success: %s', $token->getUserName()));
            }
            $this->securityContext->setToken($token);

            if (null !== $this->eventDispatcher) {
                $this->eventDispatcher->notify(new Event($this, 'security.interactive_login', array('request' => $request, 'token' => $token)));
            }
        } catch (AuthenticationException $failed) {
            $this->securityContext->setToken(null);

            if (null !== $this->logger) {
                $this->logger->debug(sprintf("Cleared security context due to exception: %s", $failed->getMessage()));
            }
        }
    }

    /**
     * Gets the user and credentials from the Request.
     *
     * @param Request $request A Request instance
     *
     * @return array An array composed of the user and the credentials
     */
    abstract protected function getPreAuthenticatedData(Request $request);
}
