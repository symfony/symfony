<?php

namespace Symfony\Component\Serializer\Encoder;

use Symfony\Component\Serializer\SerializerInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
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
class XmlEncoder extends AbstractEncoder implements EncoderInterface
{
    protected $dom;
    protected $format;

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

        if (is_scalar($data)) {
            $this->appendNode($this->dom, $data, 'response');
        } else {
            $root = $this->dom->createElement('response');
            $this->dom->appendChild($root);
            $this->buildXml($root, $data);
        }
        return $this->dom->saveXML();
    }

    /**
     * {@inheritdoc}
     */
    public function decode($data, $format)
    {
        $xml = simplexml_load_string($data);
        if (!$xml->count()) {
            return (string) $xml;
        }
        return $this->parseXml($xml);
    }

    /**
     * Parse the input SimpleXmlElement into an array
     *
     * @param SimpleXmlElement $node xml to parse
     * @return array
     */
    protected function parseXml($node)
    {
        $data = array();
        foreach ($node->children() as $key => $subnode) {
            if ($subnode->count()) {
                $value = $this->parseXml($subnode);
            } else {
                $value = (string) $subnode;
            }
            if ($key === 'item') {
                if (isset($subnode['key'])) {
                    $data[(string)$subnode['key']] = $value;
                } elseif (isset($data['item'])) {
                    $tmp = $data['item'];
                    unset($data['item']);
                    $data[] = $tmp;
                    $data[] = $value;
                }
            } else {
                $data[$key] = $value;
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
    protected function buildXml($parentNode, $data)
    {
        $append = true;

        if (is_array($data) || $data instanceof \Traversable) {
            foreach ($data as $key => $data) {
                if (is_array($data) && false === is_numeric($key)) {
                    $append = $this->appendNode($parentNode, $data, $key);
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
            if (is_scalar($data)) {
                // top level data object is normalized into a scalar
                if (!$parentNode->parentNode->parentNode) {
                    $root = $parentNode->parentNode;
                    $root->removeChild($parentNode);
                    return $this->appendNode($root, $data, 'response');
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
    protected function appendNode($parentNode, $data, $nodeName, $key = null)
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
    protected function selectNodeType($node, $val)
    {
        if (is_array($val)) {
            return $this->buildXml($node, $val);
        } elseif (is_object($val)) {
            return $this->buildXml($node, $this->serializer->normalizeObject($val, $this->format));
        } elseif ($val instanceof \SimpleXMLElement) {
            $child = $this->dom->importNode(dom_import_simplexml($val), true);
            $node->appendChild($child);
        } elseif ($val instanceof \Traversable) {
            $this->buildXml($node, $val);
        } elseif (is_numeric($val)) {
            return $this->appendText($node, (string) $val);
        } elseif (is_string($val)) {
            return $this->appendCData($node, $val);
        } elseif (is_bool($val)) {
            return $this->appendText($node, (int) $val);
        } elseif ($val instanceof \DOMNode){
            $child = $this->dom->importNode($val, true);
            $node->appendChild($child);
        }

        return true;
    }

    /**
     * @param DOMNode $node
     * @param string $val
     * @return Boolean
     */
    protected function appendXMLString($node, $val)
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
    protected function appendText($node, $val)
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
    protected function appendCData($node, $val)
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
    protected function appendDocumentFragment($node, $fragment)
    {
        if ($fragment instanceof DOMDocumentFragment) {
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
    protected function isElementNameValid($name)
    {
        return $name && strpos($name, ' ') === false && preg_match('|^\w+$|', $name);
    }
}