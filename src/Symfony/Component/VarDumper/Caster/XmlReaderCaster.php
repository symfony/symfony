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
        \XmlReader::XML_DECLARATION => 'XML_DECLARATION'
    );

    public static function castXmlReader(\XmlReader $reader, array $a, Stub $stub, $isNested)
    {
        $nodeType = new ConstStub(self::$nodeTypes[$reader->nodeType], $reader->nodeType);

        $infos = array(
            'localName' => $reader->localName,

            'depth' => $reader->depth,

            'attributeCount' => $reader->attributeCount,
            'hasAttributes' => $reader->hasAttributes,

            'hasValue' => $reader->hasValue,
            'isDefault' => $reader->isDefault,
            'isEmptyElement' => $reader->isEmptyElement,
            'nodeType' => $nodeType,
        );

        if ($reader->hasValue && (\XmlReader::TEXT === $reader->nodeType || \XmlReader::ATTRIBUTE === $reader->nodeType)) {
            $infos['value'] = $reader->value;

            unset($infos['localName']);
            $stub->cut += 1;
        }

        if ($reader->hasAttributes) {
            $infos[Caster::PREFIX_VIRTUAL . 'attributes'] = array();

            while ($reader->moveToNextAttribute()) {
                $infos[Caster::PREFIX_VIRTUAL . 'attributes'][$reader->name] = $reader->value;
            }
        }

        return $a + $infos;
    }
}
