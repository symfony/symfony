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
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Lukas Kahwe Smith <smith@pooteeweet.org>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
abstract class AbstractFactory implements AuthenticatorFactoryInterface
{
    protected array $options = [
        'check_path' => '/login_check',
        'use_forward' => false,
        'login_path' => '/login',
    ];

    protected array $defaultSuccessHandlerOptions = [
        'always_use_default_target_path' => false,
        'default_target_path' => '/',
        'login_path' => '/login',
        'target_path_parameter' => '_target_path',
        'use_referer' => false,
    ];

    protected array $defaultFailureHandlerOptions = [
        'failure_path' => null,
        'failure_forward' => false,
        'login_path' => '/login',
        'failure_path_parameter' => '_failure_path',
    ];

    final public function addOption(string $name, mixed $default = null): void
    {
        $this->options[$name] = $default;
    }

    public function addConfiguration(NodeDefinition $node): void
    {
        $builder = $node->children();

        $builder
            ->scalarNode('provider')->end()
            ->booleanNode('remember_me')->defaultTrue()->end()
            ->scalarNode('success_handler')->end()
            ->scalarNode('failure_handler')->end()
        ;

        foreach (array_merge($this->options, $this->defaultSuccessHandlerOptions, $this->defaultFailureHandlerOptions) as $name => $default) {
            if (\is_bool($default)) {
                $builder->booleanNode($name)->defaultValue($default);
            } else {
                $builder->scalarNode($name)->defaultValue($default);
            }
        }
    }

    protected function createAuthenticationSuccessHandler(ContainerBuilder $container, string $id, array $config): string
    {
        $successHandlerId = $this->getSuccessHandlerId($id);
        $options = array_intersect_key($config, $this->defaultSuccessHandlerOptions);

        if (isset($config['success_handler'])) {
            $successHandler = $container->setDefinition($successHandlerId, new ChildDefinition('security.authentication.custom_success_handler'));
            $successHandler->replaceArgument(0, new ChildDefinition($config['success_handler']));
            $successHandler->replaceArgument(1, $options);
            $successHandler->replaceArgument(2, $id);
        } else {
            $successHandler = $container->setDefinition($successHandlerId, new ChildDefinition('security.authentication.success_handler'));
            $successHandler->addMethodCall('setOptions', [$options]);
            $successHandler->addMethodCall('setFirewallName', [$id]);
        }

        return $successHandlerId;
    }

    protected function createAuthenticationFailureHandler(ContainerBuilder $container, string $id, array $config): string
    {
        $id = $this->getFailureHandlerId($id);
        $options = array_intersect_key($config, $this->defaultFailureHandlerOptions);

        if (isset($config['failure_handler'])) {
            $failureHandler = $container->setDefinition($id, new ChildDefinition('security.authentication.custom_failure_handler'));
            $failureHandler->replaceArgument(0, new ChildDefinition($config['failure_handler']));
            $failureHandler->replaceArgument(1, $options);
        } else {
            $failureHandler = $container->setDefinition($id, new ChildDefinition('security.authentication.failure_handler'));
            $failureHandler->addMethodCall('setOptions', [$options]);
        }

        return $id;
    }

    protected function getSuccessHandlerId(string $id): string
    {
        return 'security.authentication.success_handler.'.$id.'.'.str_replace('-', '_', $this->getKey());
    }

    protected function getFailureHandlerId(string $id): string
    {
        return 'security.authentication.failure_handler.'.$id.'.'.str_replace('-', '_', $this->getKey());
    }
}
