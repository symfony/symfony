<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Firewall;

use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Event\InteractiveLoginEventArgs;
use Symfony\Component\Security\Http\Events;
use Symfony\Component\HttpKernel\Event\RequestEventArgs;
use Symfony\Component\HttpKernel\Events as KernelEvents;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\Common\EventManager;

/**
 * AbstractPreAuthenticatedListener is the base class for all listener that
 * authenticates users based on a pre-authenticated request (like a certificate
 * for instance).
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class AbstractPreAuthenticatedListener implements ListenerInterface
{
    protected $securityContext;
    protected $authenticationManager;
    protected $providerKey;
    protected $logger;
    protected $evm;

    public function __construct(SecurityContextInterface $securityContext, AuthenticationManagerInterface $authenticationManager, $providerKey, LoggerInterface $logger = null)
    {
        $this->securityContext = $securityContext;
        $this->authenticationManager = $authenticationManager;
        $this->providerKey = $providerKey;
        $this->logger = $logger;
    }

    /**
     *
     *
     * @param EventManager $evm An EventManager instance
     */
    public function register(EventManager $evm)
    {
        $evm->addEventListener(KernelEvents::onCoreSecurity, $this);

        $this->evm = $evm;
    }

    /**
     * {@inheritDoc}
     */
    public function unregister(EventManager $evm)
    {
    }

    /**
     * Handles X509 authentication.
     *
     * @param EventInterface $event An EventInterface instance
     */
    public function onCoreSecurity(RequestEventArgs $eventArgs)
    {
        $request = $eventArgs->getRequest();

        if (null !== $this->logger) {
            $this->logger->debug(sprintf('Checking secure context token: %s', $this->securityContext->getToken()));
        }

        list($user, $credentials) = $this->getPreAuthenticatedData($request);

        if (null !== $token = $this->securityContext->getToken()) {
            if ($token->isImmutable()) {
                return;
            }

            if ($token instanceof PreAuthenticatedToken && $token->isAuthenticated() && (string) $token === $user) {
                return;
            }
        }

        if (null !== $this->logger) {
            $this->logger->debug(sprintf('Trying to pre-authenticate user "%s"', $user));
        }

        try {
            $token = $this->authenticationManager->authenticate(new PreAuthenticatedToken($user, $credentials, $this->providerKey));

            if (null !== $this->logger) {
                $this->logger->debug(sprintf('Authentication success: %s', $token));
            }
            $this->securityContext->setToken($token);

            if (null !== $this->evm) {
                $loginEventArgs = new InteractiveLoginEventArgs($request, $token);
                $this->evm->notify(Events::onSecurityInteractiveLogin, $loginEventArgs);
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
