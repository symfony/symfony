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
        \XmlReader::PI => 'PI (Processing Instruction)',
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

    public static function castXmlReader(\XmlReader $reader, array $a, Stub $stub, $isNested)
    {
        $props = Caster::PREFIX_VIRTUAL.'parserProperties';
        $info = array(
            'localName' => $reader->localName,
            'prefix' => $reader->prefix,
            'nodeType' => new ConstStub(self::$nodeTypes[$reader->nodeType], $reader->nodeType),
            'depth' => $reader->depth,
            'isDefault' => $reader->isDefault,
            'isEmptyElement' => \XmlReader::NONE === $reader->nodeType ? null : $reader->isEmptyElement,
            'xmlLang' => $reader->xmlLang,
            'attributeCount' => $reader->attributeCount,
            'value' => $reader->value,
            'namespaceURI' => $reader->namespaceURI,
            'baseURI' => $reader->baseURI ? new LinkStub($reader->baseURI) : $reader->baseURI,
            $props => array(
                'LOADDTD' => $reader->getParserProperty(\XmlReader::LOADDTD),
                'DEFAULTATTRS' => $reader->getParserProperty(\XmlReader::DEFAULTATTRS),
                'VALIDATE' => $reader->getParserProperty(\XmlReader::VALIDATE),
                'SUBST_ENTITIES' => $reader->getParserProperty(\XmlReader::SUBST_ENTITIES),
            ),
        );

        if ($info[$props] = Caster::filter($info[$props], Caster::EXCLUDE_EMPTY, array(), $count)) {
            $info[$props] = new EnumStub($info[$props]);
            $info[$props]->cut = $count;
        }

        $info = Caster::filter($info, Caster::EXCLUDE_EMPTY, array(), $count);
        // +2 because hasValue and hasAttributes are always filtered
        $stub->cut += $count + 2;

        return $a + $info;
    }
}
