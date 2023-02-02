<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

final class AliasPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        foreach ($container->findTaggedServiceIds(AsAlias::class) as $serviceId => $tags) {
            foreach ($tags as $tag) {
                if (false === isset($tag['alias'])) {
                    throw new InvalidArgumentException(sprintf('The "alias" attribute is mandatory for the "%s" tag.', AsAlias::class));
                }

                $container->setAlias($tag['alias'], $serviceId);
            }
        }
    }
}
