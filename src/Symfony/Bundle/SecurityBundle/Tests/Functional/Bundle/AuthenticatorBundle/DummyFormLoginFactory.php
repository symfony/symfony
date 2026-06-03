<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\AuthenticatorBundle;

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\FormLoginFactory;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class DummyFormLoginFactory extends FormLoginFactory
{
    public function getKey(): string
    {
        return 'dummy_form_login';
    }

    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, string $userProviderId): string
    {
        $authenticatorId = 'security.authenticator.dummy_form_login.'.$firewallName;
        $options = array_intersect_key($config, $this->options);
        $authenticator = $container
            ->setDefinition($authenticatorId, new ChildDefinition('security.authenticator.form_login'))
            ->replaceArgument(1, new Reference($userProviderId))
            ->replaceArgument(2, new Reference($this->createAuthenticationSuccessHandler($container, $firewallName, $config)))
            ->replaceArgument(3, new Reference($this->createAuthenticationFailureHandler($container, $firewallName, $config)))
            ->replaceArgument(4, $options);

        if ($options['use_forward'] ?? false) {
            $authenticator->addMethodCall('setHttpKernel', [new Reference('http_kernel')]);
        }

        return $authenticatorId;
    }
}
