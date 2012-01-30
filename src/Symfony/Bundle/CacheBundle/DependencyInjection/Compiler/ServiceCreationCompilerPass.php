<?php

namespace Symfony\Bundle\CacheBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface,
    Symfony\Component\DependencyInjection\ContainerBuilder,
    Symfony\Component\DependencyInjection\Reference,
    Symfony\Component\DependencyInjection\DefinitionDecorator;

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
