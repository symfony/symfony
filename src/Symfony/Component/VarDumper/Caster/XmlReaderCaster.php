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

use Symfony\Component\VarDumper\Cloner\Stub;

/**
 * Casts XmlReader class to array representation.
 *
 * @author Baptiste Clavié <clavie.b@gmail.com>
 */
class XmlReaderCaster
{
    private static $nodeTypes = array(
        \XmlReader::NONE => 'NONE',
        \XmlReader::ELEMENT => 'ELEMENT',
        \XmlReader::ATTRIBUTE => 'ATTRIBUTE',
        \XmlReader::TEXT => 'TEXT',
        \XmlReader::CDATA => 'CDATA',
        \XmlReader::ENTITY_REF => 'ENTITY_REF',
        \XmlReader::ENTITY => 'ENTITY',
        \XmlReader::PI => 'PI',
        \XmlReader::COMMENT => 'COMMENT',
        \XmlReader::DOC => 'DOC',
        \XmlReader::DOC_TYPE => 'DOC_TYPE',
        \XmlReader::DOC_FRAGMENT => 'DOC_FRAGMENT',
        \XmlReader::NOTATION => 'NOTATION',
        \XmlReader::WHITESPACE => 'WHITESPACE',
        \XmlReader::SIGNIFICANT_WHITESPACE => 'SIGNIFICANT_WHITESPACE',
        \XmlReader::END_ELEMENT => 'END_ELEMENT',
        \XmlReader::END_ENTITY => 'END_ENTITY',
        \XmlReader::XML_DECLARATION => 'XML_DECLARATION',
    );

    private static $filteredTypes = array(
        \XmlReader::ENTITY_REF => true,
        \XmlReader::ENTITY => true,
        \XmlReader::PI => true,
        \XmlReader::DOC => true,
        \XmlReader::DOC_TYPE => true,
        \XmlReader::DOC_FRAGMENT => true,
        \XmlReader::NOTATION => true,
        \XmlReader::WHITESPACE => true,
        \XmlReader::SIGNIFICANT_WHITESPACE => true,
        \XmlReader::END_ELEMENT => true,
        \XmlReader::END_ENTITY => true,
    );

    public static function castXmlReader(\XmlReader $reader, array $a, Stub $stub, $isNested)
    {
        $nodeType = new ConstStub(self::$nodeTypes[$reader->nodeType], $reader->nodeType);
        $parserProperties = new EnumStub(array(
            'LOADDTD' => $reader->getParserProperty(\XmlReader::LOADDTD),
            'DEFAULTATTRS' => $reader->getParserProperty(\XmlReader::DEFAULTATTRS),
            'VALIDATE' => $reader->getParserProperty(\XmlReader::VALIDATE),
            'SUBST_ENTITIES' => $reader->getParserProperty(\XmlReader::SUBST_ENTITIES),
        ));

        if (\XmlReader::NONE === $reader->nodeType) {
            return $a + self::castXmlNone($nodeType, $parserProperties);
        }

        if (\XmlReader::ATTRIBUTE === $reader->nodeType) {
            return $a + self::castAttribute($reader, $nodeType, $parserProperties);
        }

        $infos = array(
            'localName' => $reader->localName,
            'nodeType' => $nodeType,
            'depth' => $reader->depth,
            'attributeCount' => $reader->attributeCount,
            'hasAttributes' => $reader->hasAttributes,
            'hasValue' => $reader->hasValue,
            'isDefault' => $reader->isDefault,
            'isEmptyElement' => $reader->isEmptyElement,
        );

        if ('' !== $reader->prefix) {
            $infos['prefix'] = $reader->prefix;
            $infos['namespaceURI'] = $reader->namespaceURI;
        }

        if ($reader->hasValue && \XmlReader::TEXT === $reader->nodeType) {
            $infos['value'] = $reader->value;
        }

        if ($reader->hasAttributes) {
            $infos[Caster::PREFIX_VIRTUAL.'attributes'] = array();

            for ($i = 0; $i < $reader->attributeCount; ++$i) {
                $infos[Caster::PREFIX_VIRTUAL.'attributes'][] = $reader->getAttributeNo($i);
            }
        }

        if (isset(self::$filteredTypes[$reader->nodeType])) {
            $infos = self::castFilteredElement($reader, $infos, $stub, $nodeType);
        }

        $infos[Caster::PREFIX_VIRTUAL.'parserProperties'] = $parserProperties;

        return $a + $infos;
    }

    private static function castXmlNone(ConstStub $type, EnumStub $properties)
    {
        return array(
            'nodeType' => $type,
            Caster::PREFIX_VIRTUAL.'parserProperties' => $properties,
        );
    }

    private static function castFilteredElement(\XmlReader $reader, array $infos, Stub $stub, ConstStub $type)
    {
        $cut = array(
            'localName' => $reader->localName,
            'nodeType' => $type,
            'depth' => $reader->depth,
        );

        $stub->cut += count($infos) - count($cut);

        return $cut;
    }

    private static function castAttribute(\XmlReader $reader, ConstStub $type, EnumStub $properties)
    {
        $infos = array(
            'localName' => $reader->localName,
            'nodeType' => $type,
            'depth' => $reader->depth,
            'isDefault' => $reader->isDefault,
            'hasValue' => $reader->hasValue,
        );

        if ($reader->hasValue) {
            $infos['value'] = $reader->value;
        }

        if ('' !== $reader->prefix) {
            $infos['prefix'] = $reader->prefix;
            $infos['namespaceURI'] = $reader->namespaceURI;
        }

        $infos[Caster::PREFIX_VIRTUAL.'parserProperties'] = $properties;

        return $infos;
    }
}
