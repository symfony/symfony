<?php

namespace Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Fixtures\Authenticator;

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AuthenticatorFactoryInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CustomAuthenticator implements AuthenticatorFactoryInterface
{
    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, string $userProviderId): string
    {
        return 'security.authenticator.custom.'.$firewallName;
    }

    public function getKey(): string
    {
        return 'custom';
    }

    public function addConfiguration(NodeDefinition $builder): void
    {
    }

    public function getPriority(): int
    {
        return 0;
    }
}
