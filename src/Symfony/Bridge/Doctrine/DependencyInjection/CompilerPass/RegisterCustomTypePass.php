<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass;

use Doctrine\DBAL\Types\Type;
use Symfony\Bridge\Doctrine\Attribute\AsDoctrineType;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Registers Doctrine DBAL types from classes annotated with #[AsDoctrineType].
 *
 * The pass scans all container definitions for the attribute and registers
 * matching types in the doctrine.dbal.connection_factory.types parameter.
 *
 * Usage in DoctrineBundle::build():
 *
 *     $container->addCompilerPass(new RegisterCustomTypePass());
 */
final class RegisterCustomTypePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('doctrine.dbal.connection_factory.types')) {
            return;
        }

        /** @var array<string, array{class: class-string<Type>}> $types */
        $types = $container->getParameter('doctrine.dbal.connection_factory.types');

        foreach ($container->getDefinitions() as $definition) {
            $class = $definition->getClass();

            if (null === $class || !class_exists($class) || !is_a($class, Type::class, true)) {
                continue;
            }

            $reflectionClass = new \ReflectionClass($class);
            $attributes = $reflectionClass->getAttributes(AsDoctrineType::class);

            if ([] === $attributes) {
                continue;
            }

            $attribute = $attributes[0]->newInstance();
            $name = $attribute->name ?? $class;

            $types[$name] ??= ['class' => $class];
        }

        $container->setParameter('doctrine.dbal.connection_factory.types', $types);
    }
}
