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
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Jordi Boggiano <j.boggiano@seld.be>
 *
 * @deprecated since Symfony 4.2, use Guard instead.
 */
class SimpleFormFactory extends FormLoginFactory
{
    public function __construct(bool $triggerDeprecation = true)
    {
        parent::__construct();

        $this->addOption('authenticator', null);

        if ($triggerDeprecation) {
            @trigger_error(sprintf('The "%s" class is deprecated since Symfony 4.2, use Guard instead.', __CLASS__), \E_USER_DEPRECATED);
        }
    }

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
            ->setDefinition($provider, new ChildDefinition('security.authentication.provider.simple'))
            ->replaceArgument(0, new Reference($config['authenticator']))
            ->replaceArgument(1, new Reference($userProviderId))
            ->replaceArgument(2, $id)
            ->replaceArgument(3, new Reference('security.user_checker.'.$id))
        ;

        return $provider;
    }

    protected function createListener($container, $id, $config, $userProvider)
    {
        $listenerId = parent::createListener($container, $id, $config, $userProvider);

        $simpleAuthHandlerId = 'security.authentication.simple_success_failure_handler.'.$id;
        $simpleAuthHandler = $container->setDefinition($simpleAuthHandlerId, new ChildDefinition('security.authentication.simple_success_failure_handler'));
        $simpleAuthHandler->replaceArgument(0, new Reference($config['authenticator']));
        $simpleAuthHandler->replaceArgument(1, new Reference($this->getSuccessHandlerId($id)));
        $simpleAuthHandler->replaceArgument(2, new Reference($this->getFailureHandlerId($id)));

        $listener = $container->getDefinition($listenerId);
        $listener->replaceArgument(5, new Reference($simpleAuthHandlerId));
        $listener->replaceArgument(6, new Reference($simpleAuthHandlerId));
        $listener->addArgument(new Reference($config['authenticator']));

        return $listenerId;
    }
}
