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

use Symfony\Component\Serializer\Exception\BadMethodCallException;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerAwareTrait;

/**
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author John Wards <jwards@whiteoctober.co.uk>
 * @author Fabian Vogler <fabian@equivalence.ch>
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Dany Maillard <danymaillard93b@gmail.com>
 */
class XmlEncoder implements EncoderInterface, DecoderInterface, NormalizationAwareInterface, SerializerAwareInterface
{
    use SerializerAwareTrait;

    public const FORMAT = 'xml';

    public const AS_COLLECTION = 'as_collection';

    /**
     * An array of ignored XML node types while decoding, each one of the DOM Predefined XML_* constants.
     */
    public const DECODER_IGNORED_NODE_TYPES = 'decoder_ignored_node_types';

    /**
     * An array of ignored XML node types while encoding, each one of the DOM Predefined XML_* constants.
     */
    public const ENCODER_IGNORED_NODE_TYPES = 'encoder_ignored_node_types';
    public const ENCODING = 'xml_encoding';
    public const FORMAT_OUTPUT = 'xml_format_output';

    /**
     * A bit field of LIBXML_* constants for loading XML documents.
     */
    public const LOAD_OPTIONS = 'load_options';

    /**
     * A bit field of LIBXML_* constants for saving XML documents.
     */
    public const SAVE_OPTIONS = 'save_options';

    public const REMOVE_EMPTY_TAGS = 'remove_empty_tags';
    public const ROOT_NODE_NAME = 'xml_root_node_name';
    public const STANDALONE = 'xml_standalone';
    public const TYPE_CAST_ATTRIBUTES = 'xml_type_cast_attributes';
    public const VERSION = 'xml_version';
    public const CDATA_WRAPPING = 'cdata_wrapping';

    private array $defaultContext = [
        self::AS_COLLECTION => false,
        self::DECODER_IGNORED_NODE_TYPES => [\XML_PI_NODE, \XML_COMMENT_NODE],
        self::ENCODER_IGNORED_NODE_TYPES => [],
        self::LOAD_OPTIONS => \LIBXML_NONET | \LIBXML_NOBLANKS,
        self::SAVE_OPTIONS => 0,
        self::REMOVE_EMPTY_TAGS => false,
        self::ROOT_NODE_NAME => 'response',
        self::TYPE_CAST_ATTRIBUTES => true,
        self::CDATA_WRAPPING => true,
    ];

    public function __construct(array $defaultContext = [])
    {
        $this->defaultContext = array_merge($this->defaultContext, $defaultContext);
    }

    public function encode(mixed $data, string $format, array $context = []): string
    {
        $encoderIgnoredNodeTypes = $context[self::ENCODER_IGNORED_NODE_TYPES] ?? $this->defaultContext[self::ENCODER_IGNORED_NODE_TYPES];
        $ignorePiNode = \in_array(\XML_PI_NODE, $encoderIgnoredNodeTypes, true);
        if ($data instanceof \DOMDocument) {
            return $data->saveXML($ignorePiNode ? $data->documentElement : null);
        }

        $xmlRootNodeName = $context[self::ROOT_NODE_NAME] ?? $this->defaultContext[self::ROOT_NODE_NAME];

        $dom = $this->createDomDocument($context);

        if (null !== $data && !\is_scalar($data)) {
            $root = $dom->createElement($xmlRootNodeName);
            $dom->appendChild($root);
            $this->buildXml($root, $data, $format, $context, $xmlRootNodeName);
        } else {
            $this->appendNode($dom, $data, $format, $context, $xmlRootNodeName);
        }

        return $dom->saveXML($ignorePiNode ? $dom->documentElement : null, $context[self::SAVE_OPTIONS] ?? $this->defaultContext[self::SAVE_OPTIONS]);
    }

