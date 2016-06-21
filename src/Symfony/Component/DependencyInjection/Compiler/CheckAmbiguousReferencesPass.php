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

use Symfony\Component\DependencyInjection\AmbiguousDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\AmbiguousReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Martin Hasoň <martin.hason@gmail.com>
 */
class CheckAmbiguousReferencesPass implements CompilerPassInterface
{
    /** @var ContainerBuilder */
    private $container;

    public function process(ContainerBuilder $container)
    {
        $this->container = $container;
        foreach ($container->getDefinitions() as $id => $definition) {
            $this->processArguments($id, $definition->getArguments());
            $this->processArguments($id, $definition->getMethodCalls());
            $this->processArguments($id, $definition->getProperties());
            $this->processFactory($id, $definition->getFactory());
        }
    }

    private function processArguments($id, array $arguments)
    {
        foreach ($arguments as $argument) {
            $definition = $argument;

            if (is_array($argument)) {
                $this->processArguments($id, $argument);
            } elseif ($argument instanceof Reference) {
                try {
                    $definition = $this->container->findDefinition((string) $argument);
                } catch (ServiceNotFoundException $e) {
                    continue;
                }
            }

            if ($definition instanceof AmbiguousDefinition) {
                throw new AmbiguousReferenceException($definition->getClass(), $id, $definition->getServices());
            }
        }
    }

    private function processFactory($factory)
    {
        if (null === $factory || !is_array($factory) || !$factory[0] instanceof Reference) {
            return;
        }

        $definition = $this->container->findDefinition($id = (string) $factory[0]);

        if ($definition instanceof AmbiguousDefinition) {
            throw new AmbiguousReferenceException($definition->getClass(), $id, $definition->getServices());
        }
    }
}
