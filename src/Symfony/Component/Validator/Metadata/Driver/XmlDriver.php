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
use Symfony\Component\Validator\Exception\MappingException;

class XmlDriver extends AbstractFileDriver
{
    protected function loadMetadataFromFile(\ReflectionClass $class, $path)
    {
        $name = $class->getName();
        $xml = $this->parseFile($path);
        $xml->registerXpathNamespace('symfony', 'http://symfony.com/schema/dic/constraint-mapping');

        $metadata = new ClassMetadata($name = $class->getName());

        if (!$elems = $xml->xpath('//symfony:constraint-mapping/symfony:class[@name="' . $name . '"]')) {
            throw new MappingException(sprintf('Could not find class %s inside XML element.', $name));
        }

        foreach ($xml->xpath('//symfony:namespace') as $namespace) {
            $this->namespaces[(string) $namespace['prefix']] = (string) $namespace;
        }

        $xml = reset($elems);

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

        return $metadata;
    }

    /**
     * Parses a collection of "constraint" XML nodes.
     *
     * @param SimpleXMLElement $nodes The XML nodes
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
     * @param SimpleXMLElement $nodes The XML nodes
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
     * @param SimpleXMLElement $nodes The XML nodes
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
                $value = trim($node);
            }

            $options[(string) $node['name']] = $value;
        }

        return $options;
    }

    /**
     * Parse a XML File.
     *
     * @param string $file Path of file
     * @return SimpleXMLElement
     * @throws MappingException
     */
    protected function parseFile($file)
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        if (!$dom->load($file, defined('LIBXML_COMPACT') ? LIBXML_COMPACT : 0)) {
            throw new MappingException(implode("\n", $this->getXmlErrors()));
        }
        if (!$dom->schemaValidate(__DIR__.'/schema/dic/constraint-mapping/constraint-mapping-1.0.xsd')) {
            throw new MappingException(implode("\n", $this->getXmlErrors()));
        }
        $dom->validateOnParse = true;
        $dom->normalizeDocument();
        libxml_use_internal_errors(false);

        return simplexml_import_dom($dom);
    }

    /**
     * Returns any errors which have happend when parsing the file
     *
     * @return array
     */
    protected function getXmlErrors()
    {
        $errors = array();
        foreach (libxml_get_errors() as $error) {
            $errors[] = sprintf('[%s %s] %s (in %s - line %d, column %d)',
                LIBXML_ERR_WARNING == $error->level ? 'WARNING' : 'ERROR',
                $error->code,
                trim($error->message),
                $error->file ? $error->file : 'n/a',
                $error->line,
                $error->column
            );
        }

        libxml_clear_errors();
        libxml_use_internal_errors(false);

        return $errors;
    }

    /**
     * The file extension for this driver
     *
     * @return string
     */
    public function getExtension()
    {
        return 'xml';
    }
}
