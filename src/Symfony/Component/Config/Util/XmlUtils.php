<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Util;

/**
 * XMLUtils is a bunch of utility methods to XML operations.
 *
 * This class contains static methods only and is not meant to be instantiated.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Martin Hasoň <martin.hason@gmail.com>
 */
class XmlUtils
{
    const DIRECTIVE_SEQUENCE = 'create-sequence-if-not-exists';

    /**
     * This class should not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Loads an XML file.
     *
     * @param string          $file             An XML file path
     * @param string|callable $schemaOrCallable An XSD schema file path or callable
     *
     * @return \DOMDocument
     *
     * @throws \InvalidArgumentException When loading of XML file returns error
     */
    public static function loadFile($file, $schemaOrCallable = null)
    {
        $content = @file_get_contents($file);
        if ('' === trim($content)) {
            throw new \InvalidArgumentException(sprintf('File %s does not contain valid XML, it is empty.', $file));
        }

        $internalErrors = libxml_use_internal_errors(true);
        $disableEntities = libxml_disable_entity_loader(true);
        libxml_clear_errors();

        $dom = new \DOMDocument();
        $dom->validateOnParse = true;
        if (!$dom->loadXML($content, LIBXML_NONET | (defined('LIBXML_COMPACT') ? LIBXML_COMPACT : 0))) {
            libxml_disable_entity_loader($disableEntities);

            throw new \InvalidArgumentException(implode("\n", static::getXmlErrors($internalErrors)));
        }

        $dom->normalizeDocument();

        libxml_use_internal_errors($internalErrors);
        libxml_disable_entity_loader($disableEntities);

        foreach ($dom->childNodes as $child) {
            if ($child->nodeType === XML_DOCUMENT_TYPE_NODE) {
                throw new \InvalidArgumentException('Document types are not allowed.');
            }
        }

        if (null !== $schemaOrCallable) {
            $internalErrors = libxml_use_internal_errors(true);
            libxml_clear_errors();

            $e = null;
            if (is_callable($schemaOrCallable)) {
                try {
                    $valid = call_user_func($schemaOrCallable, $dom, $internalErrors);
                } catch (\Exception $e) {
                    $valid = false;
                }
            } elseif (!is_array($schemaOrCallable) && is_file((string) $schemaOrCallable)) {
                $schemaSource = file_get_contents((string) $schemaOrCallable);
                $valid = @$dom->schemaValidateSource($schemaSource);
            } else {
                libxml_use_internal_errors($internalErrors);

                throw new \InvalidArgumentException('The schemaOrCallable argument has to be a valid path to XSD file or callable.');
            }

            if (!$valid) {
                $messages = static::getXmlErrors($internalErrors);
                if (empty($messages)) {
                    $messages = array(sprintf('The XML file "%s" is not valid.', $file));
                }
                throw new \InvalidArgumentException(implode("\n", $messages), 0, $e);
            }
        }

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        return $dom;
    }

