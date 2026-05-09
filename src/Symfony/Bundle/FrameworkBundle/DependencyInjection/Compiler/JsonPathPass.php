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

/**
 * Collects metadata (arity, return type) for custom JsonPath functions
 * and injects it into the json_path.crawler service.
 */
class JsonPathPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('json_path.crawler')) {
            return;
        }

        $metadata = [];
        foreach ($container->findTaggedServiceIds('json_path.function') as $tags) {
            foreach ($tags as $attributes) {
                if (!isset($attributes['name'])) {
                    continue;
                }

                $metadata[$attributes['name']] = [
                    'arity' => $attributes['arity'] ?? null,
                    'return_type' => $attributes['return_type'] ?? null,
                ];
            }
        }

        $container->getDefinition('json_path.crawler')->setArgument(1, $metadata);
    }
}
