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
        \XmlReader::ATTRIBUTE => true,
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

        $infos = array(
            'nodeType' => $nodeType,
            Caster::PREFIX_VIRTUAL.'parserProperties' => array(
                'LOADDTD' => $reader->getParserProperty(\XmlReader::LOADDTD),
                'DEFAULTATTRS' => $reader->getParserProperty(\XmlReader::DEFAULTATTRS),
                'VALIDATE' => $reader->getParserProperty(\XmlReader::VALIDATE),
                'SUBST_ENTITIES' => $reader->getParserProperty(\XmlReader::SUBST_ENTITIES),
            ),
        );

        if (\XmlReader::NONE === $reader->nodeType) {
            return $infos;
        }

        $infos = $infos + array(
            'localName' => $reader->localName,

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

        if ($reader->hasValue && (\XmlReader::TEXT === $reader->nodeType || \XmlReader::ATTRIBUTE === $reader->nodeType)) {
            $infos['value'] = $reader->value;

            unset($infos['localName']);
            $stub->cut += 1;
        }

        if ($reader->hasAttributes) {
            $infos[Caster::PREFIX_VIRTUAL.'attributes'] = array();

            for ($i = 0; $i < $reader->attributeCount; ++$i) {
                $infos[Caster::PREFIX_VIRTUAL.'attributes'][] = $reader->getAttributeNo($i);
            }
        }

        if (isset(static::$filteredTypes[$reader->nodeType])) {
            $cut = array(
                'nodeType' => $nodeType,
                'depth' => $reader->depth,

                Caster::PREFIX_VIRTUAL.'parserProperties' => $infos[Caster::PREFIX_VIRTUAL.'parserProperties'],
            );

            if ('#text' !== $reader->localName) {
                $cut['localName'] = $reader->localName;
            }

            if (\XmlReader::ATTRIBUTE === $reader->nodeType) {
                $cut['hasValue'] = $reader->hasValue;

                if ($reader->hasValue) {
                    $cut['value'] = $reader->value;
                }
            }

            if ('' !== $reader->prefix) {
                $cut['prefix'] = $reader->prefix;
                $cut['namespaceURI'] = $reader->namespaceURI;
            }

            $stub->cut += count($infos) - count($cut);

            return $cut;
        }

        return $a + $infos;
    }
}