    /**
     * Converts a \DomElement object to a PHP array.
     *
     * The following rules applies during the conversion:
     *
     *  * Each tag is converted to a key value or an array
     *    if there is more than one "value"
     *
     *  * The content of a tag is set under a "value" key (<foo>bar</foo>)
     *    if the tag also has some nested tags
     *
     *  * The attributes are converted to keys (<foo foo="bar"/>)
     *
     *  * The nested-tags are converted to keys (<foo><foo>bar</foo></foo>)
     *
     *  * A special tag attribute "create-sequence-if-not-exists" is introduced, which is an equivalent of the sequence directive "-" in YAML file, so the following files are equivalent.
     *
     *    XML:
     *    -------------------------------------------------------
     *    <root>
     *        <parameter create-sequence-if-not-exists="true">
     *            <name>name001</name>
     *            <value>value001</value>
     *        </parameter>
     *    </root>
     *
     *    YAML:
     *    -------------------------------------------------------
     *    parameter:
     *        - name: name001
     *          value: value001
     *
     *    Both files are converted to the php object as follows:
     *
     *    array(
     *       'parameter'=> array(
     *          array(
     *             'name'=>'name001',
     *             'value'=>'value001'
     *          )
     *       )
     *    )
     *
     *    NOTE: the 'create-sequence-if-not-exists' attribute is removed from the php object to avoid introducting extra key.
     *
     *    So if the 'create-sequence-if-not-exists' attribute is used on a tag, its child nodes will be enclosed by a wrapper array.
     *    This is useful when the tag is a 'prototype node' in a configuration tree, BUT ONLY ONE of its concrete record is defined in the XML file.
     *    In which case, no wrapper array is created by default, which will cause subsequent processing error:
     *
     *    XML:
     *    -------------------------------------------------------
     *    <root>
     *        <parameter>
     *            <name>name001</name>
     *            <value>value001</value>
     *        </parameter>
     *    </root>
     *
     *    The php object is as follows:
     *
     *    array(
     *       'parameter'=> array(
     *           'name'=>'name001',
     *           'value'=>'value001'
     *       )
     *    )
     *
     *    NOTE: Assume the 'parameter' node is a prototype node in configuration tree, so each of its child nodes should be a warpper array, which contains real configuration data.
     *    So processing the php object as a prototype node will result in errors.
     *
     *    If more than ONE records are defined in the XML file, the 'create-sequence-if-not-exists' attribute can be omitted, because in which case, the wrapper array will be created anyways.
     *    Using this attribute when multiple records are defined will neither cause any error nor have any effect. So the following files are equivalent:
     *
     *    XML:
     *    -------------------------------------------------------
     *    <root>
     *        <parameter create-sequence-if-not-exists="true">
     *            <name>name001</name>
     *            <value>value001</value>
     *        </parameter>
     *        <parameter>
     *            <name>name002</name>
     *            <value>value002</value>
     *        </parameter>
     *    </root>
     *
     *    XML:
     *    -------------------------------------------------------
     *    <root>
     *        <parameter>
     *            <name>name001</name>
     *            <value>value001</value>
     *        </parameter>
     *        <parameter create-sequence-if-not-exists="true">
     *            <name>name002</name>
     *            <value>value002</value>
     *        </parameter>
     *    </root>
     *
     *    XML:
     *    -------------------------------------------------------
     *    <root>
     *        <parameter create-sequence-if-not-exists="true">
     *            <name>name001</name>
     *            <value>value001</value>
     *        </parameter>
     *        <parameter create-sequence-if-not-exists="true">
     *            <name>name002</name>
     *            <value>value002</value>
     *        </parameter>
     *    </root>
     *
     *    XML:
     *    -------------------------------------------------------
     *    <root>
     *        <parameter>
     *            <name>name001</name>
     *            <value>value001</value>
     *        </parameter>
     *        <parameter>
     *            <name>name002</name>
     *            <value>value002</value>
     *        </parameter>
     *    </root>
     *
     *    YAML:
     *    -------------------------------------------------------
     *    parameter:
     *        - name: name001
     *          value: value001
     *        - name: name002
     *          value: value002
     *
     *    These files are equivalent and will be converted to the following php object:
     *
     *    array(
     *       'parameter'=> array(
     *          array(
     *             'name'=>'name001',
     *             'value'=>'value001'
     *          ),
     *          array(
     *             'name'=>'name002',
     *             'value'=>'value002'
     *          )
     *       )
     *    )
     *
     *
     * @param \DomElement $element     A \DomElement instance
     * @param bool        $checkPrefix Check prefix in an element or an attribute name
     *
     * @return array A PHP array
     */
    public static function convertDomElementToArray(\DomElement $element, $checkPrefix = true)
    {
        $prefix = (string) $element->prefix;
        $empty = true;
        $config = array();
        foreach ($element->attributes as $name => $node) {
            if ($checkPrefix && !in_array((string) $node->prefix, array('', $prefix), true)) {
                continue;
            }
            $config[$name] = static::phpize($node->value);
            $empty = false;
        }

        $nodeValue = false;
        foreach ($element->childNodes as $node) {
            if ($node instanceof \DOMText) {
                if ('' !== trim($node->nodeValue)) {
                    $nodeValue = trim($node->nodeValue);
                    $empty = false;
                }
            } elseif ($checkPrefix && $prefix != (string) $node->prefix) {
                continue;
            } elseif (!$node instanceof \DOMComment) {
                $value = static::convertDomElementToArray($node, $checkPrefix);
                // Check if 'create-sequence-if-not-exists' attribute is used on current child node
                // E.g, <foo create-sequence-if-not-exists="true">...</foo>
                $createSequenceIfNotExists = isset($value[static::DIRECTIVE_SEQUENCE]);
                if ($createSequenceIfNotExists) {
                    // The 'create-sequence-if-not-exists' attribute is useless now, so remove it to avoid extra key
                    unset($value[static::DIRECTIVE_SEQUENCE]);
                }

                $key = $node->localName;
                if (isset($config[$key])) {
                    if (!is_array($config[$key]) || !is_int(key($config[$key]))) {
                        $config[$key] = array($config[$key]);
                    }
                    $config[$key][] = $value;
                } else {
                    // It's the first time to process the child node of <$key> tag under the $element node.
                    // If the 'create-sequence-if-not-exists' attribute is used on $element node, create the wrapper array.
                    // Otherwise, directly append the child node's value to $config[$key].
                    $config[$key] = $createSequenceIfNotExists ? array($value) : $value;
                }

                $empty = false;
            }
        }

        if (false !== $nodeValue) {
            $value = static::phpize($nodeValue);
            if (count($config)) {
                $config['value'] = $value;
            } else {
                $config = $value;
            }
        }

        return !$empty ? $config : null;
    }

    /**
     * Converts an xml value to a PHP type.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public static function phpize($value)
    {
        $value = (string) $value;
        $lowercaseValue = strtolower($value);

        switch (true) {
            case 'null' === $lowercaseValue:
                return;
            case ctype_digit($value):
                $raw = $value;
                $cast = (int) $value;

                return '0' == $value[0] ? octdec($value) : (((string) $raw === (string) $cast) ? $cast : $raw);
            case 'true' === $lowercaseValue:
                return true;
            case 'false' === $lowercaseValue:
                return false;
            case is_numeric($value):
                return '0x' === $value[0].$value[1] ? hexdec($value) : (float) $value;
            case preg_match('/^(-|\+)?[0-9]+(\.[0-9]+)?$/', $value):
                return (float) $value;
            default:
                return $value;
        }
    }

    protected static function getXmlErrors($internalErrors)
    {
        $errors = array();
        foreach (libxml_get_errors() as $error) {
            $errors[] = sprintf('[%s %s] %s (in %s - line %d, column %d)',
                LIBXML_ERR_WARNING == $error->level ? 'WARNING' : 'ERROR',
                $error->code,
                trim($error->message),
                $error->file ?: 'n/a',
                $error->line,
                $error->column
            );
        }

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        return $errors;
    }
}
