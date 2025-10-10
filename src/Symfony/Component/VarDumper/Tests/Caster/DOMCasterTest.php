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

use PHPUnit\Framework\Attributes\RequiresPhp;
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

    #[RequiresPhp('>=8.4')]
    public function testCastModernImplementation()
    {
        $implementation = new \Dom\Implementation();

        $this->assertDumpEquals(<<<'EODUMP'
            Dom\Implementation {
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
              +parentNode: DOMElement {%a…}
            %A}
            EODUMP,
            $node
        );
    }

    #[RequiresPhp('>=8.4')]
    public function testCastModernNode()
    {
        $doc = \Dom\XMLDocument::createFromString('<foo><bar/></foo>');
        $node = $doc->documentElement->firstChild;

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            Dom\Element {%A
              +parentElement: Dom\Element {#1 …}
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

    #[RequiresPhp('>=8.4')]
    public function testCastXMLDocument()
    {
        $doc = \Dom\XMLDocument::createFromString('<foo><bar/></foo>');

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            Dom\XMLDocument {%A
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

    #[RequiresPhp('>=8.4')]
    public function testCastHTMLDocument()
    {
        $doc = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body><p>foo</p></body></html>');

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            Dom\HTMLDocument {%A
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
              +nodeName: "#text"
            %A}
            EODUMP,
            $doc
        );
    }

    #[RequiresPhp('>=8.4')]
    public function testCastModernText()
    {
        $text = \Dom\HTMLDocument::createEmpty()->createTextNode('foo');
        $this->assertDumpMatchesFormat(<<<'EODUMP'
            Dom\Text {%A
              +nodeName: "#text"
            %A}
            EODUMP,
            $text
        );
    }

    public function testCastAttr()
    {
        $attr = new \DOMAttr('attr', 'value');

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            DOMAttr {%A
              +nodeName: "attr"
            %A}
            EODUMP,
            $attr
        );
    }

    #[RequiresPhp('>=8.4')]
    public function testCastModernAttr()
    {
        $attr = \Dom\HTMLDocument::createEmpty()->createAttribute('attr');

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            Dom\Attr {%A
              +nodeName: "attr"
            %A}
            EODUMP,
            $attr
        );
    }

    public function testCastElement()
    {
        $attr = new \DOMElement('foo');

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            DOMElement {%A
              +tagName: "foo"
            %A}
            EODUMP,
            $attr
        );
    }

    #[RequiresPhp('>=8.4')]
    public function testCastModernElement()
    {
        $attr = \Dom\HTMLDocument::createEmpty()->createElement('foo');

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            Dom\HTMLElement {%A
              +tagName: "FOO"
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
            DOMDocumentType {
              +nodeName: "html"
              +nodeValue: null
              +nodeType: XML_DOCUMENT_TYPE_NODE
            %A}
            EODUMP,
            $type
        );
    }

    #[RequiresPhp('>=8.4')]
    public function testCastModernDocumentType()
    {
        $implementation = new \Dom\Implementation();
        $type = $implementation->createDocumentType('html', 'publicId', 'systemId');

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            Dom\DocumentType {
              +nodeType: XML_DOCUMENT_TYPE_NODE
            %A}
            EODUMP,
            $type
        );
    }

    public function testCastProcessingInstruction()
    {
        $entity = new \DOMProcessingInstruction('target', 'data');

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            DOMProcessingInstruction {%A
              +data: "data"
            }
            EODUMP,
            $entity
        );
    }

    #[RequiresPhp('>=8.4')]
    public function testCastModernProcessingInstruction()
    {
        $entity = \Dom\HTMLDocument::createEmpty()->createProcessingInstruction('target', 'data');

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            Dom\ProcessingInstruction {%A
              +target: "target"
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
              +document: DOMDocument { …}
              +registerNodeNamespaces: true
            }
            EODUMP,
            $xpath
        );
    }

    #[RequiresPhp('>=8.4')]
    public function testCastModernXPath()
    {
        $entity = new \Dom\XPath(\Dom\HTMLDocument::createEmpty());

        $this->assertDumpEquals(<<<'EODUMP'
            Dom\XPath {
              +document: Dom\HTMLDocument { …}
              +registerNodeNamespaces: true
            }
            EODUMP,
            $entity
        );
    }
}
