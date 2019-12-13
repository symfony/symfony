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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use Symfony\Component\Security\Http\Firewall\GuardManagerListener;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class LazyGuardManagerListener extends GuardManagerListener
{
    private $guardLocator;

    public function __construct(
        AuthenticationManagerInterface $authenticationManager,
        GuardAuthenticatorHandler $guardHandler,
        ServiceLocator $guardLocator,
        string $providerKey,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($authenticationManager, $guardHandler, [], $providerKey, $logger);

        $this->guardLocator = $guardLocator;
    }

    protected function getSupportingGuardAuthenticators(Request $request): array
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
