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

use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Yaml\Yaml;

class YamlFileLoader extends FileLoader
{
    /**
     * An array of YAML class descriptions
     *
     * @var array
     */
    protected $classes = null;

    /**
     * {@inheritDoc}
     */
    public function loadClassMetadata(ClassMetadata $metadata)
    {
        if (null === $this->classes) {
            $this->classes = Yaml::parse($this->file);

            // empty file
            if (null === $this->classes) {
                return false;
            }

            // not an array
            if (!is_array($this->classes)) {
                throw new \InvalidArgumentException(sprintf('The file "%s" must contain a YAML array.', $this->file));
            }

            if (isset($this->classes['namespaces'])) {
                foreach ($this->classes['namespaces'] as $alias => $namespace) {
                    $this->addNamespaceAlias($alias, $namespace);
                }

                unset($this->classes['namespaces']);
            }
        }

        // TODO validation

        if (isset($this->classes[$metadata->getClassName()])) {
            $yaml = $this->classes[$metadata->getClassName()];

            if (isset($yaml['group_sequence_provider'])) {
                $metadata->setGroupSequenceProvider((bool) $yaml['group_sequence_provider']);
            }

            if (isset($yaml['group_sequence'])) {
                $metadata->setGroupSequence($yaml['group_sequence']);
            }

            if (isset($yaml['constraints']) && is_array($yaml['constraints'])) {
                foreach ($this->parseNodes($yaml['constraints']) as $constraint) {
                    $metadata->addConstraint($constraint);
                }
            }

            if (isset($yaml['properties']) && is_array($yaml['properties'])) {
                foreach ($yaml['properties'] as $property => $constraints) {
                    if (null !== $constraints) {
                        foreach ($this->parseNodes($constraints) as $constraint) {
                            $metadata->addPropertyConstraint($property, $constraint);
                        }
                    }
                }
            }

            if (isset($yaml['getters']) && is_array($yaml['getters'])) {
                foreach ($yaml['getters'] as $getter => $constraints) {
                    if (null !== $constraints) {
                        foreach ($this->parseNodes($constraints) as $constraint) {
                            $metadata->addGetterConstraint($getter, $constraint);
                        }
                    }
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Parses a collection of YAML nodes
     *
     * @param array $nodes The YAML nodes
     *
     * @return array An array of values or Constraint instances
     */
    protected function parseNodes(array $nodes)
    {
        $values = array();

        foreach ($nodes as $name => $childNodes) {
            if (is_numeric($name) && is_array($childNodes) && count($childNodes) == 1) {
                $options = current($childNodes);

                if (is_array($options)) {
                    $options = $this->parseNodes($options);
                }

                $values[] = $this->newConstraint(key($childNodes), $options);
            } else {
                if (is_array($childNodes)) {
                    $childNodes = $this->parseNodes($childNodes);
                }

                $values[$name] = $childNodes;
            }
        }

        return $values;
    }
}
