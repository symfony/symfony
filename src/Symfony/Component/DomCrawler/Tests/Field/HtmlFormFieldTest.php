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
use Symfony\Component\DomCrawler\Field\HtmlInputFormField;
use Symfony\Component\DomCrawler\Field\InputFormField;

class HtmlFormFieldTest extends FormFieldTestCase
{
    public function testGetName()
    {
        $node = $this->createHtmlNode('input', '', ['type' => 'text', 'name' => 'name', 'value' => 'value']);
        $field = new HtmlInputFormField($node);

        $this->assertSame('name', $field->getName());
    }

    public function testGetNameIsEmptyWhenTheAttributeIsMissing()
    {
        $node = $this->createHtmlNode('input', '', ['type' => 'text']);
        $field = new HtmlInputFormField($node);

        $this->assertSame('', $field->getName());
    }

    public function testGetSetHasValue()
    {
        $node = $this->createHtmlNode('input', '', ['type' => 'text', 'name' => 'name', 'value' => 'value']);
        $field = new HtmlInputFormField($node);

        $this->assertSame('value', $field->getValue());

        $field->setValue('foo');
        $this->assertSame('foo', $field->getValue());

        $field->setValue(null);
        $this->assertSame('', $field->getValue());

        $this->assertTrue($field->hasValue());
    }

    public function testIsDisabled()
    {
        $node = $this->createHtmlNode('input', '', ['type' => 'text', 'name' => 'name', 'disabled' => 'disabled']);
        $this->assertTrue((new HtmlInputFormField($node))->isDisabled());

        $node = $this->createHtmlNode('input', '', ['type' => 'text', 'name' => 'name']);
        $this->assertFalse((new HtmlInputFormField($node))->isDisabled());
    }

    public function testGetLabelReturnsTheNativeElement()
    {
        $field = new HtmlInputFormField($this->nativeInput('<label for="foo">Foo label</label><input id="foo" name="foo">'));
        $label = $field->getLabel();

        $this->assertInstanceOf(\Dom\Element::class, $label);
        $this->assertSame('label', $label->localName);
    }

    /**
     * The native and the classic fields must find the same label.
     */
    #[DataProvider('provideLabelCases')]
    public function testGetLabelMatchesClassic(string $html, ?string $expected)
    {
        $native = new HtmlInputFormField($this->nativeInput($html));
        $classic = new InputFormField($this->classicInput($html));

        $this->assertSame($expected, $native->getLabel()?->textContent);
        $this->assertSame($classic->getLabel()?->textContent, $native->getLabel()?->textContent);
    }

    public static function provideLabelCases(): iterable
    {
        yield 'none' => ['<input id="foo" name="foo">', null];
        yield 'for attribute' => ['<label for="foo">L</label><input id="foo" name="foo">', 'L'];
        yield 'label after the input' => ['<input id="foo" name="foo"><label for="foo">L</label>', 'L'];
        yield 'parenting relation' => ['<label>L<input id="foo" name="foo"></label>', 'L'];
        yield 'for attribute wins over parenting' => ['<label for="foo">L</label><label>P<input id="foo" name="foo"></label>', 'L'];
        yield 'first matching label wins' => ['<label for="foo">L</label><label for="foo">O</label><input id="foo" name="foo">', 'L'];
        yield 'closest ancestor label wins' => ['<label>O<label>L<input name="foo"></label></label>', 'L'];
        yield 'unmatched for falls back to parenting' => ['<label for="bar">B</label><label>L<input id="foo" name="foo"></label>', 'L'];
        yield 'label outside the form' => ['<label for="foo">L</label><form><input id="foo" name="foo"></form>', 'L'];
        yield 'quote in the id' => ['<label for=\'a"b\'>L</label><input id=\'a"b\' name="foo">', 'L'];
    }

    private function nativeInput(string $html): \Dom\Element
    {
        return \Dom\HTMLDocument::createFromString('<!doctype html><body>'.$html.'</body>', 0)->querySelector('input');
    }

    private function classicInput(string $html): \DOMElement
    {
        $document = new \DOMDocument();
        $document->loadHTML('<!doctype html><body>'.$html.'</body>', \LIBXML_NOERROR);

        return $document->getElementsByTagName('input')->item(0);
    }
}
