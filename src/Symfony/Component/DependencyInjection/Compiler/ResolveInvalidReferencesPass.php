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

use Symfony\Component\DependencyInjection\Argument\ArgumentInterface;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\TypedReference;

/**
 * Emulates the invalid behavior if the reference is not found within the
 * container.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ResolveInvalidReferencesPass implements CompilerPassInterface
{
    private ContainerBuilder $container;
    private RuntimeException $signalingException;
    private string $currentId;

    /**
     * Process the ContainerBuilder to resolve invalid references.
     */
    public function process(ContainerBuilder $container)
    {
        $this->container = $container;
        $this->signalingException = new RuntimeException('Invalid reference.');

        try {
            foreach ($container->getDefinitions() as $this->currentId => $definition) {
                $this->processValue($definition);
            }
        } finally {
            unset($this->container, $this->signalingException);
        }
    }

    /**
     * Processes arguments to determine invalid references.
     *
     * @throws RuntimeException When an invalid reference is found
     */
    private function processValue(mixed $value, int $rootLevel = 0, int $level = 0): mixed
    {
        if ($value instanceof ServiceClosureArgument) {
            $value->setValues($this->processValue($value->getValues(), 1, 1));
        } elseif ($value instanceof ArgumentInterface) {
            $value->setValues($this->processValue($value->getValues(), $rootLevel, 1 + $level));
        } elseif ($value instanceof Definition) {
            if ($value->isSynthetic() || $value->isAbstract()) {
                return $value;
            }
            $value->setArguments($this->processValue($value->getArguments(), 0));
            $value->setProperties($this->processValue($value->getProperties(), 1));
            $value->setMethodCalls($this->processValue($value->getMethodCalls(), 2));
        } elseif (\is_array($value)) {
            $i = 0;

            foreach ($value as $k => $v) {
                try {
                    if (false !== $i && $k !== $i++) {
                        $i = false;
                    }
                    if ($v !== $processedValue = $this->processValue($v, $rootLevel, 1 + $level)) {
                        $value[$k] = $processedValue;
                    }
                } catch (RuntimeException $e) {
                    if ($rootLevel < $level || ($rootLevel && !$level)) {
                        unset($value[$k]);
                    } elseif ($rootLevel) {
                        throw $e;
                    } else {
                        $value[$k] = null;
                    }
                }
            }

            // Ensure numerically indexed arguments have sequential numeric keys.
            if (false !== $i) {
                $value = array_values($value);
            }
        } elseif ($value instanceof Reference) {
            if ($this->container->hasDefinition($id = (string) $value) ? !$this->container->getDefinition($id)->hasTag('container.excluded') : $this->container->hasAlias($id)) {
                return $value;
            }

            $currentDefinition = $this->container->getDefinition($this->currentId);

            // resolve decorated service behavior depending on decorator service
            if ($currentDefinition->innerServiceId === $id && ContainerInterface::NULL_ON_INVALID_REFERENCE === $currentDefinition->decorationOnInvalid) {
                return null;
            }

            $invalidBehavior = $value->getInvalidBehavior();

            if (ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE === $invalidBehavior && $value instanceof TypedReference && !$this->container->has($id)) {
                $e = new ServiceNotFoundException($id, $this->currentId);

                // since the error message varies by $id and $this->currentId, so should the id of the dummy errored definition
                $this->container->register($id = sprintf('.errored.%s.%s', $this->currentId, $id), $value->getType())
                    ->addError($e->getMessage());

                return new TypedReference($id, $value->getType(), $value->getInvalidBehavior());
            }

            // resolve invalid behavior
            if (ContainerInterface::NULL_ON_INVALID_REFERENCE === $invalidBehavior) {
                $value = null;
            } elseif (ContainerInterface::IGNORE_ON_INVALID_REFERENCE === $invalidBehavior) {
                if (0 < $level || $rootLevel) {
                    throw $this->signalingException;
                }
                $value = null;
            }
        }

        return $value;
    }
}
