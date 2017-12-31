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
 * HttpDigestFactory creates services for HTTP digest authentication.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class HttpDigestFactory implements SecurityFactoryInterface
{
    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
    {
        $provider = 'security.authentication.provider.dao.'.$id;
        $container
            ->setDefinition($provider, new DefinitionDecorator('security.authentication.provider.dao'))
            ->replaceArgument(0, new Reference($userProvider))
            ->replaceArgument(1, new Reference('security.user_checker.'.$id))
            ->replaceArgument(2, $id)
        ;

        // entry point
        $entryPointId = $this->createEntryPoint($container, $id, $config, $defaultEntryPoint);

        // listener
        $listenerId = 'security.authentication.listener.digest.'.$id;
        $listener = $container->setDefinition($listenerId, new DefinitionDecorator('security.authentication.listener.digest'));
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
            ->beforeNormalization()
                ->ifTrue(function ($v) { return isset($v['key']); })
                ->then(function ($v) {
                    if (isset($v['secret'])) {
                        throw new \LogicException('Cannot set both key and secret options for http_digest, use only secret instead.');
                    }

                    @trigger_error('http_digest.key is deprecated since Symfony 2.8 and will be removed in 3.0. Use http_digest.secret instead.', E_USER_DEPRECATED);

                    $v['secret'] = $v['key'];

                    unset($v['key']);

                    return $v;
                })
            ->end()
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
            ->setDefinition($entryPointId, new DefinitionDecorator('security.authentication.digest_entry_point'))
            ->addArgument($config['realm'])
            ->addArgument($config['secret'])
        ;

        return $entryPointId;
    }
}
