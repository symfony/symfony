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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Contracts\Service\Attribute\TagInterface;

final class AttributeAutoconfigurationPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (80000 > \PHP_VERSION_ID || !interface_exists(TagInterface::class)) {
            return;
        }

        foreach ($container->getDefinitions() as $definition) {
            if (!$definition->isAutoconfigured()) {
                continue;
            }

            if (!$class = $container->getParameterBag()->resolveValue($definition->getClass())) {
                continue;
            }

            try {
                $reflector = new \ReflectionClass($class);
            } catch (\ReflectionException $e) {
                continue;
            }

            foreach ($reflector->getAttributes(TagInterface::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                /** @var TagInterface $tag */
                $tag = $attribute->newInstance();
                $definition->addTag($tag->getName(), $tag->getAttributes());
            }
        }
    }
}
