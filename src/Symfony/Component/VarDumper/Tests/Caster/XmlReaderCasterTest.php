<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Caster;

use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

/**
 * @author Baptiste Clavié <clavie.b@gmail.com>
 */
class XmlReaderCasterTest extends \PHPUnit_Framework_TestCase
{
    use VarDumperTestTrait;

    /** @var \XmlReader */
    private $reader;

    public function setUp()
    {
        $this->reader = new \XmlReader();
        $this->reader->open(__DIR__.'/../Fixtures/xml_reader.xml');
    }

    public function testSimpleElementNode()
    {
        $dump = <<<'DUMP'
XMLReader {
  +localName: "foo"
  +depth: 0
  +attributeCount: 0
  +hasAttributes: false
  +hasValue: false
  +isDefault: false
  +isEmptyElement: false
  +nodeType: ELEMENT
  parserProperties: array:4 [
    "LOADDTD" => false
    "DEFAULTATTRS" => false
    "VALIDATE" => false
    "SUBST_ENTITIES" => false
  ]
}
DUMP;

        while ($this->reader->read() && $this->reader->nodeType !== \XmlReader::ELEMENT);

        $this->assertDumpEquals($dump, $this->reader);
    }

    public function testNestedElement()
    {
        $dump = <<<'DUMP'
XMLReader {
  +localName: "bar"
  +depth: 1
  +attributeCount: 0
  +hasAttributes: false
  +hasValue: false
  +isDefault: false
  +isEmptyElement: false
  +nodeType: ELEMENT
  parserProperties: array:4 [
    "LOADDTD" => false
    "DEFAULTATTRS" => false
    "VALIDATE" => false
    "SUBST_ENTITIES" => false
  ]
}

DUMP;

        while ($this->reader->read() && $this->reader->localName !== 'bar');

        $this->assertDumpEquals($dump, $this->reader);
    }

    public function testEmptyElement()
    {
        $dump = <<<'DUMP'
XMLReader {
  +localName: "bar"
  +depth: 1
  +attributeCount: 0
  +hasAttributes: false
  +hasValue: false
  +isDefault: false
  +isEmptyElement: true
  +nodeType: ELEMENT
  parserProperties: array:4 [
    "LOADDTD" => false
    "DEFAULTATTRS" => false
    "VALIDATE" => false
    "SUBST_ENTITIES" => false
  ]
}

DUMP;

        while ($this->reader->read() && $this->reader->localName !== 'bar');

        $this->reader->next('bar');

        $this->assertDumpEquals($dump, $this->reader);
    }

    public function testElementWithAttributes()
    {
        $dump = <<<'DUMP'
XMLReader {
  +localName: "bar"
  +depth: 1
  +attributeCount: 2
  +hasAttributes: true
  +hasValue: false
  +isDefault: false
  +isEmptyElement: false
  +nodeType: ELEMENT
  parserProperties: array:4 [
    "LOADDTD" => false
    "DEFAULTATTRS" => false
    "VALIDATE" => false
    "SUBST_ENTITIES" => false
  ]
  attributes: array:2 [
    0 => "bar"
    1 => "fubar"
  ]
}

DUMP;

        while ($this->reader->read() && $this->reader->localName !== 'bar');

        $this->reader->next('bar');
        $this->reader->next('bar');
        $this->reader->next('bar');

        $this->assertDumpEquals($dump, $this->reader);
    }

    public function testTextElement()
    {
        $dump = <<<'DUMP'
XMLReader {
  +depth: 2
  +attributeCount: 0
  +hasAttributes: false
  +hasValue: true
  +isDefault: false
  +isEmptyElement: false
  +nodeType: TEXT
  parserProperties: array:4 [
    "LOADDTD" => false
    "DEFAULTATTRS" => false
    "VALIDATE" => false
    "SUBST_ENTITIES" => false
  ]
  +value: "With text"
   …1
}

DUMP;

        while ($this->reader->read() && $this->reader->nodeType !== \XmlReader::TEXT);

        $this->assertDumpEquals($dump, $this->reader);
    }

    /** @dataProvider textFilteredProvider */
    public function testFilteredTextElement($nodeType, $nodeTypeName, $depth)
    {
        $dump = <<<DUMP
XMLReader {
  +nodeType: $nodeTypeName
  +depth: $depth
  parserProperties: array:4 [
    "LOADDTD" => false
    "DEFAULTATTRS" => false
    "VALIDATE" => false
    "SUBST_ENTITIES" => false
  ]
   …6
}

DUMP;

        while ($this->reader->read() && $this->reader->nodeType !== $nodeType);

        $this->assertDumpEquals($dump, $this->reader);
    }

    public function textFilteredProvider()
    {
        return array(
            'Significant Whiltespace element' => array(\XmlReader::SIGNIFICANT_WHITESPACE, 'SIGNIFICANT_WHITESPACE', 1),
        );
    }

    /** @dataProvider elementFilteredProvider */
    public function testFilteredElement($localName, $nodeType, $nodeTypeName, $expandType, $depth)
    {
        $dump = <<<DUMP
XMLReader {
  +nodeType: $nodeTypeName
  +depth: $depth
  parserProperties: array:4 [
    "LOADDTD" => false
    "DEFAULTATTRS" => false
    "VALIDATE" => false
    "SUBST_ENTITIES" => false
  ]
  +localName: "$localName"
   …5
}

DUMP;

        while ($this->reader->read() && $this->reader->nodeType !== $nodeType);

        $this->assertDumpEquals($dump, $this->reader);
    }

    public function elementFilteredProvider()
    {
        return array(
            'End Element' => array('bar', \XmlReader::END_ELEMENT, 'END_ELEMENT', 'DOMElement', 1),
        );
    }

    public function testAttributeNode()
    {
        $dump = <<<'DUMP'
XMLReader {
  +nodeType: ATTRIBUTE
  +depth: 2
  parserProperties: array:4 [
    "LOADDTD" => false
    "DEFAULTATTRS" => false
    "VALIDATE" => false
    "SUBST_ENTITIES" => false
  ]
  +localName: "foo"
  +hasValue: true
  +value: "bar"
   …4
}

DUMP;

        while ($this->reader->read() && !$this->reader->hasAttributes);

        $this->reader->moveToFirstAttribute();

        $this->assertDumpEquals($dump, $this->reader);
    }

    public function tearDown()
    {
        $this->reader->close();
    }
}

