<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler\Tests\Field;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DomCrawler\Field\TextareaFormField;

class TextareaFormFieldTest extends FormFieldTestCase
{
    public function testInitialize()
    {
        $node = $this->createNode('textarea', 'foo bar');
        $field = new TextareaFormField($node);

        $this->assertEquals('foo bar', $field->getValue(), '->initialize() sets the value of the field to the textarea node value');

        $node = $this->createNode('input', '');
        try {
            new TextareaFormField($node);
            $this->fail('->initialize() throws a \LogicException if the node is not a textarea');
        } catch (\LogicException $e) {
            $this->assertTrue(true, '->initialize() throws a \LogicException if the node is not a textarea');
        }

        // Ensure that valid HTML can be used on a textarea.
        $node = $this->createNode('textarea', 'foo bar <h1>Baz</h1>');
        $field = new TextareaFormField($node);

        $this->assertEquals('foo bar <h1>Baz</h1>', $field->getValue(), '->initialize() sets the value of the field to the textarea node value');

        // Ensure that we don't do any DOM manipulation/validation by passing in
        // "invalid" HTML.
        $node = $this->createNode('textarea', 'foo bar <h1>Baz</h2>');
        $field = new TextareaFormField($node);

        $this->assertEquals('foo bar <h1>Baz</h2>', $field->getValue(), '->initialize() sets the value of the field to the textarea node value');
    }

    #[DataProvider('provideParsedContents')]
    public function testInitializeWithParsedContent(string $content, string $expected)
    {
        $field = new TextareaFormField($this->parseTextarea($content));

        $this->assertSame($expected, $field->getValue());
    }

    public static function provideParsedContents(): iterable
    {
        yield 'text' => ['foo bar', 'foo bar'];
        yield 'empty' => ['', ''];
        yield 'entity' => ['a&amp;b', 'a&b'];
        yield 'escaped markup' => ['foo bar &lt;h1&gt;Baz&lt;/h1&gt;', 'foo bar <h1>Baz</h1>'];
        yield 'comment' => ['a<!--c-->b', 'ab'];
        yield 'element' => ['foo bar <h1>Baz</h1>', 'foo bar Baz'];
        yield 'inline element' => ['a<b>c</b>', 'ac'];
        yield 'nested elements' => ['a<div><p>b</p></div>c', 'abc'];
    }

    public function testInitializeDoesNotRepeatAdjacentTextNodes()
    {
        $document = new \DOMDocument();
        $document->loadXML('<form><textarea name="name">a<![CDATA[x]]>b</textarea></form>');

        $field = new TextareaFormField($document->getElementsByTagName('textarea')->item(0));

        $this->assertSame('axb', $field->getValue());
    }

    /**
     * The content has to be parsed instead of passed to createElement(), which
     * always builds a single text child and never the other node types the
     * parser produces for markup inside a textarea.
     */
    private function parseTextarea(string $content): \DOMElement
    {
        $document = new \DOMDocument();
        $document->loadHTML('<html><body><form><textarea name="name">'.$content.'</textarea></form></body></html>', \LIBXML_NOERROR);

        return $document->getElementsByTagName('textarea')->item(0);
    }
}
