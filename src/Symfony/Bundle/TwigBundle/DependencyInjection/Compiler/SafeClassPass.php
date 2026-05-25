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
final class SafeClassPass implements CompilerPassInterface
{
    private const BUILTIN_STRATEGIES = ['html', 'js', 'css', 'url', 'html_attr', 'html_attr_relaxed', 'all'];

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('twig.runtime.escaper')) {
            return;
        }

        $definition = $container->getDefinition('twig.runtime.escaper');

        foreach ($container->findTaggedResourceIds('twig.safe_class') as $id => $tags) {
            $class = $container->getDefinition($id)->getClass();
            foreach ($tags as $tag) {
                $definition->addMethodCall('addSafeClass', [$class, $this->normalizeStrategies($tag, $id)]);
            }
        }
    }

    private function normalizeStrategies(array $tag, string $id): array
    {
        $strategies = $tag['strategy'] ?? null;
        if (\is_string($strategies)) {
            $strategies = [$strategies];
        } elseif (!\is_array($strategies) || array_filter($strategies, 'is_string') !== $strategies) {
            throw new InvalidArgumentException(\sprintf('The "strategy" attribute of the "twig.safe_class" tag on "%s" must be a string or a list of strings.', $id));
        } elseif (!$strategies) {
            throw new InvalidArgumentException(\sprintf('The "strategy" attribute of the "twig.safe_class" tag on "%s" must not be empty; use "all" to mark the class safe for every strategy.', $id));
        }

        foreach ($strategies as $strategy) {
            if (\in_array($strategy, self::BUILTIN_STRATEGIES, true)) {
                continue;
            }
            if (!preg_match('/^[a-z][a-z0-9_]*$/D', $strategy)) {
                throw new InvalidArgumentException(\sprintf('The "strategy" attribute of the "twig.safe_class" tag on "%s" contains the invalid strategy "%s"; expected one of "%s" or a custom name matching "[a-z][a-z0-9_]*".', $id, $strategy, implode('", "', self::BUILTIN_STRATEGIES)));
            }
        }

        return $strategies;
    }
}