    public function decode(string $data, string $format, array $context = []): mixed
    {
        if ('' === trim($data)) {
            throw new NotEncodableValueException('Invalid XML data, it cannot be empty.');
        }

        $internalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new \DOMDocument();
        $dom->loadXML($data, $context[self::LOAD_OPTIONS] ?? $this->defaultContext[self::LOAD_OPTIONS]);

        libxml_use_internal_errors($internalErrors);

        if ($error = libxml_get_last_error()) {
            libxml_clear_errors();

            throw new NotEncodableValueException($error->message);
        }

        $rootNode = null;
        $decoderIgnoredNodeTypes = $context[self::DECODER_IGNORED_NODE_TYPES] ?? $this->defaultContext[self::DECODER_IGNORED_NODE_TYPES];
        foreach ($dom->childNodes as $child) {
            if (\in_array($child->nodeType, $decoderIgnoredNodeTypes, true)) {
                continue;
            }
            if (\XML_DOCUMENT_TYPE_NODE === $child->nodeType) {
                throw new NotEncodableValueException('Document types are not allowed.');
            }
            if (!$rootNode) {
                $rootNode = $child;
            }
        }

        // todo: throw an exception if the root node name is not correctly configured (bc)

        if ($rootNode->hasChildNodes()) {
            $data = $this->parseXml($rootNode, $context);
            if (\is_array($data)) {
                $data = $this->addXmlNamespaces($data, $rootNode, $dom);
            }

            return $data;
        }

        if (!$rootNode->hasAttributes()) {
            return $rootNode->nodeValue;
        }

        $data = array_merge($this->parseXmlAttributes($rootNode, $context), ['#' => $rootNode->nodeValue]);
        $data = $this->addXmlNamespaces($data, $rootNode, $dom);

        return $data;
    }

    public function supportsEncoding(string $format): bool
    {
        return self::FORMAT === $format;
    }

    public function supportsDecoding(string $format): bool
    {
        return self::FORMAT === $format;
    }

    final protected function appendXMLString(\DOMNode $node, string $val): bool
    {
        if ('' !== $val) {
            $frag = $node->ownerDocument->createDocumentFragment();
            $frag->appendXML($val);
            $node->appendChild($frag);

            return true;
        }

        return false;
    }

    final protected function appendText(\DOMNode $node, string $val): bool
    {
        $nodeText = $node->ownerDocument->createTextNode($val);
        $node->appendChild($nodeText);

        return true;
    }

    final protected function appendCData(\DOMNode $node, string $val): bool
    {
        $nodeText = $node->ownerDocument->createCDATASection($val);
        $node->appendChild($nodeText);

        return true;
    }

    final protected function appendDocumentFragment(\DOMNode $node, \DOMDocumentFragment $fragment): bool
    {
        if ($fragment instanceof \DOMDocumentFragment) {
            $node->appendChild($fragment);

            return true;
        }

        return false;
    }

    final protected function appendComment(\DOMNode $node, string $data): bool
    {
        $node->appendChild($node->ownerDocument->createComment($data));

        return true;
    }

    /**
     * Checks the name is a valid xml element name.
     */
    final protected function isElementNameValid(string $name): bool
    {
        return $name
            && !str_contains($name, ' ')
            && preg_match('#^[\pL_][\pL0-9._:-]*$#ui', $name);
    }

    /**
     * Parse the input DOMNode into an array or a string.
     */
    private function parseXml(\DOMNode $node, array $context = []): array|string
    {
        $data = $this->parseXmlAttributes($node, $context);

        $value = $this->parseXmlValue($node, $context);

        if (!\count($data)) {
            return $value;
        }

        if (!\is_array($value)) {
            $data['#'] = $value;

            return $data;
        }

        if (1 === \count($value) && key($value)) {
            $data[key($value)] = current($value);

            return $data;
        }

        foreach ($value as $key => $val) {
            $data[$key] = $val;
        }

        return $data;
    }

    /**
     * Parse the input DOMNode attributes into an array.
     */
    private function parseXmlAttributes(\DOMNode $node, array $context = []): array
    {
        if (!$node->hasAttributes()) {
            return [];
        }

        $data = [];
        $typeCastAttributes = (bool) ($context[self::TYPE_CAST_ATTRIBUTES] ?? $this->defaultContext[self::TYPE_CAST_ATTRIBUTES]);

        foreach ($node->attributes as $attr) {
            if (!is_numeric($attr->nodeValue) || !$typeCastAttributes || (isset($attr->nodeValue[1]) && '0' === $attr->nodeValue[0] && '.' !== $attr->nodeValue[1])) {
                $data['@'.$attr->nodeName] = $attr->nodeValue;

                continue;
            }

            if (false !== $val = filter_var($attr->nodeValue, \FILTER_VALIDATE_INT)) {
                $data['@'.$attr->nodeName] = $val;

                continue;
            }

            $data['@'.$attr->nodeName] = (float) $attr->nodeValue;
        }

        return $data;
    }

