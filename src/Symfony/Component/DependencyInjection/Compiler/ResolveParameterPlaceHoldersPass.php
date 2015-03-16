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
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;

/**
 * Resolves all parameter placeholders "%somevalue%" to their real values.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ResolveParameterPlaceHoldersPass implements CompilerPassInterface
{
    /**
     * Processes the ContainerBuilder to resolve parameter placeholders.
     *
     * @param ContainerBuilder $container
     *
     * @throws ParameterNotFoundException
     */
    public function process(ContainerBuilder $container)
    {
        $parameterBag = $container->getParameterBag();

        foreach ($container->getDefinitions() as $id => $definition) {
            try {
                $definition->setClass($parameterBag->resolveValue($definition->getClass()));
                $definition->setFile($parameterBag->resolveValue($definition->getFile()));
                $definition->setArguments($parameterBag->resolveValue($definition->getArguments()));
                $definition->setFactoryClass($parameterBag->resolveValue($definition->getFactoryClass()));
                $definition->setFactory($parameterBag->resolveValue($definition->getFactory()));

                $calls = array();
                foreach ($definition->getMethodCalls() as $name => $arguments) {
                    $calls[$parameterBag->resolveValue($name)] = $parameterBag->resolveValue($arguments);
                }
                $definition->setMethodCalls($calls);

                $definition->setProperties($parameterBag->resolveValue($definition->getProperties()));
            } catch (ParameterNotFoundException $e) {
                $e->setSourceId($id);

                throw $e;
            }
        }

        $aliases = array();
        foreach ($container->getAliases() as $name => $target) {
            $aliases[$parameterBag->resolveValue($name)] = $parameterBag->resolveValue($target);
        }
        $container->setAliases($aliases);

        $parameterBag->resolve();
    }
}
