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
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * FormLoginFactory creates services for form login authentication.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class FormLoginFactory extends AbstractFactory
{
    public function __construct()
    {
        $this->addOption('username_parameter', '_username');
        $this->addOption('password_parameter', '_password');
        $this->addOption('csrf_parameter', '_csrf_token');
        $this->addOption('csrf_token_id', 'authenticate');
        $this->addOption('post_only', true);
    }

    public function getPosition()
    {
        return 'form';
    }

    public function getKey()
    {
        return 'form-login';
    }

    public function addConfiguration(NodeDefinition $node)
    {
        parent::addConfiguration($node);

        $node
            ->beforeNormalization()
                ->ifTrue(function ($v) { return isset($v['csrf_provider']) && isset($v['csrf_token_generator']); })
                ->thenInvalid("You should define a value for only one of 'csrf_provider' and 'csrf_token_generator' on a security firewall. Use 'csrf_token_generator' as this replaces 'csrf_provider'.")
            ->end()
            ->beforeNormalization()
                ->ifTrue(function ($v) { return isset($v['intention']) && isset($v['csrf_token_id']); })
                ->thenInvalid("You should define a value for only one of 'intention' and 'csrf_token_id' on a security firewall. Use 'csrf_token_id' as this replaces 'intention'.")
            ->end()
            ->beforeNormalization()
                ->ifTrue(function ($v) { return isset($v['csrf_provider']); })
                ->then(function ($v) {
                    @trigger_error("Setting the 'csrf_provider' configuration key on a security firewall is deprecated since Symfony 2.8 and will be removed in 3.0. Use the 'csrf_token_generator' configuration key instead.", E_USER_DEPRECATED);

                    $v['csrf_token_generator'] = $v['csrf_provider'];
                    unset($v['csrf_provider']);

                    return $v;
                })
            ->end()
            ->beforeNormalization()
                ->ifTrue(function ($v) { return isset($v['intention']); })
                ->then(function ($v) {
                    @trigger_error("Setting the 'intention' configuration key on a security firewall is deprecated since Symfony 2.8 and will be removed in 3.0. Use the 'csrf_token_id' key instead.", E_USER_DEPRECATED);

                    $v['csrf_token_id'] = $v['intention'];
                    unset($v['intention']);

                    return $v;
                })
            ->end()
            ->children()
                ->scalarNode('csrf_token_generator')->cannotBeEmpty()->end()
            ->end()
        ;
    }

    protected function getListenerId()
    {
        return 'security.authentication.listener.form';
    }

    protected function createAuthProvider(ContainerBuilder $container, $id, $config, $userProviderId)
    {
        $provider = 'security.authentication.provider.dao.'.$id;
        $container
            ->setDefinition($provider, new DefinitionDecorator('security.authentication.provider.dao'))
            ->replaceArgument(0, new Reference($userProviderId))
            ->replaceArgument(1, new Reference('security.user_checker.'.$id))
            ->replaceArgument(2, $id)
        ;

        return $provider;
    }

    protected function createListener($container, $id, $config, $userProvider)
    {
        $listenerId = parent::createListener($container, $id, $config, $userProvider);

        $container
            ->getDefinition($listenerId)
            ->addArgument(isset($config['csrf_token_generator']) ? new Reference($config['csrf_token_generator']) : null)
        ;

        return $listenerId;
    }

    protected function createEntryPoint($container, $id, $config, $defaultEntryPoint)
    {
        $entryPointId = 'security.authentication.form_entry_point.'.$id;
        $container
            ->setDefinition($entryPointId, new DefinitionDecorator('security.authentication.form_entry_point'))
            ->addArgument(new Reference('security.http_utils'))
            ->addArgument($config['login_path'])
            ->addArgument($config['use_forward'])
        ;

        return $entryPointId;
    }
}
