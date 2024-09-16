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

    /**
     * @requires PHP < 8.4
     */
    public function testCastNodePriorToPhp84()
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
    public function testCastNode()
    {
        $doc = new \DOMDocument();
        $doc->loadXML('<foo><bar/></foo>');
        $node = $doc->documentElement->firstChild;

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            DOMElement {%A
              +ownerDocument: ~ ?DOMDocument
              +namespaceURI: ~ ?string
              +prefix: ~ string
              +localName: ~ ?string
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
        $doc = \Dom\XMLDocument::createFromString('<foo><bar/></foo>');
        $node = $doc->documentElement->firstChild;

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            Dom\Element {%A
              +baseURI: ~ string
              +isConnected: ~ bool
              +ownerDocument: ~ ?Dom\Document
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

    /**
     * @requires PHP 8.4
     */
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

    /**
     * @requires PHP < 8.4
     */
    public function testCastTextPriorToPhp84()
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
    public function testCastText()
    {
        $doc = new \DOMText('foo');

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            DOMText {%A
              +wholeText: ~ string
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
        $text = \Dom\HTMLDocument::createEmpty()->createTextNode('foo');
        $this->assertDumpMatchesFormat(<<<'EODUMP'
            Dom\Text {%A
              +wholeText: ~ string
            }
            EODUMP,
            $text
        );
    }

    /**
     * @requires PHP < 8.4
     */
    public function testCastAttrPriorToPhp84()
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
    public function testCastAttr()
    {
        $attr = new \DOMAttr('attr', 'value');

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            DOMAttr {%A
              +name: ~ string
              +specified: ~ bool
              +value: ~ string
              +ownerElement: ~ ?DOMElement
              +schemaTypeInfo: ~ mixed
            }
            EODUMP,
            $attr
        );
    }

    /**
     * @requires PHP 8.4
     */
    public function testCastAttrPrior()
    {
        $attr = new \DOMAttr('attr', 'value');

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            DOMAttr {%A
              +name: ~ string
              +specified: ~ bool
              +value: ~ string
              +ownerElement: ~ ?DOMElement
              +schemaTypeInfo: ~ mixed
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
        $attr = \Dom\HTMLDocument::createEmpty()->createAttribute('attr');

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            Dom\Attr {%A
              +name: ~ string
              +value: ~ string
              +ownerElement: ~ ?Dom\Element
              +specified: ~ bool
            }
            EODUMP,
            $attr
        );
    }

    /**
     * @requires PHP < 8.4
     */
    public function testCastElementPriorToPhp84()
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
    public function testCastElement()
    {
        $attr = new \DOMElement('foo');

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            DOMElement {%A
              +tagName: ~ string
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
        $attr = \Dom\HTMLDocument::createEmpty()->createElement('foo');

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            Dom\HTMLElement {%A
              +tagName: ~ string
            %A}
            EODUMP,
            $attr
        );
    }

    /**
     * @requires PHP < 8.4
     */
    public function testCastDocumentTypePriorToPhp84()
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
    public function testCastDocumentType()
    {
        $implementation = new \DOMImplementation();
        $type = $implementation->createDocumentType('html', 'publicId', 'systemId');

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            DOMDocumentType {%A
              +name: ~ string
              +entities: ~ DOMNamedNodeMap
              +notations: ~ DOMNamedNodeMap
              +publicId: ~ string
              +systemId: ~ string
              +internalSubset: ~ ?string
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
        $implementation = new \Dom\Implementation();
        $type = $implementation->createDocumentType('html', 'publicId', 'systemId');

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            Dom\DocumentType {%A
              +name: ~ string
              +entities: ~ Dom\DtdNamedNodeMap
              +notations: ~ Dom\DtdNamedNodeMap
              +publicId: ~ string
              +systemId: ~ string
              +internalSubset: ~ ?string
            }
            EODUMP,
            $type
        );
    }

    /**
     * @requires PHP < 8.4
     */
    public function testCastProcessingInstructionPriorToPhp84()
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
    public function testCastProcessingInstruction()
    {
        $entity = new \DOMProcessingInstruction('target', 'data');

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            DOMProcessingInstruction {%A
              +target: ~ string
              +data: ~ string
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
        $entity = \Dom\HTMLDocument::createEmpty()->createProcessingInstruction('target', 'data');

        $this->assertDumpMatchesFormat(<<<'EODUMP'
            Dom\ProcessingInstruction {%A
              +data: ~ string
              +length: ~ int
              +target: ~ string
            }
            EODUMP,
            $entity
        );
    }

    /**
     * @requires PHP < 8.4
     */
    public function testCastXPathPriorToPhp84()
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
    public function testCastXPath()
    {
        $xpath = new \DOMXPath(new \DOMDocument());

        $this->assertDumpEquals(<<<'EODUMP'
            DOMXPath {
              +document: ~ DOMDocument
              +registerNodeNamespaces: ~ bool
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
        $entity = new \Dom\XPath(\Dom\HTMLDocument::createEmpty());

        $this->assertDumpEquals(<<<'EODUMP'
            Dom\XPath {
              +document: ~ Dom\Document
              +registerNodeNamespaces: ~ bool
            }
            EODUMP,
            $entity
        );
    }
}
