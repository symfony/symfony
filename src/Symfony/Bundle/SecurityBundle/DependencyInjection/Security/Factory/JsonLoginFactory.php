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

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * JsonLoginFactory creates services for JSON login authentication.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @internal
 */
class JsonLoginFactory extends AbstractFactory
{
    public const PRIORITY = -40;

    public function __construct()
    {
        $this->addOption('username_path', 'username');
        $this->addOption('password_path', 'password');
        $this->defaultFailureHandlerOptions = [];
        $this->defaultSuccessHandlerOptions = [];
    }

    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    /**
     * {@inheritdoc}
     */
    public function getKey(): string
    {
        return 'json-login';
    }

    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, string $userProviderId): string
    {
        $authenticatorId = 'security.authenticator.json_login.'.$firewallName;
        $options = array_intersect_key($config, $this->options);
        $container
            ->setDefinition($authenticatorId, new ChildDefinition('security.authenticator.json_login'))
            ->replaceArgument(1, new Reference($userProviderId))
            ->replaceArgument(2, isset($config['success_handler']) ? new Reference($this->createAuthenticationSuccessHandler($container, $firewallName, $config)) : null)
            ->replaceArgument(3, isset($config['failure_handler']) ? new Reference($this->createAuthenticationFailureHandler($container, $firewallName, $config)) : null)
            ->replaceArgument(4, $options);

        return $authenticatorId;
    }
}
