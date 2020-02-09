<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticatorHandler;
use Symfony\Component\Security\Http\Firewall\AuthenticatorManagerListener;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @experimental in 5.1
 */
class LazyAuthenticatorManagerListener extends AuthenticatorManagerListener
{
    private $authenticatorLocator;

    public function __construct(
        AuthenticationManagerInterface $authenticationManager,
        AuthenticatorHandler $authenticatorHandler,
        ServiceLocator $authenticatorLocator,
        string $providerKey,
        EventDispatcherInterface $eventDispatcher,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($authenticationManager, $authenticatorHandler, [], $providerKey, $eventDispatcher, $logger);

        $this->authenticatorLocator = $authenticatorLocator;
    }

    protected function getSupportingAuthenticators(Request $request): array
    {
        $authenticators = [];
        $lazy = true;
        foreach ($this->authenticatorLocator->getProvidedServices() as $key => $type) {
            $authenticator = $this->authenticatorLocator->get($key);
            if (null !== $this->logger) {
                $this->logger->debug('Checking support on guard authenticator.', ['firewall_key' => $this->providerKey, 'authenticator' => \get_class($authenticator)]);
            }

            if (false !== $supports = $authenticator->supports($request)) {
                $authenticators[$key] = $authenticator;
                $lazy = $lazy && null === $supports;
            } elseif (null !== $this->logger) {
                $this->logger->debug('Guard authenticator does not support the request.', ['firewall_key' => $this->providerKey, 'authenticator' => \get_class($authenticator)]);
            }
        }

        return [$authenticators, $lazy];
    }
}
