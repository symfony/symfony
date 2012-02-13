<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\CacheBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface,
    Symfony\Component\DependencyInjection\ContainerBuilder,
    Symfony\Component\DependencyInjection\Reference,
    Symfony\Component\DependencyInjection\DefinitionDecorator;

/**
 * @author Lukas Kahwe Smith <smith@pooteeweet.org>
 */
class ServiceCreationCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $namespaces = $container->getParameter('cache.namespaces');

        foreach ($namespaces as $name => $config) {
            $id = 'cache.driver.'.$config['type'];
            if (!$container->hasDefinition($id)) {
                throw new \InvalidArgumentException('Supplied cache type is not supported: '.$config['type']);
            }

            $namespace = empty($config['namespace']) ? $name : $config['namespace'];
            $service = $container
                ->setDefinition('cache.ns.'.$name, new DefinitionDecorator($id))
                ->addMethodCall('setNamespace', array($namespace));

            switch ($config['type']) {
                case 'memcache':
                    if (empty($config['id'])) {
                        throw new \InvalidArgumentException('Service id for memcache missing');
                    }
                    $service->addMethodCall('setMemcache', array(new Reference($config['id'])));
                    break;
                case 'memcached':
                    if (empty($config['id'])) {
                        throw new \InvalidArgumentException('Service id for memcached missing');
                    }
                    $service->addMethodCall('setMemcached', array(new Reference($config['id'])));
                    break;
            }
        }
    }
}
