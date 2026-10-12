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

final class DebugValidatorClassCandidatesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('console.command.debug.section.validator')) {
            return;
        }

        $classes = [];
        foreach ($container->getDefinitions() as $definition) {
            if ($definition->isAbstract() || $definition->isSynthetic()) {
                continue;
            }

            $class = $container->getParameterBag()->resolveValue($definition->getClass());

            if (!\is_string($class) || !$reflectionClass = $container->getReflectionClass($class, false)) {
                continue;
            }

            if ($reflectionClass->isInternal() || $reflectionClass->isInterface() || $reflectionClass->isTrait()) {
                continue;
            }

            $classes[$reflectionClass->name] = true;
        }

        $classes = array_keys($classes);
        sort($classes);

        $container->getDefinition('console.command.debug.section.validator')->setArgument(2, $classes);
    }
}
