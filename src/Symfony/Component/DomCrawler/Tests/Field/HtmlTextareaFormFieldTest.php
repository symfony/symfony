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
use Symfony\Component\DomCrawler\Field\HtmlTextareaFormField;

class HtmlTextareaFormFieldTest extends FormFieldTestCase
{
    #[DataProvider('provideContents')]
    public function testInitialize(string $content)
    {
        $node = $this->createHtmlNode('textarea', $content, ['name' => 'name']);
        $field = new HtmlTextareaFormField($node);

        $this->assertSame($content, $field->getValue());
    }

    public static function provideContents(): iterable
    {
        yield 'text' => ['foo bar'];
        yield 'empty' => [''];
        yield 'markup is raw text' => ['foo bar <h1>Baz</h1>'];
        yield 'unbalanced markup is raw text' => ['foo bar <h1>Baz</h2>'];
        yield 'newlines' => ["first\nsecond"];
    }

    public function testEntitiesAreDecoded()
    {
        $node = $this->createHtmlNode('textarea', 'a&amp;b&lt;c', ['name' => 'name']);
        $field = new HtmlTextareaFormField($node);

        $this->assertSame('a&b<c', $field->getValue());
    }

    public function testInitializeRejectsANonTextareaNode()
    {
        $node = $this->createHtmlNode('input');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('An HtmlTextareaFormField can only be created from a textarea tag (input given).');

        new HtmlTextareaFormField($node);
    }

    /**
     * The HTML parser reads the content of a textarea as raw text, so markup that
     * looks like a comment is part of the value. loadHTML() parses it as markup
     * instead and drops it, which is why the two fields report different values.
     */
    public function testCommentsBelongToTheValue()
    {
        $html = '<!doctype html><body><textarea name="name">a<!--c-->b</textarea></body>';

        $node = \Dom\HTMLDocument::createFromString($html, 0)->querySelector('textarea');
        $this->assertSame('a<!--c-->b', (new HtmlTextareaFormField($node))->getValue());

        $document = new \DOMDocument();
        $document->loadHTML($html, \LIBXML_NOERROR);
        $this->assertSame('ab', $document->getElementsByTagName('textarea')->item(0)->textContent);
    }
}
