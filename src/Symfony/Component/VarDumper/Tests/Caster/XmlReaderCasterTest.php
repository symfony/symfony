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

    /** @var \XmlReader */
    private $reader;

    protected function setUp()
    {
        $this->reader = new \XmlReader();
        $this->reader->open(__DIR__.'/../Fixtures/xml_reader.xml');
    }

    protected function tearDown()
    {
        $this->reader->close();
    }

    public function testParserProperty()
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
     * @dataProvider provideNodes
     */
    public function testNodes($seek, $expectedDump)
    {
        while ($seek--) {
            $this->reader->read();
        }
        $this->assertDumpMatchesFormat($expectedDump, $this->reader);
    }

    public function provideNodes()
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
}
