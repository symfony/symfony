<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Security;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\LogicException;
use Symfony\Component\Security\Core\Security as LegacySecurity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * Helper class for commonly-needed security tasks.
 *
 * @final
 */
class Security extends LegacySecurity
{
    public function __construct(private ContainerInterface $container, private array $authenticators = [])
    {
        parent::__construct($container, false);
    }

    public function getFirewallConfig(Request $request): ?FirewallConfig
    {
        return $this->container->get('security.firewall.map')->getFirewallConfig($request);
    }

    public function login(UserInterface $user, string $authenticatorName = null, string $firewallName = null): void
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();

        if (!class_exists(AuthenticatorInterface::class)) {
            throw new \LogicException('Security HTTP is missing. Try running "composer require symfony/security-http".');
        }
        $authenticator = $this->getAuthenticator($authenticatorName, $firewallName ?? $this->getFirewallName($request));

        $this->container->get('security.user_checker')->checkPreAuth($user);
        $this->container->get('security.user_authenticator')->authenticateUser($user, $authenticator, $request);
    }

    private function getAuthenticator(?string $authenticatorName, string $firewallName): AuthenticatorInterface
    {
        if (!\array_key_exists($firewallName, $this->authenticators)) {
            throw new LogicException(sprintf('No authenticators found for firewall "%s".', $firewallName));
        }
        /** @var ServiceProviderInterface $firewallAuthenticatorLocator */
        $firewallAuthenticatorLocator = $this->authenticators[$firewallName];

        if (!$authenticatorName) {
            $authenticatorIds = array_keys($firewallAuthenticatorLocator->getProvidedServices());

            if (!$authenticatorIds) {
                throw new LogicException('No authenticator was found for the firewall "%s".');
            }

            if (1 < \count($authenticatorIds)) {
                throw new LogicException(sprintf('Too much authenticators were found for the current firewall "%s". You must provide an instance of "%s" to login programmatically. The available authenticators for the firewall "%s" are "%s".', $firewallName, AuthenticatorInterface::class, $firewallName, implode('" ,"', $authenticatorIds)));
            }

            return $firewallAuthenticatorLocator->get($authenticatorIds[0]);
        }
        $authenticatorId = 'security.authenticator.'.$authenticatorName.'.'.$firewallName;

        if (!$firewallAuthenticatorLocator->has($authenticatorId)) {
            throw new LogicException(sprintf('Unable to find an authenticator named "%s" for the firewall "%s". Try to pass a firewall name in the Security::login() method.', $authenticatorName, $firewallName));
        }

        return $firewallAuthenticatorLocator->get($authenticatorId);
    }

    private function getFirewallName(Request $request): string
    {
        $firewall = $this->container->get('security.firewall.map')->getFirewallConfig($request);

        if (null === $firewall) {
            throw new LogicException('No firewall found as the current route is not covered by any firewall.');
        }

        return $firewall->getName();
    }
}
