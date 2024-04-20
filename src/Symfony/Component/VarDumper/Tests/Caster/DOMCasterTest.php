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

class DOMCasterTest extends TestCase
{
    use VarDumperTestTrait;

    public function testCastImplementation()
    {
        $implementation = new \DOMImplementation();

        $this->assertDumpEquals(<<<'EODUMP'
            DOMImplementation {
              Core: "1.0"
              XML: "2.0"
            }
            EODUMP,
            $implementation
        );
    }

    /**
     * @requires PHP 8.4
     */
    public function testCastModernImplementation()
    {
        $implementation = new \DOM\Implementation();

        $this->assertDumpEquals(<<<'EODUMP'
            DOM\Implementation {
              Core: "1.0"
              XML: "2.0"
            }
            EODUMP,
            $implementation
        );
    }

    public function testCastNode()
    {
        $doc = new \DOMDocument();
        $doc->loadXML('<foo><bar/></foo>');
        $node = $doc->documentElement->firstChild;

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            DOMElement {%A
              +ownerDocument: ? ?DOMDocument
              +namespaceURI: ? ?string
              +prefix: ? string
              +localName: ? ?string
            %A}
            EODUMP,
            $node
        );
    }

    /**
     * @requires PHP 8.4
     */
    public function testCastModernNode()
    {
        $doc = \DOM\XMLDocument::createFromString('<foo><bar/></foo>');
        $node = $doc->documentElement->firstChild;

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            DOM\Element {%A
              +baseURI: ? string
              +isConnected: ? bool
              +ownerDocument: ? ?DOM\Document
            %A}
            EODUMP,
            $node
        );
    }

    public function testCastDocument()
    {
        $doc = new \DOMDocument();
        $doc->loadXML('<foo><bar/></foo>');

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            DOMDocument {%A
              xml: """
                <?xml version="1.0"?>\n
                <foo>\n
                  <bar/>\n
                </foo>\n
                """
            }
            EODUMP,
            $doc
        );
    }

    /**
     * @requires PHP 8.4
     */
    public function testCastXMLDocument()
    {
        $doc = \DOM\XMLDocument::createFromString('<foo><bar/></foo>');

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            DOM\XMLDocument {%A
              xml: """
                <?xml version="1.0" encoding="UTF-8"?>\n
                <foo>\n
                  <bar/>\n
                </foo>
                """
            }
            EODUMP,
            $doc
        );
    }

    /**
     * @requires PHP 8.4
     */
    public function testCastHTMLDocument()
    {
        $doc = \DOM\HTMLDocument::createFromString('<!DOCTYPE html><html><body><p>foo</p></body></html>');

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            DOM\HTMLDocument {%A
              html: "<!DOCTYPE html><html><head></head><body><p>foo</p></body></html>"
            }
            EODUMP,
            $doc
        );
    }

    public function testCastText()
    {
        $doc = new \DOMText('foo');

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            DOMText {%A
              +wholeText: ? string
            }
            EODUMP,
            $doc
        );
    }

    /**
     * @requires PHP 8.4
     */
    public function testCastModernText()
    {
        $text = \DOM\HTMLDocument::createEmpty()->createTextNode('foo');
        $this->assertDumpMatchesFormat(<<<'EODUMP'
            DOM\Text {%A
              +wholeText: ? string
            }
            EODUMP,
            $text
        );
    }

    public function testCastAttr()
    {
        $attr = new \DOMAttr('attr', 'value');

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            DOMAttr {%A
              +name: ? string
              +specified: true
              +value: ? string
              +ownerElement: ? ?DOMElement
              +schemaTypeInfo: null
            }
            EODUMP,
            $attr
        );
    }

    /**
     * @requires PHP 8.4
     */
    public function testCastModernAttr()
    {
        $attr = \DOM\HTMLDocument::createEmpty()->createAttribute('attr');

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            DOM\Attr {%A
              +name: ? string
              +value: ? string
              +ownerElement: ? ?DOM\Element
              +specified: true
            }
            EODUMP,
            $attr
        );
    }

    public function testCastElement()
    {
        $attr = new \DOMElement('foo');

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            DOMElement {%A
              +tagName: ? string
            %A}
            EODUMP,
            $attr
        );
    }

    /**
     * @requires PHP 8.4
     */
    public function testCastModernElement()
    {
        $attr = \DOM\HTMLDocument::createEmpty()->createElement('foo');

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            DOM\Element {%A
              +tagName: ? string
            %A}
            EODUMP,
            $attr
        );
    }

    public function testCastDocumentType()
    {
        $implementation = new \DOMImplementation();
        $type = $implementation->createDocumentType('html', 'publicId', 'systemId');

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            DOMDocumentType {%A
              +name: ? string
              +entities: ? DOMNamedNodeMap
              +notations: ? DOMNamedNodeMap
              +publicId: ? string
              +systemId: ? string
              +internalSubset: ? ?string
            }
            EODUMP,
            $type
        );
    }

    /**
     * @requires PHP 8.4
     */
    public function testCastModernDocumentType()
    {
        $implementation = new \DOM\Implementation();
        $type = $implementation->createDocumentType('html', 'publicId', 'systemId');

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            DOM\DocumentType {%A
              +name: ? string
              +entities: ? DOM\DTDNamedNodeMap
              +notations: ? DOM\DTDNamedNodeMap
              +publicId: ? string
              +systemId: ? string
              +internalSubset: ? ?string
            }
            EODUMP,
            $type
        );
    }

    public function testCastProcessingInstruction()
    {
        $entity = new \DOMProcessingInstruction('target', 'data');

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            DOMProcessingInstruction {%A
              +target: ? string
              +data: ? string
            }
            EODUMP,
            $entity
        );
    }

    /**
     * @requires PHP 8.4
     */
    public function testCastModernProcessingInstruction()
    {
        $entity = \DOM\HTMLDocument::createEmpty()->createProcessingInstruction('target', 'data');

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            DOM\ProcessingInstruction {%A
              +data: ? string
              +length: ? int
              +target: ? string
            }
            EODUMP,
            $entity
        );
    }

    public function testCastXPath()
    {
        $xpath = new \DOMXPath(new \DOMDocument());

        $this->assertDumpEquals(<<<'EODUMP'
            DOMXPath {
              +document: ? DOMDocument
              +registerNodeNamespaces: ? bool
            }
            EODUMP,
            $xpath
        );
    }

    /**
     * @requires PHP 8.4
     */
    public function testCastModernXPath()
    {
        $entity = new \DOM\XPath(\DOM\HTMLDocument::createEmpty());

        $this->assertDumpEquals(<<<'EODUMP'
            DOM\XPath {
              +document: ? DOM\Document
              +registerNodeNamespaces: ? bool
            }
            EODUMP,
            $entity
        );
    }
}
