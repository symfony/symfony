<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\DependencyInjection;

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\Workflow\Attribute\AsMarkingStore;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
class WorkflowMarkingStorePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Register marking store
        $container->registerAttributeForAutoconfiguration(AsMarkingStore::class, static function (ChildDefinition $definition, AsMarkingStore $attribute, \ReflectionClass $reflector): void {
            $tagAttributes = get_object_vars($attribute);
            $invalid = true;

            if ($constructor = $reflector->getConstructor()) {
                foreach ($constructor->getParameters() as $parameters) {
                    if ($tagAttributes['property'] === $parameters->getName()) {
                        $type = $parameters->getType();
                        $invalid = !$type instanceof \ReflectionNamedType || 'string' !== $type->getName();
                    }
                }
            }

            if ($invalid) {
                throw new LogicException(sprintf('The "%s" class doesn\'t have a constructor with a string type-hinted argument named "%s".', $reflector->getName(), $tagAttributes['property']));
            }

            $definition->replaceArgument('$'.$tagAttributes['property'], $tagAttributes['markingName']);
            $definition->addTag('workflow.marking_store', $tagAttributes);
        });
    }
}
