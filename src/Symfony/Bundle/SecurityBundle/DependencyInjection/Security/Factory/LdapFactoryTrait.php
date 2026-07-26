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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Ldap\Security\CheckLdapCredentialsListener;
use Symfony\Component\Ldap\Security\LdapAuthenticator;

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

        $container->setDefinition('security.listener.'.$key.'.'.$firewallName, new Definition(CheckLdapCredentialsListener::class))
            ->addTag('kernel.event_subscriber', ['dispatcher' => 'security.event_dispatcher.'.$firewallName])
            ->addArgument(new Reference('security.ldap_locator'))
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
}
