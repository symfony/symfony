<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Caster;

/**
 * Casts DOM related classes to array representation.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DOMCaster
{
    private static $errorCodes = array(
        DOM_PHP_ERR => 'DOM_PHP_ERR',
        DOM_INDEX_SIZE_ERR => 'DOM_INDEX_SIZE_ERR',
        DOMSTRING_SIZE_ERR => 'DOMSTRING_SIZE_ERR',
        DOM_HIERARCHY_REQUEST_ERR => 'DOM_HIERARCHY_REQUEST_ERR',
        DOM_WRONG_DOCUMENT_ERR => 'DOM_WRONG_DOCUMENT_ERR',
        DOM_INVALID_CHARACTER_ERR => 'DOM_INVALID_CHARACTER_ERR',
        DOM_NO_DATA_ALLOWED_ERR => 'DOM_NO_DATA_ALLOWED_ERR',
        DOM_NO_MODIFICATION_ALLOWED_ERR => 'DOM_NO_MODIFICATION_ALLOWED_ERR',
        DOM_NOT_FOUND_ERR => 'DOM_NOT_FOUND_ERR',
        DOM_NOT_SUPPORTED_ERR => 'DOM_NOT_SUPPORTED_ERR',
        DOM_INUSE_ATTRIBUTE_ERR => 'DOM_INUSE_ATTRIBUTE_ERR',
        DOM_INVALID_STATE_ERR => 'DOM_INVALID_STATE_ERR',
        DOM_SYNTAX_ERR => 'DOM_SYNTAX_ERR',
        DOM_INVALID_MODIFICATION_ERR => 'DOM_INVALID_MODIFICATION_ERR',
        DOM_NAMESPACE_ERR => 'DOM_NAMESPACE_ERR',
        DOM_INVALID_ACCESS_ERR => 'DOM_INVALID_ACCESS_ERR',
        DOM_VALIDATION_ERR => 'DOM_VALIDATION_ERR',
    );

    public static function castException(\DOMException $e, array $a, $isNested, &$cut)
    {
        if (isset($a["\0*\0code"], self::$errorCodes[$a["\0*\0code"]])) {
            $a["\0*\0code"] .= ' ('.self::$errorCodes[$a["\0*\0code"]].')';
        }

        return $a;
    }

    public static function castLength($dom, array $a, $isNested, &$cut)
    {
        $a += array(
            'length' => $dom->length,
        );

        return $a;
    }

    public static function castImplementation($dom, array $a, $isNested, &$cut)
    {
        $a += array(
            "\0~\0Core" => '1.0',
            "\0~\0XML" => '2.0',
        );

        return $a;
    }

    public static function castNode(\DOMNode $dom, array $a, $isNested, &$cut)
    {
        // Commented lines denote properties that exist but are better not dumped for clarity.

        $a += array(
            'nodeName' => $dom->nodeName,
            //'nodeValue' => $dom->nodeValue,
            'nodeType' => $dom->nodeType,
            //'parentNode' => $dom->parentNode,
            'childNodes' => $dom->childNodes,
            //'firstChild' => $dom->firstChild,
            //'lastChild' => $dom->lastChild,
            //'previousSibling' => $dom->previousSibling,
            //'nextSibling' => $dom->nextSibling,
            'attributes' => $dom->attributes,
            //'ownerDocument' => $dom->ownerDocument,
            'namespaceURI' => $dom->namespaceURI,
            'prefix' => $dom->prefix,
            'localName' => $dom->localName,
            'baseURI' => $dom->baseURI,
            //'textContent' => $dom->textContent,
        );
        $cut += 8;

        return $a;
    }

    public static function castNameSpaceNode(\DOMNameSpaceNode $dom, array $a, $isNested, &$cut)
    {
        // Commented lines denote properties that exist but are better not dumped for clarity.

        $a += array(
            'nodeName' => $dom->nodeName,
            //'nodeValue' => $dom->nodeValue,
            'nodeType' => $dom->nodeType,
            'prefix' => $dom->prefix,
            'localName' => $dom->localName,
            'namespaceURI' => $dom->namespaceURI,
            //'ownerDocument' => $dom->ownerDocument,
            //'parentNode' => $dom->parentNode,
        );
        $cut += 3;

        return $a;
    }

    public static function castDocument(\DOMDocument $dom, array $a, $isNested, &$cut)
    {
        $formatOutput = $dom->formatOutput;
        $dom->formatOutput = true;

        $a += array(
            'doctype' => $dom->doctype,
            'implementation' => $dom->implementation,
            'documentElement' => $dom->documentElement,
            'actualEncoding' => $dom->actualEncoding,
            'encoding' => $dom->encoding,
            'xmlEncoding' => $dom->xmlEncoding,
            'standalone' => $dom->standalone,
            'xmlStandalone' => $dom->xmlStandalone,
            'version' => $dom->version,
            'xmlVersion' => $dom->xmlVersion,
            'strictErrorChecking' => $dom->strictErrorChecking,
            'documentURI' => $dom->documentURI,
            'config' => $dom->config,
            'formatOutput' => $formatOutput,
            'validateOnParse' => $dom->validateOnParse,
            'resolveExternals' => $dom->resolveExternals,
            'preserveWhiteSpace' => $dom->preserveWhiteSpace,
            'recover' => $dom->recover,
            'substituteEntities' => $dom->substituteEntities,
            "\0~\0xml" => $dom->saveXML(),
        );

        $dom->formatOutput = $formatOutput;

        return $a;
    }

    public static function castCharacterData(\DOMCharacterData $dom, array $a, $isNested, &$cut)
    {
        $a += array(
            'data' => $dom->data,
            'length' => $dom->length,
        );

        return $a;
    }

    public static function castAttr(\DOMAttr $dom, array $a, $isNested, &$cut)
    {
        $a += array(
            'name' => $dom->name,
            'specified' => $dom->specified,
            'value' => $dom->value,
            'ownerElement' => $dom->ownerElement,
            'schemaTypeInfo' => $dom->schemaTypeInfo,
        );

        return $a;
    }

    public static function castElement(\DOMElement $dom, array $a, $isNested, &$cut)
    {
        $a += array(
            'tagName' => $dom->tagName,
            'schemaTypeInfo' => $dom->schemaTypeInfo,
        );

        return $a;
    }

    public static function castText(\DOMText $dom, array $a, $isNested, &$cut)
    {
        $a += array(
            'wholeText' => $dom->wholeText,
        );

        return $a;
    }

    public static function castTypeinfo(\DOMTypeinfo $dom, array $a, $isNested, &$cut)
    {
        $a += array(
            'typeName' => $dom->typeName,
            'typeNamespace' => $dom->typeNamespace,
        );

        return $a;
    }

    public static function castDomError(\DOMDomError $dom, array $a, $isNested, &$cut)
    {
        $a += array(
            'severity' => $dom->severity,
            'message' => $dom->message,
            'type' => $dom->type,
            'relatedException' => $dom->relatedException,
            'related_data' => $dom->related_data,
            'location' => $dom->location,
        );

        return $a;
    }

    public static function castLocator(\DOMLocator $dom, array $a, $isNested, &$cut)
    {
        $a += array(
            'lineNumber' => $dom->lineNumber,
            'columnNumber' => $dom->columnNumber,
            'offset' => $dom->offset,
            'relatedNode' => $dom->relatedNode,
            'uri' => $dom->uri,
        );

        return $a;
    }

    public static function castDocumentType(\DOMDocumentType $dom, array $a, $isNested, &$cut)
    {
        $a += array(
            'name' => $dom->name,
            'entities' => $dom->entities,
            'notations' => $dom->notations,
            'publicId' => $dom->publicId,
            'systemId' => $dom->systemId,
            'internalSubset' => $dom->internalSubset,
        );

        return $a;
    }

    public static function castNotation(\DOMNotation $dom, array $a, $isNested, &$cut)
    {
        $a += array(
            'publicId' => $dom->publicId,
            'systemId' => $dom->systemId,
        );

        return $a;
    }

    public static function castEntity(\DOMEntity $dom, array $a, $isNested, &$cut)
    {
        $a += array(
            'publicId' => $dom->publicId,
            'systemId' => $dom->systemId,
            'notationName' => $dom->notationName,
            'actualEncoding' => $dom->actualEncoding,
            'encoding' => $dom->encoding,
            'version' => $dom->version,
        );

        return $a;
    }

    public static function castProcessingInstruction(\DOMProcessingInstruction $dom, array $a, $isNested, &$cut)
    {
        $a += array(
            'target' => $dom->target,
            'data' => $dom->data,
        );

        return $a;
    }

    public static function castXPath(\DOMXPath $dom, array $a, $isNested, &$cut)
    {
        $a += array(
            'document' => $dom->document,
        );

        return $a;
    }
}
