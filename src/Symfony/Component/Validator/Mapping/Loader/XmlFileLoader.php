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

use Symfony\Component\Validator\Exception\MappingException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Config\Util\XmlUtils;

class XmlFileLoader extends FileLoader
{
    /**
     * An array of SimpleXMLElement instances.
     *
     * @var \SimpleXMLElement[]
     */
    protected $classes = null;

    /**
     * {@inheritDoc}
     */
    public function loadClassMetadata(ClassMetadata $metadata)
    {
        if (null === $this->classes) {
            $this->classes = array();
            $xml = $this->parseFile($this->file);

            foreach ($xml->namespace as $namespace) {
                $this->addNamespaceAlias((string) $namespace['prefix'], trim((string) $namespace));
            }

            foreach ($xml->class as $class) {
                $this->classes[(string) $class['name']] = $class;
            }
        }

        if (isset($this->classes[$metadata->getClassName()])) {
            $xml = $this->classes[$metadata->getClassName()];

            foreach ($xml->{'group-sequence-provider'} as $provider) {
                $metadata->setGroupSequenceProvider(true);
            }

            foreach ($xml->{'group-sequence'} as $groupSequence) {
                if (count($groupSequence->value) > 0) {
                    $metadata->setGroupSequence($this->parseValues($groupSequence[0]->value));
                }
            }

            foreach ($this->parseConstraints($xml->constraint) as $constraint) {
                $metadata->addConstraint($constraint);
            }

            foreach ($xml->property as $property) {
                foreach ($this->parseConstraints($property->constraint) as $constraint) {
                    $metadata->addPropertyConstraint((string) $property['name'], $constraint);
                }
            }

            foreach ($xml->getter as $getter) {
                foreach ($this->parseConstraints($getter->constraint) as $constraint) {
                    $metadata->addGetterConstraint((string) $getter['property'], $constraint);
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Parses a collection of "constraint" XML nodes.
     *
     * @param \SimpleXMLElement $nodes The XML nodes
     *
     * @return array The Constraint instances
     */
    protected function parseConstraints(\SimpleXMLElement $nodes)
    {
        $constraints = array();

        foreach ($nodes as $node) {
            if (count($node) > 0) {
                if (count($node->value) > 0) {
                    $options = $this->parseValues($node->value);
                } elseif (count($node->constraint) > 0) {
                    $options = $this->parseConstraints($node->constraint);
                } elseif (count($node->option) > 0) {
                    $options = $this->parseOptions($node->option);
                } else {
                    $options = array();
                }
            } elseif (strlen((string) $node) > 0) {
                $options = trim($node);
            } else {
                $options = null;
            }

            $constraints[] = $this->newConstraint($node['name'], $options);
        }

        return $constraints;
    }

    /**
     * Parses a collection of "value" XML nodes.
     *
     * @param \SimpleXMLElement $nodes The XML nodes
     *
     * @return array The values
     */
    protected function parseValues(\SimpleXMLElement $nodes)
    {
        $values = array();

        foreach ($nodes as $node) {
            if (count($node) > 0) {
                if (count($node->value) > 0) {
                    $value = $this->parseValues($node->value);
                } elseif (count($node->constraint) > 0) {
                    $value = $this->parseConstraints($node->constraint);
                } else {
                    $value = array();
                }
            } else {
                $value = trim($node);
            }

            if (isset($node['key'])) {
                $values[(string) $node['key']] = $value;
            } else {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * Parses a collection of "option" XML nodes.
     *
     * @param \SimpleXMLElement $nodes The XML nodes
     *
     * @return array The options
     */
    protected function parseOptions(\SimpleXMLElement $nodes)
    {
        $options = array();

        foreach ($nodes as $node) {
            if (count($node) > 0) {
                if (count($node->value) > 0) {
                    $value = $this->parseValues($node->value);
                } elseif (count($node->constraint) > 0) {
                    $value = $this->parseConstraints($node->constraint);
                } else {
                    $value = array();
                }
            } else {
                $value = XmlUtils::phpize($node);
                if (is_string($value)) {
                    $value = trim($value);
                }
            }

            $options[(string) $node['name']] = $value;
        }

        return $options;
    }

    /**
     * Parse a XML File.
     *
     * @param string $file Path of file
     *
     * @return \SimpleXMLElement
     *
     * @throws MappingException
     */
    protected function parseFile($file)
    {
        try {
            $dom = XmlUtils::loadFile($file, __DIR__.'/schema/dic/constraint-mapping/constraint-mapping-1.0.xsd');
        } catch (\Exception $e) {
            throw new MappingException($e->getMessage(), $e->getCode(), $e);
        }

        return simplexml_import_dom($dom);
    }
}
