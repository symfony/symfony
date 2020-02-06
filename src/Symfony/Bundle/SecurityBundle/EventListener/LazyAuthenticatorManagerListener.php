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
    private $guardLocator;

    public function __construct(
        AuthenticationManagerInterface $authenticationManager,
        AuthenticatorHandler $authenticatorHandler,
        ServiceLocator $guardLocator,
        string $providerKey,
        EventDispatcherInterface $eventDispatcher,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($authenticationManager, $authenticatorHandler, [], $providerKey, $eventDispatcher, $logger);

        $this->guardLocator = $guardLocator;
    }

    protected function getSupportingAuthenticators(Request $request): array
    {
        $guardAuthenticators = [];
        foreach ($this->guardLocator->getProvidedServices() as $key => $type) {
            $guardAuthenticator = $this->guardLocator->get($key);
            if (null !== $this->logger) {
                $this->logger->debug('Checking support on guard authenticator.', ['firewall_key' => $this->providerKey, 'authenticator' => \get_class($guardAuthenticator)]);
            }

            if ($guardAuthenticator->supports($request)) {
                $guardAuthenticators[$key] = $guardAuthenticator;
            } elseif (null !== $this->logger) {
                $this->logger->debug('Guard authenticator does not support the request.', ['firewall_key' => $this->providerKey, 'authenticator' => \get_class($guardAuthenticator)]);
            }
        }

        return $guardAuthenticators;
    }
}
