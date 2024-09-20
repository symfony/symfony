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

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Can be implemented by a security factory to add a listener to the firewall.
 *
 * @author Christian Scheb <me@christianscheb.de>
 */
interface FirewallListenerFactoryInterface
{
    /**
     * Creates the firewall listener services for the provided configuration.
     *
     * @param array<string, mixed> $config
     *
     * @return string[] The listener service IDs to be used by the firewall
     */
    public function createListeners(ContainerBuilder $container, string $firewallName, array $config): array;
}
