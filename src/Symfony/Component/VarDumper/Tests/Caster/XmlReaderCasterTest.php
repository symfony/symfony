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

use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

/**
 * @author Baptiste Clavié <clavie.b@gmail.com>
 */
class XmlReaderCasterTest extends TestCase
{
    use VarDumperTestTrait;

    private \XMLReader $reader;

    protected function setUp(): void
    {
        $this->reader = new \XMLReader();
        $this->reader->open(__DIR__.'/../Fixtures/xml_reader.xml');
    }

    protected function tearDown(): void
    {
        $this->reader->close();
    }

    /**
     * @requires PHP < 8.4
     */
    public function testParserPropertyPriorToPhp84()
    {
        $this->reader->setParserProperty(\XMLReader::SUBST_ENTITIES, true);

        $expectedDump = <<<'EODUMP'
XMLReader {
  +nodeType: NONE
  parserProperties: {
    SUBST_ENTITIES: true
     …3
  }
   …12
}
EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, $this->reader);
    }

    /**
     * @requires PHP 8.4
     */
    public function testParserProperty()
    {
        $this->reader->setParserProperty(\XMLReader::SUBST_ENTITIES, true);

        $expectedDump = <<<'EODUMP'
XMLReader {%A
  +nodeType: ~ int
%A
  parserProperties: {
    SUBST_ENTITIES: true
     …3
  }
   …12
}
EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, $this->reader);
    }

    /**
     * This test only work before PHP 8.4. In PHP 8.4, XMLReader properties are virtual
     * and their values are not dumped.
     *
     * @requires PHP < 8.4
     *
     * @dataProvider provideNodes
     */
    public function testNodes($seek, $expectedDump)
    {
        while ($seek--) {
            $this->reader->read();
        }
        $this->assertDumpMatchesFormat($expectedDump, $this->reader);
    }

    public static function provideNodes()
    {
        return [
            [0, <<<'EODUMP'
XMLReader {
  +nodeType: NONE
   …13
}
EODUMP
            ],
            [1, <<<'EODUMP'
XMLReader {
  +localName: "foo"
  +nodeType: ELEMENT
  +baseURI: "%sxml_reader.xml"
   …11
}
EODUMP
            ],
            [2, <<<'EODUMP'
XMLReader {
  +localName: "#text"
  +nodeType: SIGNIFICANT_WHITESPACE
  +depth: 1
  +value: """
    \n
        
    """
  +baseURI: "%sxml_reader.xml"
   …9
}
EODUMP
            ],
            [3, <<<'EODUMP'
XMLReader {
  +localName: "bar"
  +nodeType: ELEMENT
  +depth: 1
  +baseURI: "%sxml_reader.xml"
   …10
}
EODUMP
            ],
            [4, <<<'EODUMP'
XMLReader {
  +localName: "bar"
  +nodeType: END_ELEMENT
  +depth: 1
  +baseURI: "%sxml_reader.xml"
   …10
}
EODUMP
            ],
            [6, <<<'EODUMP'
XMLReader {
  +localName: "bar"
  +nodeType: ELEMENT
  +depth: 1
  +isEmptyElement: true
  +baseURI: "%sxml_reader.xml"
   …9
}
EODUMP
            ],
            [9, <<<'EODUMP'
XMLReader {
  +localName: "#text"
  +nodeType: TEXT
  +depth: 2
  +value: "With text"
  +baseURI: "%sxml_reader.xml"
   …9
}
EODUMP
            ],
            [12, <<<'EODUMP'
XMLReader {
  +localName: "bar"
  +nodeType: ELEMENT
  +depth: 1
  +attributeCount: 2
  +baseURI: "%sxml_reader.xml"
   …9
}
EODUMP
            ],
            [13, <<<'EODUMP'
XMLReader {
  +localName: "bar"
  +nodeType: END_ELEMENT
  +depth: 1
  +baseURI: "%sxml_reader.xml"
   …10
}
EODUMP
            ],
            [15, <<<'EODUMP'
XMLReader {
  +localName: "bar"
  +nodeType: ELEMENT
  +depth: 1
  +attributeCount: 1
  +baseURI: "%sxml_reader.xml"
   …9
}
EODUMP
            ],
            [16, <<<'EODUMP'
XMLReader {
  +localName: "#text"
  +nodeType: SIGNIFICANT_WHITESPACE
  +depth: 2
  +value: """
    \n
            
    """
  +baseURI: "%sxml_reader.xml"
   …9
}
EODUMP
            ],
            [17, <<<'EODUMP'
XMLReader {
  +localName: "baz"
  +prefix: "baz"
  +nodeType: ELEMENT
  +depth: 2
  +namespaceURI: "http://symfony.com"
  +baseURI: "%sxml_reader.xml"
   …8
}
EODUMP
            ],
            [18, <<<'EODUMP'
XMLReader {
  +localName: "baz"
  +prefix: "baz"
  +nodeType: END_ELEMENT
  +depth: 2
  +namespaceURI: "http://symfony.com"
  +baseURI: "%sxml_reader.xml"
   …8
}
EODUMP
            ],
            [19, <<<'EODUMP'
XMLReader {
  +localName: "#text"
  +nodeType: SIGNIFICANT_WHITESPACE
  +depth: 2
  +value: """
    \n
        
    """
  +baseURI: "%sxml_reader.xml"
   …9
}
EODUMP
            ],
            [21, <<<'EODUMP'
XMLReader {
  +localName: "#text"
  +nodeType: SIGNIFICANT_WHITESPACE
  +depth: 1
  +value: "\n"
  +baseURI: "%sxml_reader.xml"
   …9
}
EODUMP
            ],
            [22, <<<'EODUMP'
XMLReader {
  +localName: "foo"
  +nodeType: END_ELEMENT
  +baseURI: "%sxml_reader.xml"
   …11
}
EODUMP
            ],
        ];
    }

    /**
     * @requires PHP < 8.4
     */
    public function testWithUninitializedXMLReaderPriorToPhp84()
    {
        $this->reader = new \XMLReader();

        $expectedDump = <<<'EODUMP'
XMLReader {
  +nodeType: NONE
   …13
}
EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, $this->reader);
    }

    /**
     * @requires PHP 8.4
     */
    public function testWithUninitializedXMLReader()
    {
        $this->reader = new \XMLReader();

        $expectedDump = <<<'EODUMP'
XMLReader {%A
  +nodeType: ~ int
%A
   …13
}
EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, $this->reader);
    }
}