    /**
     * Parse the input DOMNode value (content and children) into an array or a string.
     */
    private function parseXmlValue(\DOMNode $node, array $context = []): array|string
    {
        if (!$node->hasChildNodes()) {
            return $node->nodeValue;
        }

        if (1 === $node->childNodes->length && \in_array($node->firstChild->nodeType, [\XML_TEXT_NODE, \XML_CDATA_SECTION_NODE])) {
            return $node->firstChild->nodeValue;
        }

        $value = [];
        $decoderIgnoredNodeTypes = $context[self::DECODER_IGNORED_NODE_TYPES] ?? $this->defaultContext[self::DECODER_IGNORED_NODE_TYPES];
        foreach ($node->childNodes as $subnode) {
            if (\in_array($subnode->nodeType, $decoderIgnoredNodeTypes, true)) {
                continue;
            }

            $val = $this->parseXml($subnode, $context);

            if ('item' === $subnode->nodeName && isset($val['@key'])) {
                $value[$val['@key']] = $val['#'] ?? $val;
            } else {
                $value[$subnode->nodeName][] = $val;
            }
        }

        $asCollection = $context[self::AS_COLLECTION] ?? $this->defaultContext[self::AS_COLLECTION];
        foreach ($value as $key => $val) {
            if (!$asCollection && \is_array($val) && 1 === \count($val)) {
                $value[$key] = current($val);
            }
        }

        return $value;
    }

    private function addXmlNamespaces(array $data, \DOMNode $node, \DOMDocument $document): array
    {
        $xpath = new \DOMXPath($document);

        foreach ($xpath->query('namespace::*', $node) as $nsNode) {
            $data['@'.$nsNode->nodeName] = $nsNode->nodeValue;
        }

        unset($data['@xmlns:xml']);

        return $data;
    }

    /**
     * Parse the data and convert it to DOMElements.
     *
     * @throws NotEncodableValueException
     */
    private function buildXml(\DOMNode $parentNode, mixed $data, string $format, array $context, string $xmlRootNodeName = null): bool
    {
        $append = true;
        $removeEmptyTags = $context[self::REMOVE_EMPTY_TAGS] ?? $this->defaultContext[self::REMOVE_EMPTY_TAGS] ?? false;
        $encoderIgnoredNodeTypes = $context[self::ENCODER_IGNORED_NODE_TYPES] ?? $this->defaultContext[self::ENCODER_IGNORED_NODE_TYPES];

        if (\is_array($data) || ($data instanceof \Traversable && (null === $this->serializer || !$this->serializer->supportsNormalization($data, $format)))) {
            foreach ($data as $key => $data) {
                // Ah this is the magic @ attribute types.
                if (str_starts_with($key, '@') && $this->isElementNameValid($attributeName = substr($key, 1))) {
                    if (!\is_scalar($data)) {
                        $data = $this->serializer->normalize($data, $format, $context);
                    }
                    if (\is_bool($data)) {
                        $data = (int) $data;
                    }
                    $parentNode->setAttribute($attributeName, $data);
                } elseif ('#' === $key) {
                    $append = $this->selectNodeType($parentNode, $data, $format, $context);
                } elseif ('#comment' === $key) {
                    if (!\in_array(\XML_COMMENT_NODE, $encoderIgnoredNodeTypes, true)) {
                        $append = $this->appendComment($parentNode, $data);
                    }
                } elseif (\is_array($data) && false === is_numeric($key)) {
                    // Is this array fully numeric keys?
                    if (ctype_digit(implode('', array_keys($data)))) {
                        /*
                         * Create nodes to append to $parentNode based on the $key of this array
                         * Produces <xml><item>0</item><item>1</item></xml>
                         * From ["item" => [0,1]];.
                         */
                        foreach ($data as $subData) {
                            $append = $this->appendNode($parentNode, $subData, $format, $context, $key);
                        }
                    } else {
                        $append = $this->appendNode($parentNode, $data, $format, $context, $key);
                    }
                } elseif (is_numeric($key) || !$this->isElementNameValid($key)) {
                    $append = $this->appendNode($parentNode, $data, $format, $context, 'item', $key);
                } elseif (null !== $data || !$removeEmptyTags) {
                    $append = $this->appendNode($parentNode, $data, $format, $context, $key);
                }
            }

            return $append;
        }

        if (\is_object($data)) {
            if (null === $this->serializer) {
                throw new BadMethodCallException(sprintf('The serializer needs to be set to allow "%s()" to be used with object data.', __METHOD__));
            }

            $data = $this->serializer->normalize($data, $format, $context);
            if (null !== $data && !\is_scalar($data)) {
                return $this->buildXml($parentNode, $data, $format, $context, $xmlRootNodeName);
            }

            // top level data object was normalized into a scalar
            if (!$parentNode->parentNode->parentNode) {
                $root = $parentNode->parentNode;
                $root->removeChild($parentNode);

                return $this->appendNode($root, $data, $format, $context, $xmlRootNodeName);
            }

            return $this->appendNode($parentNode, $data, $format, $context, 'data');
        }

        throw new NotEncodableValueException('An unexpected value could not be serialized: '.(!\is_resource($data) ? var_export($data, true) : sprintf('%s resource', get_resource_type($data))));
    }

