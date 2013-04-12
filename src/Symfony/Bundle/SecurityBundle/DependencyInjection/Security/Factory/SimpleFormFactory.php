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
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class SimpleFormFactory extends FormLoginFactory
{
    public function __construct()
    {
        parent::__construct();

        $this->addOption('authenticator', null);
    }

// TODO create proxies for success_handler/failure_handler that call the impl ones then the default ones by default if no response is returned

    public function getKey()
    {
        return 'simple-form';
    }

    public function addConfiguration(NodeDefinition $node)
    {
        parent::addConfiguration($node);

        $node->children()
            ->scalarNode('authenticator')->cannotBeEmpty()->end()
        ->end();
    }

    protected function getListenerId()
    {
        return 'security.authentication.listener.simple_form';
    }

    protected function createAuthProvider(ContainerBuilder $container, $id, $config, $userProviderId)
    {
        $provider = 'security.authentication.provider.simple_form.'.$id;
        $container
            ->setDefinition($provider, new DefinitionDecorator('security.authentication.provider.simple'))
            ->replaceArgument(0, new Reference($config['authenticator']))
            ->replaceArgument(1, new Reference($userProviderId))
            ->replaceArgument(2, $id)
        ;

        return $provider;
    }

    protected function createListener($container, $id, $config, $userProvider)
    {
        $listenerId = parent::createListener($container, $id, $config, $userProvider);

        if (!isset($config['csrf_provider'])) {
            $container
                ->getDefinition($listenerId)
                ->addArgument(null)
            ;
        }
        $container
            ->getDefinition($listenerId)
            ->addArgument(new Reference($config['authenticator']))
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
