<?php

namespace Symfony\Component\Serializer\Encoder;

use Symfony\Component\Serializer\SerializerInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Encodes XML data
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author John Wards <jwards@whiteoctober.co.uk>
 * @author Fabian Vogler <fabian@equivalence.ch>
 */
class XmlEncoder extends AbstractEncoder
{
    private $dom;
    private $format;
    private $rootNodeName = 'response';

    /**
     * {@inheritdoc}
     */
    public function encode($data, $format)
    {
        if ($data instanceof \DOMDocument) {
            return $data->saveXML();
        }

        $this->dom = new \DOMDocument();
        $this->format = $format;

        if ($this->serializer->isStructuredType($data)) {
            $root = $this->dom->createElement($this->rootNodeName);
            $this->dom->appendChild($root);
            $this->buildXml($root, $data);
        } else {
            $this->appendNode($this->dom, $data, $this->rootNodeName);
        }
        return $this->dom->saveXML();
    }

    /**
     * {@inheritdoc}
     */
    public function decode($data, $format)
    {
        $xml = \DOMDocument::loadXML($data);
        if (!$xml->documentElement->hasChildNodes()) {
            return "";
        } elseif ($xml->documentElement->childNodes->length == 1 && $xml->documentElement->firstChild instanceof \DOMText) {
            return trim((string)$xml->documentElement->firstChild->wholeText);
        }
        return $this->parseXml($xml->documentElement);
    }

    /**
     * Sets the root node name
     * @param string $name root node name
     */
    public function setRootNodeName($name)
    {
        $this->rootNodeName = $name;
    }

    /**
     * Returns the root node name
     * @return string
     */
    public function getRootNodeName()
    {
        return $this->rootNodeName;
    }

    /**
     * @param DOMNode $node
     * @param string $val
     * @return Boolean
     */
    final protected function appendXMLString($node, $val)
    {
        if (strlen($val) > 0) {
            $frag = $this->dom->createDocumentFragment();
            $frag->appendXML($val);
            $node->appendChild($frag);
            return true;
        }

        return false;
    }

    /**
     * @param DOMNode $node
     * @param string $val
     * @return Boolean
     */
    final protected function appendText($node, $val)
    {
        $nodeText = $this->dom->createTextNode($val);
        $node->appendChild($nodeText);

        return true;
    }

    /**
     * @param DOMNode $node
     * @param string $val
     * @return Boolean
     */
    final protected function appendCData($node, $val)
    {
        $nodeText = $this->dom->createCDATASection($val);
        $node->appendChild($nodeText);

        return true;
    }

    /**
     * @param DOMNode $node
     * @param DOMDocumentFragment $fragment
     * @return Boolean
     */
    final protected function appendDocumentFragment($node, $fragment)
    {
        if ($fragment instanceof \DOMDocumentFragment) {
            $node->appendChild($fragment);
            return true;
        }

        return false;
    }

    /**
     * Checks the name is avalid xml element name
     * @param string $name
     * @return Boolean
     */
    final protected function isElementNameValid($name)
    {
        return $name &&
            false === strpos($name, ' ') &&
            preg_match('#^[\pL_][\pL0-9._-]*$#ui', $name);
    }

    /**
     * Parse the input DOMElement into an array
     *
     * @param DOMElement $node xml to parse
     * @return array
     */
    private function parseXml($node)
    {
        $data = array();
        foreach ($node->childNodes as $subnode) {
            //When xml is "beautiful" (with tabs and newlines...), tabs and newline are considered as text but we do not want them
            if ($subnode instanceof DOMText && trim($subnode->wholeText) === "") {
                continue;
            }
            if (!$subnode->hasChildNodes()) {
                $value = "";
            } elseif ($subnode->childNodes->length == 1 && $subnode->firstChild instanceof \DOMText) {
                $value = trim((string)$subnode->firstChild->wholeText);
            } else {
                $value = $this->parseXml($subnode);
            }
            
            if ($subnode->hasAttributes()) {
                if (is_string($value) && $value !== "") {
                    $value = array('#' => $value);
                } elseif (is_string($value)) {
                    $value = array();
                }
                foreach($subnode->attributes as $attrKey => $attr) {
                    $value['@'.$attrKey] = (string) $attr->value;
                }
            }
            
            if ($subnode->tagName === 'item') {
                if (isset($value['@key'])) {
                    $key = $value['@key'];
                    $tmp = $value['#'];
                    unset($value['@key']);
                    unset($value['#']);
                    if (!empty($value)) {
                        $data[$key] = array_merge(array('#' => $tmp), $value);
                    } else {
                        $data[$key] = $tmp;
                    }
                } elseif (isset($data['item'])) {
                    $tmp = $data['item'];
                    unset($data['item']);
                    $data[] = $tmp;
                    $data[] = $value;
                }
            } elseif (key_exists($subnode->tagName, $data)) {
                if ((false === is_array($data[$subnode->tagName])) || (false === isset($data[$subnode->tagName][0]))) {
                    $data[$subnode->tagName] = array($data[$subnode->tagName]);
                }
                $data[$subnode->tagName][] = $value;
            } else {
                $data[$subnode->tagName] = $value;
            }
        }
        return $data;
    }

