<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Encoder;

use Symfony\Component\Serializer\Exception\UnexpectedValueException;

/**
 * Encodes XML data
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author John Wards <jwards@whiteoctober.co.uk>
 * @author Fabian Vogler <fabian@equivalence.ch>
 */
class XmlEncoder extends SerializerAwareEncoder implements EncoderInterface, DecoderInterface, NormalizationAwareInterface
{
    private $dom;
    private $format;
    private $rootNodeName = 'response';

    /**
     * Construct new XmlEncoder and allow to change the root node element name.
     *
     * @param string $rootNodeName
     */
    public function __construct($rootNodeName = 'response')
    {
        $this->rootNodeName = $rootNodeName;
    }

    /**
     * {@inheritdoc}
     */
    public function encode($data, $format, array $context = array())
    {
        if ($data instanceof \DOMDocument) {
            return $data->saveXML();
        }

        $xmlRootNodeName = $this->resolveXmlRootName($context);

        $this->dom = new \DOMDocument();
        $this->format = $format;

        if (null !== $data && !is_scalar($data)) {
            $root = $this->dom->createElement($xmlRootNodeName);
            $this->dom->appendChild($root);
            $this->buildXml($root, $data, $xmlRootNodeName);
        } else {
            $this->appendNode($this->dom, $data, $xmlRootNodeName);
        }

        return $this->dom->saveXML();
    }

    /**
     * {@inheritdoc}
     */
    public function decode($data, $format, array $context = array())
    {
        if ('' === trim($data)) {
            throw new UnexpectedValueException('Invalid XML data, it can not be empty.');
        }

        $internalErrors = libxml_use_internal_errors(true);
        $disableEntities = libxml_disable_entity_loader(true);
        libxml_clear_errors();

        $dom = new \DOMDocument();
        $dom->loadXML($data, LIBXML_NONET);

        libxml_use_internal_errors($internalErrors);
        libxml_disable_entity_loader($disableEntities);

        if ($error = libxml_get_last_error()) {
            libxml_clear_errors();

            throw new UnexpectedValueException($error->message);
        }

        foreach ($dom->childNodes as $child) {
            if ($child->nodeType === XML_DOCUMENT_TYPE_NODE) {
                throw new UnexpectedValueException('Document types are not allowed.');
            }
        }

        $xml = simplexml_import_dom($dom);

        if ($error = libxml_get_last_error()) {
            throw new UnexpectedValueException($error->message);
        }

        if (!$xml->count()) {
            if (!$xml->attributes()) {
                return (string) $xml;
            }
            $data = array();
            foreach ($xml->attributes() as $attrkey => $attr) {
                $data['@'.$attrkey] = (string) $attr;
            }
            $data['#'] = (string) $xml;

            return $data;
        }

        return $this->parseXml($xml);
    }

    /**
      * Checks whether the serializer can encode to given format
      *
      * @param string $format format name
      *
      * @return bool
      */
     public function supportsEncoding($format)
     {
         return 'xml' === $format;
     }

     /**
      * Checks whether the serializer can decode from given format
      *
      * @param string $format format name
      *
      * @return bool
      */
     public function supportsDecoding($format)
     {
         return 'xml' === $format;
     }

    /**
     * Sets the root node name
     *
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
     * @param \DOMNode $node
     * @param string   $val
     *
     * @return bool
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
     * @param string  $val
     *
     * @return bool
     */
    final protected function appendText($node, $val)
    {
        $nodeText = $this->dom->createTextNode($val);
        $node->appendChild($nodeText);

        return true;
    }

    /**
     * @param DOMNode $node
     * @param string  $val
     *
     * @return bool
     */
    final protected function appendCData($node, $val)
    {
        $nodeText = $this->dom->createCDATASection($val);
        $node->appendChild($nodeText);

        return true;
    }

    /**
     * @param DOMNode             $node
     * @param DOMDocumentFragment $fragment
     *
     * @return bool
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
     * Checks the name is a valid xml element name
     *
     * @param string $name
     *
     * @return bool
     */
    final protected function isElementNameValid($name)
    {
        return $name &&
            false === strpos($name, ' ') &&
            preg_match('#^[\pL_][\pL0-9._-]*$#ui', $name);
    }

