<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
class CacheAdapterPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $adapters = array();

        foreach ($container->findTaggedServiceIds('cache.adapter') as $id => $tags) {
            foreach ($tags as $attributes) {
                $adapters[$attributes['id']] = array(
                    'definition_id' => $id,
                    'namespace_argument_index' => isset($attributes['namespace-arg-index']) ? $attributes['namespace-arg-index'] : null,
                );
            }
        }

        foreach ($container->getDefinitions() as $id => $definition) {
            $definition->setArguments($this->resolveArguments($adapters, $id, $definition->getArguments()));

            $calls = $definition->getMethodCalls();

            foreach ($calls as $index => $call) {
                $calls[$index] = array($call[0], $this->resolveArguments($adapters, $id, $call[1]));
            }

            $definition->setMethodCalls($calls);

            $definition->setProperties($this->resolveArguments($adapters, $id, $definition->getProperties()));
        }
    }

    private function resolveArguments(array $adapters, $id, array $arguments)
    {
        foreach ($arguments as $index => $argument) {
            if ($argument instanceof Reference) {
                $arguments[$index] = $this->createCacheAdapter($adapters, $id, $argument);
            }
        }

        return $arguments;
    }

    private function createCacheAdapter(array $adapters, $serviceId, Reference $argument)
    {
        $adapterId = (string) $argument;

        if (0 !== strpos($adapterId, 'cache.adapter.')) {
            return $argument;
        }

        $name = substr($adapterId, 14);

        if (!isset($adapters[$name])) {
            throw new \InvalidArgumentException(sprintf('The cache adapter "%s" is not configured.', $name));
        }

        $adapter = new DefinitionDecorator($adapters[$name]['definition_id']);

        if (null !== $adapters[$name]['namespace_argument_index']) {
            $adapter->replaceArgument($adapters[$name]['namespace_argument_index'], sha1($serviceId));
        }

        return $adapter;
    }
}
