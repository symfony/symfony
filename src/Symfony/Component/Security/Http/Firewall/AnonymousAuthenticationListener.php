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

use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;

/**
 * AnonymousAuthenticationListener automatically adds a Token if none is
 * already present.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AnonymousAuthenticationListener implements ListenerInterface
{
    private $context;
    private $key;
    private $authenticationManager;
    private $logger;

    public function __construct(SecurityContextInterface $context, $key, LoggerInterface $logger = null, AuthenticationManagerInterface $authenticationManager = null)
    {
        $this->context = $context;
        $this->key = $key;
        $this->authenticationManager = $authenticationManager;
        $this->logger = $logger;
    }

    /**
     * Handles anonymous authentication.
     *
     * @param GetResponseEvent $event A GetResponseEvent instance
     */
    public function handle(GetResponseEvent $event)
    {
        if (null !== $this->context->getToken()) {
            return;
        }

        try {
            $token = new AnonymousToken($this->key, 'anon.', array());
            if (null !== $this->authenticationManager) {
                $token = $this->authenticationManager->authenticate($token);
            }

            $this->context->setToken($token);

            if (null !== $this->logger) {
                $this->logger->info('Populated SecurityContext with an anonymous Token');
            }
        } catch (AuthenticationException $failed) {
            if (null !== $this->logger) {
                $this->logger->info(sprintf('Anonymous authentication failed: %s', $failed->getMessage()));
            }
        }
    }
}
