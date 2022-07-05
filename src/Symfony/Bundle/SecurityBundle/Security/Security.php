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
 * @author Ryan Weaver <ryan@symfonycasts.com>
 * @author Robin Chalas <robin.chalas@gmail.com>
 * @author Arnaud Frézet <arnaud@larriereguichet.fr>
 *
 * @final
 */
class Security extends LegacySecurity
{
    public function __construct(private readonly ContainerInterface $container, private readonly array $authenticators = [])
    {
        parent::__construct($container, false);
    }

    public function getFirewallConfig(Request $request): ?FirewallConfig
    {
        return $this->container->get('security.firewall.map')->getFirewallConfig($request);
    }

    /**
     * @param UserInterface $user              The user to authenticate
     * @param string|null   $authenticatorName The authenticator name (e.g. "form_login") or service id (e.g. SomeApiKeyAuthenticator::class) - required only if multiple authenticators are configured
     * @param string|null   $firewallName      The firewall name - required only if multiple firewalls are configured
     */
    public function login(UserInterface $user, string $authenticatorName = null, string $firewallName = null): void
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();
        $firewallName ??= $this->getFirewallConfig($request)?->getName();

        if (!$firewallName) {
            throw new LogicException('Unable to login as the current route is not covered by any firewall.');
        }

        $authenticator = $this->getAuthenticator($authenticatorName, $firewallName);

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
                throw new LogicException(sprintf('No authenticator was found for the firewall "%s".', $firewallName));
            }
            if (1 < \count($authenticatorIds)) {
                throw new LogicException(sprintf('Too much authenticators were found for the current firewall "%s". You must provide an instance of "%s" to login programmatically. The available authenticators for the firewall "%s" are "%s".', $firewallName, AuthenticatorInterface::class, $firewallName, implode('" ,"', $authenticatorIds)));
            }

            return $firewallAuthenticatorLocator->get($authenticatorIds[0]);
        }

        if ($firewallAuthenticatorLocator->has($authenticatorName)) {
            return $firewallAuthenticatorLocator->get($authenticatorName);
        }

        $authenticatorId = 'security.authenticator.'.$authenticatorName.'.'.$firewallName;

        if (!$firewallAuthenticatorLocator->has($authenticatorId)) {
            throw new LogicException(sprintf('Unable to find an authenticator named "%s" for the firewall "%s". Available authenticators: "%s".', $authenticatorName, implode('", "', $firewallAuthenticatorLocator->getProvidedServices())));
        }

        return $firewallAuthenticatorLocator->get($authenticatorId);
    }
}
