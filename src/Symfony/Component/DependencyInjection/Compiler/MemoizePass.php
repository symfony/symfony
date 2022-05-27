<?php

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Attribute\Memoizable;
use Symfony\Component\DependencyInjection\Attribute\Memoize;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\MemoizeProxy\BaseKeyGenerator;
use Symfony\Component\DependencyInjection\MemoizeProxy\MemoizeFactory;
use Symfony\Component\DependencyInjection\Reference;

class MemoizePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container->register('dependency_injection.memoize_proxy.factory', MemoizeFactory::class)
            ->setArguments([$container->getParameter('kernel.cache_dir').'/memoize']);
        $container->register('dependency_injection.memoize_proxy.key_generator.base', BaseKeyGenerator::class);

        foreach ($container->getDefinitions() as $id => $definition) {
            if (!$definition->isAutoconfigured()) {
                continue;
            }

            // Is MemoizeClass
            if (!$class = $container->getReflectionClass($definition->getClass())) {
                continue;
            }
            if (!$class->getAttributes(Memoizable::class, \ReflectionAttribute::IS_INSTANCEOF)) {
                continue;
            }

            // Get Memoize methods
            $methods = [];
            foreach ($class->getMethods() as $method) {
                if (!$memoize = $method->getAttributes(Memoize::class, \ReflectionAttribute::IS_INSTANCEOF)) {
                    continue;
                }
                $memoize = $memoize[0]->newInstance();

                $methods[$method->getName()] = [
                    new Reference($memoize->pool),
                    new Reference($memoize->keyGenerator ?: 'dependency_injection.memoize_proxy.key_generator.base'),
                    $memoize->ttl,
                ];
            }

            if (!$methods) {
                continue;
            }

            // Create proxy
            $proxy = $container->register($id.'.memoized', $definition->getClass());
            $proxy->setDecoratedService($id);
            $proxy->setFactory(new Reference('dependency_injection.memoize_proxy.factory'));
            $proxy->setArguments([new Reference('.inner'), $methods]);
        }
    }

}
