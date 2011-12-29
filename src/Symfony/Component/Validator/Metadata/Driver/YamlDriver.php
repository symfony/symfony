<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Metadata\Driver;

use Symfony\Component\Validator\Metadata\ClassMetadata;
use Symfony\Component\Yaml\Yaml;

class YamlDriver extends AbstractFileDriver
{
    /**
     * {@inheritDoc}
     */
    protected function loadMetadataFromFile(\ReflectionClass $class, $path)
    {
        $classes = Yaml::parse($path);

        // not an array
        if (!is_array($classes)) {
            throw new \InvalidArgumentException(sprintf('The file "%s" must contain a YAML array.', $this->file));
        }

        if (!isset($classes[$class->getName()])) {
            return null;
        }

        $metadata = new ClassMetadata($name = $class->getName());
        $yaml = $classes[$name];

        if (isset($classes['namespaces'])) {
            foreach ($classes['namespaces'] as $prefix => $namespace) {
                $this->namespaces[$prefix] = $namespace;
            }

            unset($classes['namespaces']);
        }

        if (isset($yaml['constraints'])) {
            foreach ($this->parseNodes($yaml['constraints']) as $constraint) {
                $metadata->addConstraint($constraint);
            }
        }

        if (isset($yaml['properties'])) {
            foreach ($yaml['properties'] as $property => $constraints) {
                foreach ($this->parseNodes($constraints) as $constraint) {
                    $metadata->addPropertyConstraint($property, $constraint);
                }
            }
        }

        if (isset($yaml['getters'])) {
            foreach ($yaml['getters'] as $getter => $constraints) {
                foreach ($this->parseNodes($constraints) as $constraint) {
                    $metadata->addGetterConstraint($getter, $constraint);
                }
            }
        }

        return $metadata;
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

    /**
     * @return string
     */
    public function getExtension()
    {
        return 'yml';
    }
}
