<?php

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory;

use Symfony\Component\DependencyInjection\ContainerBuilder;

interface FirewallListenerFactoryInterface
{
    /**
     * Creates the firewall listener service(s) for the provided configuration.
     *
     * @return string|string[] The listener service ID(s) to be used by the firewall
     */
    public function createListeners(ContainerBuilder $container, string $firewallName, array $config);
}
