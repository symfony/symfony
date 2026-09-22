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

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
interface AuthenticatorFactoryInterface
{
    /**
     * Defines the priority at which the authenticator is called.
     */
    public function getPriority(): int;

    /**
     * Defines the configuration key used to reference the provider
     * in the firewall configuration.
     */
    public function getKey(): string;

    /**
     * Configures the node of this authenticator in the firewall config tree.
     *
     * The node must not depend on anything external, such as container parameters, env vars or the machine it is built on: the same tree must come out whatever the environment.
     */
    public function addConfiguration(NodeDefinition $builder): void;

    /**
     * Creates the authenticator service(s) for the provided configuration.
     *
     * @param array<string, mixed> $config
     *
     * @return string|string[] The authenticator service ID(s) to be used by the firewall
     */
    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, string $userProviderId): string|array;
}
