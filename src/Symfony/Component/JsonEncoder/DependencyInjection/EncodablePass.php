<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Sets the encodable classes to the services that need them.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
class EncodablePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('json_encoder.encoder')) {
            return;
        }

        $encodableClassNames = [];

        // retrieve concrete services tagged with "json_encoder.encodable" tag
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!$definition->hasTag('json_encoder.encodable')) {
                continue;
            }

            if (($className = $container->getDefinition($id)->getClass()) && !$container->getDefinition($id)->isAbstract()) {
                $encodableClassNames[] = $className;
            }

            $container->removeDefinition($id);
        }

        $container->getDefinition('.json_encoder.cache_warmer.encoder_decoder')
            ->replaceArgument(0, $encodableClassNames);

        if ($container->hasDefinition('.json_encoder.cache_warmer.lazy_ghost')) {
            $container->getDefinition('.json_encoder.cache_warmer.lazy_ghost')
                ->replaceArgument(0, $encodableClassNames);
        }
    }
}
