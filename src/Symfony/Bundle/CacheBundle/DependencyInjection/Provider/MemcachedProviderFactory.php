<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\CacheBundle\DependencyInjection\Provider;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Alias;

/**
 * @author Victor Berchet <victor@suumit.com>
 */
class MemcachedProviderFactory extends AbstractProviderFactory
{
    public function getDefinition(array $config)
    {
        $definition = new DefinitionDecorator('cache.provider.memcached');

        return $definition->addMethodCall('setMemcached', array(new Reference($config['backend'])));
    }

    protected function getSignature(ContainerBuilder $container, array $config)
    {
        $backend = $config['backend'];

        return md5(serialize(array(
            'backend'   => $container->hasAlias($backend) ? (string) $container->getAlias($backend) : $backend,
            'namespace' => $config['namespace']
        )));
    }
}