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

use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Authentication\SimpleTokenAuthenticatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * SimpleTokenListener implements simple proxying to an authenticator.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class SimpleTokenAuthenticationListener implements ListenerInterface
{
    private $securityContext;
    private $authenticationManager;
    private $providerKey;
    private $simpleAuthenticator;
    private $logger;

    /**
     * Constructor.
     *
     * @param SecurityContextInterface         $securityContext       A SecurityContext instance
     * @param AuthenticationManagerInterface   $authenticationManager An AuthenticationManagerInterface instance
     * @param string                           $providerKey
     * @param SimpleTokenAuthenticatorInterface $simpleAuthenticator   A SimpleTokenAuthenticatorInterface instance
     * @param LoggerInterface                  $logger                A LoggerInterface instance
     */
    public function __construct(SecurityContextInterface $securityContext, AuthenticationManagerInterface $authenticationManager, $providerKey, SimpleTokenAuthenticatorInterface $simpleAuthenticator, LoggerInterface $logger = null)
    {
        if (empty($providerKey)) {
            throw new \InvalidArgumentException('$providerKey must not be empty.');
        }

        $this->securityContext = $securityContext;
        $this->authenticationManager = $authenticationManager;
        $this->providerKey = $providerKey;
        $this->simpleAuthenticator = $simpleAuthenticator;
        $this->logger = $logger;
    }

    /**
     * Handles basic authentication.
     *
     * @param GetResponseEvent $event A GetResponseEvent instance
     */
    public function handle(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        if (null !== $this->logger) {
            $this->logger->info(sprintf('Attempting simple token authorization %s', $this->providerKey));
        }


        try {
            $token = $this->simpleAuthenticator->createToken($request, $this->providerKey);
            $token = $this->authenticationManager->authenticate($token);
            $this->securityContext->setToken($token);

        } catch (AuthenticationException $failed) {
            $this->securityContext->setToken(null);

            if (null !== $this->logger) {
                $this->logger->info(sprintf('Authentication request failed: %s', $failed->getMessage()));
            }

            // TODO call failure handler
            return;
        }

        // TODO call success handler
    }
}
