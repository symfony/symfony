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
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser as YamlParser;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads validation metadata from a YAML file.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class YamlFileLoader extends FileLoader
{
    protected array $classes;

    public function __construct(string $file)
    {
        $this->file = $file;
    }

    /**
     * Caches the used YAML parser.
     */
    private YamlParser $yamlParser;

    public function loadClassMetadata(ClassMetadata $metadata): bool
    {
        if (!isset($this->classes)) {
            $this->loadClassesFromYaml();
        }

        if (isset($this->classes[$metadata->getClassName()])) {
            $classDescription = $this->classes[$metadata->getClassName()];

            $this->loadClassMetadataFromYaml($metadata, $classDescription);

            return true;
        }

        return false;
    }

    /**
     * Return the names of the classes mapped in this file.
     *
     * @return string[]
     */
    public function getMappedClasses(): array
    {
        if (!isset($this->classes)) {
            $this->loadClassesFromYaml();
        }

        return array_keys($this->classes);
    }

    /**
     * Parses a collection of YAML nodes.
     *
     * @param array $nodes The YAML nodes
     *
     * @return array<array|scalar|Constraint>
     */
    protected function parseNodes(array $nodes): array
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

    /**
     * Loads the YAML class descriptions from the given file.
     *
     * @throws \InvalidArgumentException If the file could not be loaded or did
     *                                   not contain a YAML array
     */
    private function parseFile(string $path): array
    {
        try {
            $classes = $this->yamlParser->parseFile($path, Yaml::PARSE_CONSTANT);
        } catch (ParseException $e) {
            throw new \InvalidArgumentException(\sprintf('The file "%s" does not contain valid YAML: ', $path).$e->getMessage(), 0, $e);
        }

        // empty file
        if (null === $classes) {
            return [];
        }

        // not an array
        if (!\is_array($classes)) {
            throw new \InvalidArgumentException(\sprintf('The file "%s" must contain a YAML array.', $this->file));
        }

        return $classes;
    }

    private function loadClassesFromYaml(): void
    {
        parent::__construct($this->file);

        $this->yamlParser ??= new YamlParser();
        $this->classes = $this->parseFile($this->file);

        if (isset($this->classes['namespaces'])) {
            foreach ($this->classes['namespaces'] as $alias => $namespace) {
                $this->addNamespaceAlias($alias, $namespace);
            }

            unset($this->classes['namespaces']);
        }
    }

    private function loadClassMetadataFromYaml(ClassMetadata $metadata, array $classDescription): void
    {
        if (isset($classDescription['group_sequence_provider'])) {
            if (\is_string($classDescription['group_sequence_provider'])) {
                $metadata->setGroupProvider($classDescription['group_sequence_provider']);
            }
            $metadata->setGroupSequenceProvider(
                (bool) $classDescription['group_sequence_provider']
            );
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
    }
}