    /**
     * Parse the input SimpleXmlElement into an array.
     *
     * @param SimpleXmlElement $node xml to parse
     *
     * @return array
     */
    private function parseXml($node)
    {
        $data = array();
        if ($node->attributes()) {
            foreach ($node->attributes() as $attrkey => $attr) {
                $data['@'.$attrkey] = (string) $attr;
            }
        }
        foreach ($node->children() as $key => $subnode) {
            if ($subnode->count()) {
                $value = $this->parseXml($subnode);
            } elseif ($subnode->attributes()) {
                $value = array();
                foreach ($subnode->attributes() as $attrkey => $attr) {
                    $value['@'.$attrkey] = (string) $attr;
                }
                $value['#'] = (string) $subnode;
            } else {
                $value = (string) $subnode;
            }

            if ($key === 'item') {
                if (isset($value['@key'])) {
                    if (isset($value['#'])) {
                        $data[(string) $value['@key']] = $value['#'];
                    } else {
                        $data[(string) $value['@key']] = $value;
                    }
                } else {
                    $data['item'][] = $value;
                }
            } elseif (array_key_exists($key, $data) || $key == "entry") {
                if ((false === is_array($data[$key]))  || (false === isset($data[$key][0]))) {
                    $data[$key] = array($data[$key]);
                }
                $data[$key][] = $value;
            } else {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Parse the data and convert it to DOMElements
     *
     * @param DOMNode      $parentNode
     * @param array|object $data            data
     * @param string       $xmlRootNodeName
     *
     * @return bool
     *
     * @throws UnexpectedValueException
     */
    private function buildXml($parentNode, $data, $xmlRootNodeName = null)
    {
        $append = true;

        if (is_array($data) || $data instanceof \Traversable) {
            foreach ($data as $key => $data) {
                //Ah this is the magic @ attribute types.
                if (0 === strpos($key, "@") && is_scalar($data) && $this->isElementNameValid($attributeName = substr($key, 1))) {
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
            $data = $this->serializer->normalize($data, $this->format);
            if (null !== $data && !is_scalar($data)) {
                return $this->buildXml($parentNode, $data, $xmlRootNodeName);
            }

            // top level data object was normalized into a scalar
            if (!$parentNode->parentNode->parentNode) {
                $root = $parentNode->parentNode;
                $root->removeChild($parentNode);

                return $this->appendNode($root, $data, $xmlRootNodeName);
            }

            return $this->appendNode($parentNode, $data, 'data');
        }

        throw new UnexpectedValueException(sprintf('An unexpected value could not be serialized: %s', var_export($data, true)));
    }

    /**
     * Selects the type of node to create and appends it to the parent.
     *
     * @param DOMNode      $parentNode
     * @param array|object $data
     * @param string       $nodeName
     * @param string       $key
     *
     * @return bool
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
     * Checks if a value contains any characters which would require CDATA wrapping.
     *
     * @param string $val
     *
     * @return bool
     */
    private function needsCdataWrapping($val)
    {
        return preg_match('/[<>&]/', $val);
    }

    /**
     * Tests the value being passed and decide what sort of element to create
     *
     * @param DOMNode $node
     * @param mixed   $val
     *
     * @return bool
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
            return $this->buildXml($node, $this->serializer->normalize($val, $this->format));
        } elseif (is_numeric($val)) {
            return $this->appendText($node, (string) $val);
        } elseif (is_string($val) && $this->needsCdataWrapping($val)) {
            return $this->appendCData($node, $val);
        } elseif (is_string($val)) {
            return $this->appendText($node, $val);
        } elseif (is_bool($val)) {
            return $this->appendText($node, (int) $val);
        } elseif ($val instanceof \DOMNode) {
            $child = $this->dom->importNode($val, true);
            $node->appendChild($child);
        }

        return true;
    }

    /**
     * Get real XML root node name, taking serializer options into account.
     */
    private function resolveXmlRootName(array $context = array())
    {
        return isset($context['xml_root_node_name'])
            ? $context['xml_root_node_name']
            : $this->rootNodeName;
    }
}
