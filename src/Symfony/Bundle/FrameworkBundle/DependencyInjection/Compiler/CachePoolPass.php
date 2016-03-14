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
 * @author Nicolas Grekas <p@tchwork.com>
 */
class CachePoolPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $attributes = array(
            'provider_service',
            'namespace',
            'default_lifetime',
            'directory',
        );
        foreach ($container->findTaggedServiceIds('cache.pool') as $id => $tags) {
            $adapter = $pool = $container->getDefinition($id);
            $tags[0] += array('namespace' => $this->getNamespace($id));

            while ($adapter instanceof DefinitionDecorator) {
                $adapter = $container->findDefinition($adapter->getParent());
                if ($t = $adapter->getTag('cache.pool')) {
                    $tags[0] += $t[0];
                }
            }
            if ($pool->isAbstract()) {
                continue;
            }
            if (isset($tags[0]['provider_service']) && is_string($tags[0]['provider_service'])) {
                $tags[0]['provider_service'] = new Reference($tags[0]['provider_service']);
            }
            $i = 0;
            foreach ($attributes as $attr) {
                if (isset($tags[0][$attr])) {
                    $pool->replaceArgument($i++, $tags[0][$attr]);
                    unset($tags[0][$attr]);
                }
            }
            if (!empty($tags[0])) {
                throw new \InvalidArgumentException(sprintf('Invalid "cache.pool" tag for service "%s": accepted attributes are "provider_service", "namespace", "default_lifetime" and "directory", found "%s".', $id, implode('", "', array_keys($tags[0]))));
            }
        }
    }

    private function getNamespace($id)
    {
        return substr(str_replace('/', '-', base64_encode(md5('symfony.'.$id, true))), 0, 10);
    }
}
