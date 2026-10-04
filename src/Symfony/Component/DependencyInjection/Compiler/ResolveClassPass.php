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

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ResolveClassPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $acyclic = [];

        foreach ($container->getDefinitions() as $id => $definition) {
            // Passes that run before ResolveChildDefinitionsPass may walk the parents of a definition: report a circular chain before they loop forever
            $path = [];
            $parentDefinition = $definition;
            while ($parentDefinition instanceof ChildDefinition && !isset($acyclic[$parent = $parentDefinition->getParent()]) && $container->has($parent)) {
                $i = array_search($parent, $path, true);
                $path[] = $parent;

                if (false !== $i) {
                    throw new ServiceCircularReferenceException($parent, \array_slice($path, $i));
                }

                $parentDefinition = $container->findDefinition($parent);
            }
            $acyclic += array_fill_keys($path, true);

            if ($definition->isSynthetic()
                || $definition->hasErrors()
                || null !== $definition->getClass()
                || !preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+(?:\\\\[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+)++$/', $id)
            ) {
                continue;
            }
            if ($container->getReflectionClass($id, false)) {
                $definition->setClass($id);
                continue;
            }
            if ($definition instanceof ChildDefinition) {
                throw new InvalidArgumentException(\sprintf('Service definition "%s" has a parent but no class, and its name looks like a FQCN. Either the class is missing or you want to inherit it from the parent service. To resolve this ambiguity, please rename this service to a non-FQCN (e.g. using dots), or create the missing class.', $id));
            }

            trigger_deprecation('symfony/dependency-injection', '7.4', 'Service id "%s" looks like a FQCN but no corresponding class or interface exists. To resolve this ambiguity, please rename this service to a non-FQCN (e.g. using dots), or create the missing class or interface.', $id);
            // throw new InvalidArgumentException(\sprintf('Service id "%s" looks like a FQCN but no corresponding class or interface exists. To resolve this ambiguity, please rename this service to a non-FQCN (e.g. using dots), or create the missing class or interface.'), $id);
            $definition->setClass($id);
        }
    }
}
