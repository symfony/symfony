<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Ldap\Security\CheckLdapCredentialsListener;
use Symfony\Component\Ldap\Security\LdapAuthenticator;
use Symfony\Component\Ldap\Security\LdapUser;
use Symfony\Component\Ldap\Security\LdapUserProvider;
use Symfony\Component\Security\Core\User\ChainUserProvider;

/**
 * A trait decorating the authenticator with LDAP functionality.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @internal
 */
trait LdapFactoryTrait
{
    public function getKey(): string
    {
        return parent::getKey().'-ldap';
    }

    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, string $userProviderId): string
    {
        $key = str_replace('-', '_', $this->getKey());
        $definitions = $container->getDefinitions();
        $authenticatorId = parent::createAuthenticator($container, $firewallName, $config, $userProviderId);

        // the parent factory registers its authenticator under the id its non-LDAP variant uses as
        // well, so move the decorated one aside and hand the id back, to let both variants be
        // configured on the same firewall
        $decoratedId = 'security.authenticator.'.$key.'.'.$firewallName.'.inner';
        $container->setDefinition($decoratedId, $container->getDefinition($authenticatorId));
        $container->removeDefinition($authenticatorId);

        if (isset($definitions[$authenticatorId])) {
            $container->setDefinition($authenticatorId, $definitions[$authenticatorId]);
        }

        $authenticatorId = $decoratedId;

        if ($config['ldap_users_only']) {
            if (!property_exists(CheckLdapCredentialsListener::class, 'ldapUsersOnly')) {
                throw new InvalidConfigurationException('Using "ldap_users_only" requires symfony/ldap 8.2 or higher, the installed version would ignore it.');
            }

            if (false === self::providesLdapUsers($container, $userProviderId)) {
                throw new InvalidConfigurationException(\sprintf('Using "ldap_users_only" on the "%s" firewall requires a user provider that returns "%s" instances, but none of the providers it uses does.', $firewallName, LdapUser::class));
            }
        }

        $container->setDefinition('security.listener.'.$key.'.'.$firewallName, new Definition(CheckLdapCredentialsListener::class))
            ->addTag('kernel.event_subscriber', ['dispatcher' => 'security.event_dispatcher.'.$firewallName])
            ->addArgument(new Reference('security.ldap_locator'))
            ->addArgument($config['ldap_users_only'])
        ;

        $ldapAuthenticatorId = 'security.authenticator.'.$key.'.'.$firewallName;
        $definition = $container->setDefinition($ldapAuthenticatorId, new Definition(LdapAuthenticator::class))
            ->setArguments([
                new Reference($authenticatorId),
                $config['service'],
                $config['dn_string'],
                $config['search_dn'],
                $config['search_password'],
            ]);

        if (!empty($config['query_string'])) {
            if ('' === $config['search_dn'] || '' === $config['search_password']) {
                throw new InvalidConfigurationException('Using the "query_string" config without using a "search_dn" and a "search_password" is not supported.');
            }

            $definition->addArgument($config['query_string']);
        }

        return $ldapAuthenticatorId;
    }

    /**
     * Returns null when a provider class cannot be determined, so a setup we cannot read is never rejected.
     */
    private static function providesLdapUsers(ContainerBuilder $container, string $userProviderId): ?bool
    {
        if (!$container->hasDefinition($userProviderId) || !$class = self::resolveClass($container, $userProviderId)) {
            return null;
        }

        if (is_a($class, LdapUserProvider::class, true)) {
            return true;
        }

        if (!is_a($class, ChainUserProvider::class, true)) {
            return false;
        }

        $legs = $container->findDefinition($userProviderId)->getArguments()[0] ?? null;

        if (!$legs instanceof IteratorArgument) {
            return null;
        }

        $unknown = false;
        foreach ($legs->getValues() as $leg) {
            if (!$leg instanceof Reference) {
                return null;
            }

            if ($legProvidesLdapUsers = self::providesLdapUsers($container, (string) $leg)) {
                return true;
            }

            $unknown = $unknown || null === $legProvidesLdapUsers;
        }

        return $unknown ? null : false;
    }

    /**
     * User providers are child definitions, whose class only gets resolved by a later pass.
     */
    private static function resolveClass(ContainerBuilder $container, string $id): ?string
    {
        while (true) {
            $definition = $container->findDefinition($id);

            if ($class = $definition->getClass()) {
                return $class;
            }

            if (!$definition instanceof ChildDefinition || !$container->hasDefinition($id = $definition->getParent())) {
                return null;
            }
        }
    }
}