    /**
     * Selects the type of node to create and appends it to the parent.
     */
    private function appendNode(\DOMNode $parentNode, mixed $data, string $format, array $context, string $nodeName, string $key = null): bool
    {
        $dom = $parentNode instanceof \DOMDocument ? $parentNode : $parentNode->ownerDocument;
        $node = $dom->createElement($nodeName);
        if (null !== $key) {
            $node->setAttribute('key', $key);
        }
        $appendNode = $this->selectNodeType($node, $data, $format, $context);
        // we may have decided not to append this node, either in error or if its $nodeName is not valid
        if ($appendNode) {
            $parentNode->appendChild($node);
        }

        return $appendNode;
    }

    /**
     * Checks if a value contains any characters which would require CDATA wrapping.
     */
    private function needsCdataWrapping(string $val, array $context): bool
    {
        return ($context[self::CDATA_WRAPPING] ?? $this->defaultContext[self::CDATA_WRAPPING]) && preg_match('/[<>&]/', $val);
    }

    /**
     * Tests the value being passed and decide what sort of element to create.
     *
     * @throws NotEncodableValueException
     */
    private function selectNodeType(\DOMNode $node, mixed $val, string $format, array $context): bool
    {
        if (\is_array($val)) {
            return $this->buildXml($node, $val, $format, $context);
        } elseif ($val instanceof \SimpleXMLElement) {
            $child = $node->ownerDocument->importNode(dom_import_simplexml($val), true);
            $node->appendChild($child);
        } elseif ($val instanceof \Traversable) {
            $this->buildXml($node, $val, $format, $context);
        } elseif ($val instanceof \DOMNode) {
            $child = $node->ownerDocument->importNode($val, true);
            $node->appendChild($child);
        } elseif (\is_object($val)) {
            if (null === $this->serializer) {
                throw new BadMethodCallException(sprintf('The serializer needs to be set to allow "%s()" to be used with object data.', __METHOD__));
            }

            return $this->selectNodeType($node, $this->serializer->normalize($val, $format, $context), $format, $context);
        } elseif (is_numeric($val)) {
            return $this->appendText($node, (string) $val);
        } elseif (\is_string($val) && $this->needsCdataWrapping($val, $context)) {
            return $this->appendCData($node, $val);
        } elseif (\is_string($val)) {
            return $this->appendText($node, $val);
        } elseif (\is_bool($val)) {
            return $this->appendText($node, (int) $val);
        }

        return true;
    }

    /**
     * Create a DOM document, taking serializer options into account.
     */
    private function createDomDocument(array $context): \DOMDocument
    {
        $document = new \DOMDocument();

        // Set an attribute on the DOM document specifying, as part of the XML declaration,
        $xmlOptions = [
            // nicely formats output with indentation and extra space
            self::FORMAT_OUTPUT => 'formatOutput',
            // the version number of the document
            self::VERSION => 'xmlVersion',
            // the encoding of the document
            self::ENCODING => 'encoding',
            // whether the document is standalone
            self::STANDALONE => 'xmlStandalone',
        ];
        foreach ($xmlOptions as $xmlOption => $documentProperty) {
            if ($contextOption = $context[$xmlOption] ?? $this->defaultContext[$xmlOption] ?? false) {
                $document->$documentProperty = $contextOption;
            }
        }

        return $document;
    }
}
