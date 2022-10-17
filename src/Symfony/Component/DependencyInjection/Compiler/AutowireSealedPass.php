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

use Symfony\Component\DependencyInjection\Attribute\Sealed;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\SealedClassException;
use Symfony\Component\DependencyInjection\Reference;

final class AutowireSealedPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $sealedDefinitionList = array_filter($container->getDefinitions(), static function (Definition $definition) use ($container): bool {
            $reflectionClass = $container->getReflectionClass($definition->getClass(), false);

            return $reflectionClass instanceof \ReflectionClass && [] !== $reflectionClass->getAttributes(Sealed::class, \ReflectionAttribute::IS_INSTANCEOF);
        });

        foreach ($container->getDefinitions() as $definition) {
            $this->processClass($definition, $sealedDefinitionList);
        }
    }

    private function processClass(Definition $definition, array $sealedDefinitionList): void
    {
        $constructorArgs = $definition->getArguments();
        $definitionClassName = $definition->getClass();

        if ([] === $constructorArgs) {
            return;
        }

        $permittedClassesPerSealedClass = array_map(static function (Definition $definition) use ($sealedDefinitionList): array {
            $reflectionClass = new \ReflectionClass($sealedDefinitionList[$definition->getClass()]->getClass());

            return array_merge(...array_map(static fn (\ReflectionAttribute $attribute): array => $attribute->newInstance()->permits, $reflectionClass->getAttributes(Sealed::class, \ReflectionAttribute::IS_INSTANCEOF)));
        }, $sealedDefinitionList);

        foreach ($permittedClassesPerSealedClass as $sealedDefinition) {
            $definitionConstructorArguments = array_map(static fn (Reference $reference): string => $reference, $constructorArgs);

            if (\in_array($definitionClassName, $sealedDefinition, true)) {
                continue;
            }

            if (0 === \count(array_intersect($definitionConstructorArguments, $sealedDefinition))) {
                $argumentPosition = array_search($definitionClassName, $definitionConstructorArguments, true);

                $reflectionClass = new \ReflectionClass($definitionClassName);
                $arguments = $reflectionClass->getConstructor()->getParameters();

                throw new SealedClassException(sprintf('Cannot autowire service "%s", argument "$%s" of method "%s::__construct()" references class "%s" but this class is sealed.', $definitionClassName, $arguments[$argumentPosition]->getName(), $definitionClassName, $definition->getArgument($argumentPosition)));
            }
        }
    }
}
