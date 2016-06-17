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
  +nodeType: ELEMENT
  +depth: 0
  +attributeCount: 0
  +hasAttributes: false
  +hasValue: false
  +isDefault: false
  +isEmptyElement: false
  parserProperties: {
    LOADDTD: false
    DEFAULTATTRS: false
    VALIDATE: false
    SUBST_ENTITIES: false
  }
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
  +nodeType: ELEMENT
  +depth: 1
  +attributeCount: 0
  +hasAttributes: false
  +hasValue: false
  +isDefault: false
  +isEmptyElement: false
  parserProperties: {
    LOADDTD: false
    DEFAULTATTRS: false
    VALIDATE: false
    SUBST_ENTITIES: false
  }
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
  +nodeType: ELEMENT
  +depth: 1
  +attributeCount: 0
  +hasAttributes: false
  +hasValue: false
  +isDefault: false
  +isEmptyElement: true
  parserProperties: {
    LOADDTD: false
    DEFAULTATTRS: false
    VALIDATE: false
    SUBST_ENTITIES: false
  }
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
  +nodeType: ELEMENT
  +depth: 1
  +attributeCount: 2
  +hasAttributes: true
  +hasValue: false
  +isDefault: false
  +isEmptyElement: false
  attributes: array:2 [
    0 => "bar"
    1 => "fubar"
  ]
  parserProperties: {
    LOADDTD: false
    DEFAULTATTRS: false
    VALIDATE: false
    SUBST_ENTITIES: false
  }
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
  +localName: "#text"
  +nodeType: TEXT
  +depth: 2
  +attributeCount: 0
  +hasAttributes: false
  +hasValue: true
  +isDefault: false
  +isEmptyElement: false
  +value: "With text"
  parserProperties: {
    LOADDTD: false
    DEFAULTATTRS: false
    VALIDATE: false
    SUBST_ENTITIES: false
  }
}

DUMP;

        while ($this->reader->read() && $this->reader->nodeType !== \XmlReader::TEXT);

        $this->assertDumpEquals($dump, $this->reader);
    }

    /** @dataProvider textFilteredProvider */
    public function testFilteredTextElement($nodeType, $nodeTypeName, $depth, $localName)
    {
        $dump = <<<DUMP
XMLReader {
  +localName: "$localName"
  +nodeType: $nodeTypeName
  +depth: $depth
  parserProperties: {
    LOADDTD: false
    DEFAULTATTRS: false
    VALIDATE: false
    SUBST_ENTITIES: false
  }
   …5
}

DUMP;

        while ($this->reader->read() && $this->reader->nodeType !== $nodeType);

        $this->assertDumpEquals($dump, $this->reader);
    }

    public function textFilteredProvider()
    {
        return array(
            'Significant Whiltespace element' => array(\XmlReader::SIGNIFICANT_WHITESPACE, 'SIGNIFICANT_WHITESPACE', 1, '#text'),
        );
    }

    /** @dataProvider elementFilteredProvider */
    public function testFilteredElement($localName, $nodeType, $nodeTypeName, $expandType, $depth)
    {
        $dump = <<<DUMP
XMLReader {
  +localName: "$localName"
  +nodeType: $nodeTypeName
  +depth: $depth
  parserProperties: {
    LOADDTD: false
    DEFAULTATTRS: false
    VALIDATE: false
    SUBST_ENTITIES: false
  }
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
  +localName: "foo"
  +nodeType: ATTRIBUTE
  +depth: 2
  +isDefault: false
  +hasValue: true
  +value: "bar"
  parserProperties: {
    LOADDTD: false
    DEFAULTATTRS: false
    VALIDATE: false
    SUBST_ENTITIES: false
  }
}

DUMP;

        while ($this->reader->read() && !$this->reader->hasAttributes);

        $this->reader->moveToFirstAttribute();

        $this->assertDumpEquals($dump, $this->reader);
    }

    public function testPrefixedElements()
    {
        $dump = <<<'DUMP'
XMLReader {
  +localName: "baz"
  +nodeType: ELEMENT
  +depth: 2
  +attributeCount: 0
  +hasAttributes: false
  +hasValue: false
  +isDefault: false
  +isEmptyElement: false
  +prefix: "baz"
  +namespaceURI: "http://symfony.com"
  parserProperties: {
    LOADDTD: false
    DEFAULTATTRS: false
    VALIDATE: false
    SUBST_ENTITIES: false
  }
}
DUMP;

        while ($this->reader->read() && $this->reader->localName !== 'baz');

        $this->assertDumpEquals($dump, $this->reader);
    }

    public function testNone()
    {
        $dump = <<<'DUMP'
XMLReader {
  +nodeType: NONE
  parserProperties: {
    LOADDTD: false
    DEFAULTATTRS: false
    VALIDATE: false
    SUBST_ENTITIES: false
  }
}
DUMP;

        while ($this->reader->read());

        $this->assertDumpEquals($dump, $this->reader);
    }

    public function tearDown()
    {
        $this->reader->close();
    }
}
