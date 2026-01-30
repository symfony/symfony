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

    public function testCastImplementation(): void
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

    public function testCastModernImplementation(): void
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

    public function testCastNode(): void
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

    public function testCastModernNode(): void
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

    public function testCastDocument(): void
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

    public function testCastXMLDocument(): void
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

    public function testCastHTMLDocument(): void
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

    public function testCastText(): void
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

    public function testCastModernText(): void
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

    public function testCastAttr(): void
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

    public function testCastModernAttr(): void
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

    public function testCastElement(): void
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

    public function testCastModernElement(): void
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

    public function testCastDocumentType(): void
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

    public function testCastModernDocumentType(): void
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

    public function testCastProcessingInstruction(): void
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

    public function testCastModernProcessingInstruction(): void
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

    public function testCastXPath(): void
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

    public function testCastModernXPath(): void
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
