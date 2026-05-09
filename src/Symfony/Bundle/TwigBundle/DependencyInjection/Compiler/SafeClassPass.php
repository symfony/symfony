<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * Registers safe classes for Twig's escaper.
 */
class SafeClassPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('twig.runtime.escaper')) {
            return;
        }

        $definition = $container->getDefinition('twig.runtime.escaper');

        foreach ($container->findTaggedResourceIds('twig.safe_class') as $id => $tags) {
            $class = $container->getDefinition($id)->getClass();
            foreach ($tags as $tag) {
                $strategies = $tag['strategy'] ?? null;
                if (\is_string($strategies)) {
                    $strategies = [$strategies];
                } elseif (!\is_array($strategies) || $strategies !== array_filter($strategies, 'is_string')) {
                    throw new InvalidArgumentException(\sprintf('The "strategy" attribute of the "twig.safe_class" tag on "%s" must be a string or a list of strings.', $id));
                }
                $definition->addMethodCall('addSafeClass', [$class, $strategies]);
            }
        }
    }
}
