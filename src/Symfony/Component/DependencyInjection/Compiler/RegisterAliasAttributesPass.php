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

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * Reads #[AsAlias] attributes on definitions that are autoconfigured
 * and don't have the "container.ignore_attributes" tag.
 */
final class RegisterAliasAttributesPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (80000 > \PHP_VERSION_ID) {
            return;
        }

        foreach ($container->getDefinitions() as $id => $definition) {
            if ($this->accept($definition) && null !== $class = $container->getReflectionClass($definition->getClass())) {
                $this->processClass($container, $class, $id);
            }
        }
    }

    private function accept(Definition $definition): bool
    {
        return 80000 <= \PHP_VERSION_ID && $definition->isAutoconfigured() && !$definition->hasTag('container.ignore_attributes');
    }

    private function processClass(ContainerBuilder $container, \ReflectionClass $class, string $id): void
    {
        foreach ($class->getAttributes(AsAlias::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            /** @var AsAlias $asAliasAttribute */
            $asAliasAttribute = $attribute->newInstance();

            if ($container->hasAlias($alias = $asAliasAttribute->id)) {
                throw new RuntimeException(sprintf('The service "%s" cannot use the alias "%s" as it is already used by "%s".', $id, $alias, (string) $container->getAlias($alias)));
            }

            $container->setAlias($alias, new Alias($id, $asAliasAttribute->public));
        }
    }
}
