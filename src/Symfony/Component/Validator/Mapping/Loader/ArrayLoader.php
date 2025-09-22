<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Mapping\Loader;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Loads validation metadata from a PHP array shaped like the YAML mapping.
 *
 * The accepted structure is the same as in YamlFileLoader, including the
 * optional top-level "namespaces" key for namespace aliases.
 */
class ArrayLoader extends AbstractLoader
{
    /**
     * @param array<class-string, array> $classes
     */
    public function __construct(
        private array $classes,
    ) {
        if (!isset($classes['namespaces'])) {
            return;
        }

        foreach ($classes['namespaces'] as $alias => $namespace) {
            $this->addNamespaceAlias($alias, $namespace);
        }

        unset($classes['namespaces']);

        $this->classes = $classes;
    }

    /**
     * Return the names of the classes mapped by this loader.
     *
     * @return class-string[]
     */
    public function getMappedClasses(): array
    {
        return array_keys($this->classes);
    }

    public function loadClassMetadata(ClassMetadata $metadata): bool
    {
        if (!$classDescription = $this->classes[$metadata->getClassName()] ?? null) {
            return false;
        }

        if (isset($classDescription['group_sequence_provider'])) {
            if (\is_string($classDescription['group_sequence_provider'])) {
                $metadata->setGroupProvider($classDescription['group_sequence_provider']);
            }
            $metadata->setGroupSequenceProvider($classDescription['group_sequence_provider']);
        }

        if (isset($classDescription['group_sequence'])) {
            $metadata->setGroupSequence($classDescription['group_sequence']);
        }

        if (isset($classDescription['constraints']) && \is_array($classDescription['constraints'])) {
            foreach ($this->parseNodes($classDescription['constraints']) as $constraint) {
                $metadata->addConstraint($constraint);
            }
        }

        if (isset($classDescription['properties']) && \is_array($classDescription['properties'])) {
            foreach ($classDescription['properties'] as $property => $constraints) {
                if (null !== $constraints) {
                    foreach ($this->parseNodes($constraints) as $constraint) {
                        $metadata->addPropertyConstraint($property, $constraint);
                    }
                }
            }
        }

        if (isset($classDescription['getters']) && \is_array($classDescription['getters'])) {
            foreach ($classDescription['getters'] as $getter => $constraints) {
                if (null !== $constraints) {
                    foreach ($this->parseNodes($constraints) as $constraint) {
                        $metadata->addGetterConstraint($getter, $constraint);
                    }
                }
            }
        }

        return true;
    }

    /**
     * @return array<array|scalar|Constraint>
     *
     * @internal
     */
    public function parseNodes(array $nodes): array
    {
        $values = [];

        foreach ($nodes as $name => $childNodes) {
            if (is_numeric($name) && \is_array($childNodes) && 1 === \count($childNodes)) {
                $options = current($childNodes);

                if (\is_array($options)) {
                    $options = $this->parseNodes($options);
                }

                if (null !== $options && (!\is_array($options) || array_is_list($options))) {
                    $options = [
                        'value' => $options,
                    ];
                }

                $values[] = $this->newConstraint(key($childNodes), $options);
            } else {
                if (\is_array($childNodes)) {
                    $childNodes = $this->parseNodes($childNodes);
                }

                $values[$name] = $childNodes;
            }
        }

        return $values;
    }
}