    /**
     * Parse the data and convert it to DOMElements
     *
     * @param DOMNode $parentNode
     * @param array|object $data data
     * @return bool
     */
    private function buildXml($parentNode, $data)
    {
        $append = true;

        if (is_array($data) || $data instanceof \Traversable) {
            foreach ($data as $key => $data) {
                //Ah this is the magic @ attribute types.
                if (0 === strpos($key, "@") && is_scalar($data) && $this->isElementNameValid($attributeName = substr($key,1))) {
                    $parentNode->setAttribute($attributeName, $data);
                } elseif ($key === '#') {
                    $append = $this->selectNodeType($parentNode, $data);
                } elseif (is_array($data) && false === is_numeric($key)) {
                    /**
                    * Is this array fully numeric keys?
                    */
                    if (ctype_digit(implode('', array_keys($data)))) {
                        /**
                        * Create nodes to append to $parentNode based on the $key of this array
                        * Produces <xml><item>0</item><item>1</item></xml>
                        * From array("item" => array(0,1));
                        */
                        foreach ($data as $subData) {
                            $append = $this->appendNode($parentNode, $subData, $key);
                        }
                    } else {
                        $append = $this->appendNode($parentNode, $data, $key);
                    }
                } elseif (is_numeric($key) || !$this->isElementNameValid($key)) {
                    $append = $this->appendNode($parentNode, $data, "item", $key);
                } else {
                    $append = $this->appendNode($parentNode, $data, $key);
                }
            }
            return $append;
        }
        if (is_object($data)) {
            $data = $this->serializer->normalizeObject($data, $this->format);
            if (!$this->serializer->isStructuredType($data)) {
                // top level data object is normalized into a scalar
                if (!$parentNode->parentNode->parentNode) {
                    $root = $parentNode->parentNode;
                    $root->removeChild($parentNode);
                    return $this->appendNode($root, $data, $this->rootNodeName);
                }
                return $this->appendNode($parentNode, $data, 'data');
            }
            return $this->buildXml($parentNode, $data);
        }
        throw new \UnexpectedValueException('An unexpected value could not be serialized: '.var_export($data, true));
    }

    /**
     * Selects the type of node to create and appends it to the parent.
     *
     * @param  $parentNode
     * @param  $data
     * @param  $nodename
     * @return void
     */
    private function appendNode($parentNode, $data, $nodeName, $key = null)
    {
        $node = $this->dom->createElement($nodeName);
        if (null !== $key) {
            $node->setAttribute('key', $key);
        }
        $appendNode = $this->selectNodeType($node, $data);
        // we may have decided not to append this node, either in error or if its $nodeName is not valid
        if ($appendNode) {
            $parentNode->appendChild($node);
        }
        return $appendNode;
    }

    /**
     * Tests the value being passed and decide what sort of element to create
     *
     * @param DOMNode $node
     * @param mixed $val
     * @return Boolean
     */
    private function selectNodeType($node, $val)
    {
        if (is_array($val)) {
            return $this->buildXml($node, $val);
        } elseif ($val instanceof \SimpleXMLElement) {
            $child = $this->dom->importNode(dom_import_simplexml($val), true);
            $node->appendChild($child);
        } elseif ($val instanceof \Traversable) {
            $this->buildXml($node, $val);
        } elseif (is_object($val)) {
            return $this->buildXml($node, $this->serializer->normalizeObject($val, $this->format));
        } elseif (is_numeric($val)) {
            return $this->appendText($node, (string) $val);
        } elseif (is_string($val)) {
            return $this->appendCData($node, $val);
        } elseif (is_bool($val)) {
            return $this->appendText($node, (int) $val);
        } elseif ($val instanceof \DOMNode) {
            $child = $this->dom->importNode($val, true);
            $node->appendChild($child);
        }

        return true;
    }
}
