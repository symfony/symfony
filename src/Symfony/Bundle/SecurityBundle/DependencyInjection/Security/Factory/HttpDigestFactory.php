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
 * HttpDigestFactory creates services for HTTP digest authentication.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since 3.4, to be removed in 4.0
 */
class HttpDigestFactory implements SecurityFactoryInterface
{
    public function __construct($triggerDeprecation = true)
    {
        if ($triggerDeprecation) {
            @trigger_error(sprintf('The "%s" class and the whole HTTP digest authentication system is deprecated since Symfony 3.4 and will be removed in 4.0.', __CLASS__), E_USER_DEPRECATED);
        }
    }

    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
    {
        $provider = 'security.authentication.provider.dao.'.$id;
        $container
            ->setDefinition($provider, new ChildDefinition('security.authentication.provider.dao'))
            ->replaceArgument(0, new Reference($userProvider))
            ->replaceArgument(1, new Reference('security.user_checker.'.$id))
            ->replaceArgument(2, $id)
        ;

        // entry point
        $entryPointId = $this->createEntryPoint($container, $id, $config, $defaultEntryPoint);

        // listener
        $listenerId = 'security.authentication.listener.digest.'.$id;
        $listener = $container->setDefinition($listenerId, new ChildDefinition('security.authentication.listener.digest'));
        $listener->replaceArgument(1, new Reference($userProvider));
        $listener->replaceArgument(2, $id);
        $listener->replaceArgument(3, new Reference($entryPointId));

        return array($provider, $listenerId, $entryPointId);
    }

    public function getPosition()
    {
        return 'http';
    }

    public function getKey()
    {
        return 'http-digest';
    }

    public function addConfiguration(NodeDefinition $node)
    {
        $node
            ->setDeprecated('The HTTP digest authentication is deprecated since 3.4 and will be removed in 4.0.')
            ->children()
                ->scalarNode('provider')->end()
                ->scalarNode('realm')->defaultValue('Secured Area')->end()
                ->scalarNode('secret')->isRequired()->cannotBeEmpty()->end()
            ->end()
        ;
    }

    protected function createEntryPoint($container, $id, $config, $defaultEntryPoint)
    {
        if (null !== $defaultEntryPoint) {
            return $defaultEntryPoint;
        }

        $entryPointId = 'security.authentication.digest_entry_point.'.$id;
        $container
            ->setDefinition($entryPointId, new ChildDefinition('security.authentication.digest_entry_point'))
            ->addArgument($config['realm'])
            ->addArgument($config['secret'])
        ;

        return $entryPointId;
    }
}
