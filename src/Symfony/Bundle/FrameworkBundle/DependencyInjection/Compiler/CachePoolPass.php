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

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class CachePoolPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        foreach ($container->findTaggedServiceIds('cache.pool') as $id => $tags) {
            $pool = $container->getDefinition($id);

            if (!$pool instanceof DefinitionDecorator) {
                throw new \InvalidArgumentException(sprintf('Services tagged with "cache.pool" must have a parent service but "%s" has none.', $id));
            }

            $adapter = $pool;

            do {
                $adapterId = $adapter->getParent();
                $adapter = $container->getDefinition($adapterId);
            } while ($adapter instanceof DefinitionDecorator && !$adapter->hasTag('cache.adapter'));

            if (!$adapter->hasTag('cache.adapter')) {
                throw new \InvalidArgumentException(sprintf('Services tagged with "cache.pool" must have a parent service tagged with "cache.adapter" but "%s" has none.', $id));
            }

            $tags = $adapter->getTag('cache.adapter');

            if (!isset($tags[0]['namespace_arg_index'])) {
                throw new \InvalidArgumentException(sprintf('Invalid "cache.adapter" tag for service "%s": attribute "namespace_arg_index" is missing.', $adapterId));
            }

            if (!$adapter->isAbstract()) {
                throw new \InvalidArgumentException(sprintf('Services tagged as "cache.adapter" must be abstract: "%s" is not.', $adapterId));
            }

            if (0 <= $namespaceArgIndex = $tags[0]['namespace_arg_index']) {
                $pool->replaceArgument($namespaceArgIndex, $this->getNamespace($id));
            }
        }
    }

    private function getNamespace($id)
    {
        return substr(str_replace('/', '-', base64_encode(md5('symfony.'.$id, true))), 0, 10);
    }
}
