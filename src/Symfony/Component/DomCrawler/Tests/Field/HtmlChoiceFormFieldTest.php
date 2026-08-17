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
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Field\HtmlChoiceFormField;

class HtmlChoiceFormFieldTest extends FormFieldTestCase
{
    /**
     * The native and the classic fields must read the same document the same way.
     */
    #[DataProvider('provideFields')]
    public function testMatchesClassic(string $html, string $selector)
    {
        $native = new HtmlChoiceFormField($this->nativeNode($html, $selector));
        $classic = new ChoiceFormField($this->classicNode($html, $selector));

        $this->assertSame($classic->getType(), $native->getType());
        $this->assertSame($classic->isMultiple(), $native->isMultiple());
        $this->assertSame($classic->getValue(), $native->getValue());
        $this->assertSame($classic->hasValue(), $native->hasValue());
        $this->assertSame($classic->isDisabled(), $native->isDisabled());
        $this->assertSame($classic->availableOptionValues(), $native->availableOptionValues());
    }

    public static function provideFields(): iterable
    {
        yield 'select, no option selected' => ['<select name="n"><option value="foo">foo</option><option value="bar">bar</option></select>', 'select'];
        yield 'select, second option selected' => ['<select name="n"><option value="foo">foo</option><option value="bar" selected>bar</option></select>', 'select'];
        yield 'select with an empty selected attribute' => ['<select name="n"><option value="foo">foo</option><option value="bar" selected="">bar</option></select>', 'select'];
        yield 'select without options' => ['<select name="n"></select>', 'select'];
        yield 'select with a disabled attribute' => ['<select name="n" disabled><option value="foo">foo</option></select>', 'select'];
        yield 'select with a disabled option selected' => ['<select name="n"><option value="foo" disabled selected>foo</option><option value="bar">bar</option></select>', 'select'];
        yield 'multiple select' => ['<select name="n[]" multiple><option value="foo" selected>foo</option><option value="bar">bar</option></select>', 'select'];
        yield 'multiple select, nothing selected' => ['<select name="n[]" multiple><option value="foo">foo</option></select>', 'select'];
        yield 'options without a value attribute' => ['<select name="n"><option>Foo</option><option>Bar</option></select>', 'select'];
        yield 'option with an empty text' => ['<select name="n"><option></option><option>Bar</option></select>', 'select'];
        yield 'options in an optgroup' => ['<select name="n"><optgroup label="G"><option value="foo">Foo</option></optgroup></select>', 'select'];
        yield 'option holding markup' => ['<select name="n"><option value="foo"><b>bold</b> tail</option></select>', 'select'];
        yield 'option text needing normalization' => ['<select name="n"><option value="foo">  Foo   bar  </option></select>', 'select'];
        yield 'unchecked checkbox' => ['<input type="checkbox" name="n" value="foo">', 'input'];
        yield 'checked checkbox' => ['<input type="checkbox" name="n" value="foo" checked>', 'input'];
        yield 'checkbox without a value attribute' => ['<input type="checkbox" name="n" checked>', 'input'];
        yield 'disabled checkbox' => ['<input type="checkbox" name="n" value="foo" disabled>', 'input'];
        yield 'uppercased checkbox type' => ['<input type="CHECKBOX" name="n" value="foo">', 'input'];
        yield 'unchecked radio' => ['<input type="radio" name="n" value="foo">', 'input'];
        yield 'checked radio' => ['<input type="radio" name="n" value="foo" checked>', 'input'];
        yield 'disabled radio' => ['<input type="radio" name="n" value="foo" disabled checked>', 'input'];
    }

    public function testMultipleSelectDropsTheBracketsFromTheName()
    {
        $field = new HtmlChoiceFormField($this->nativeNode('<select name="n[]" multiple><option value="foo">foo</option></select>', 'select'));

        $this->assertSame('n', $field->getName());
    }

    /**
     * The option text is read from the text content, because the native DOM
     * reports a null nodeValue for an element.
     */
    public function testOptionTextIsReadFromTheTextContent()
    {
        $html = '<select name="n"><option value="foo"><b>bold</b> tail</option><option value="bar">Tom &amp; Jerry</option></select>';
        $field = new HtmlChoiceFormField($this->nativeNode($html, 'select'));

        $field->selectByText('bold tail');
        $this->assertSame('foo', $field->getValue());

        $field->selectByText('Tom & Jerry');
        $this->assertSame('bar', $field->getValue());
    }

    public function testOptionWithoutAValueAttributeFallsBackToItsText()
    {
        $field = new HtmlChoiceFormField($this->nativeNode('<select name="n"><option>Foo</option><option>Bar</option></select>', 'select'));

        $field->select('Bar');
        $this->assertSame('Bar', $field->getValue());
    }

    public function testOptionWithoutAValueOrATextHasAnEmptyValue()
    {
        $field = new HtmlChoiceFormField($this->nativeNode('<select name="n"><option></option><option>Bar</option></select>', 'select'));

        $this->assertSame(['', 'Bar'], $field->availableOptionValues());
        $this->assertSame('', $field->getValue());
    }

    public function testCheckboxWithoutAValueDefaultsToOn()
    {
        $field = new HtmlChoiceFormField($this->nativeNode('<input type="checkbox" name="n">', 'input'));

        $this->assertSame(['on'], $field->availableOptionValues());
    }

    public function testIsDisabledWhenTheSelectedOptionIsDisabled()
    {
        $field = new HtmlChoiceFormField($this->nativeNode('<select name="n"><option value="foo" disabled selected>foo</option><option value="bar">bar</option></select>', 'select'));

        $this->assertTrue($field->isDisabled());

        $field->setValue('bar');
        $this->assertFalse($field->isDisabled());
    }

    public function testSetValue()
    {
        $field = new HtmlChoiceFormField($this->nativeNode('<select name="n"><option value="foo">foo</option><option value="bar">bar</option></select>', 'select'));

        $this->assertSame('foo', $field->getValue());

        $field->setValue('bar');
        $this->assertSame('bar', $field->getValue());
    }

    public function testSetValueRejectsAnUnknownValue()
    {
        $field = new HtmlChoiceFormField($this->nativeNode('<select name="n"><option value="foo">foo</option></select>', 'select'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Input "n" cannot take "bar" as a value (possible values: "foo").');

        $field->setValue('bar');
    }

    public function testSetValueRejectsAnArrayOnASingleSelect()
    {
        $field = new HtmlChoiceFormField($this->nativeNode('<select name="n"><option value="foo">foo</option></select>', 'select'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The value for "n" cannot be an array.');

        $field->setValue(['foo']);
    }

    public function testSetValueOnAMultipleSelect()
    {
        $field = new HtmlChoiceFormField($this->nativeNode('<select name="n[]" multiple><option value="foo">foo</option><option value="bar">bar</option></select>', 'select'));

        $field->setValue(['foo', 'bar']);
        $this->assertSame(['foo', 'bar'], $field->getValue());

        $field->setValue('bar');
        $this->assertSame(['bar'], $field->getValue());
    }

    public function testTickAndUntick()
    {
        $field = new HtmlChoiceFormField($this->nativeNode('<input type="checkbox" name="n">', 'input'));

        $this->assertNull($field->getValue());
        $this->assertFalse($field->hasValue());

        $field->tick();
        $this->assertSame('on', $field->getValue());
        $this->assertTrue($field->hasValue());

        $field->untick();
        $this->assertNull($field->getValue());
        $this->assertFalse($field->hasValue());
    }

    public function testTickUsesTheValueAttribute()
    {
        $field = new HtmlChoiceFormField($this->nativeNode('<input type="checkbox" name="n" value="yes">', 'input'));
        $field->tick();

        $this->assertSame('yes', $field->getValue());
    }

    public function testTickRejectsANonCheckbox()
    {
        $field = new HtmlChoiceFormField($this->nativeNode('<input type="radio" name="n" value="foo">', 'input'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('You cannot tick "n" as it is not a checkbox (radio).');

        $field->tick();
    }

    public function testUntickRejectsANonCheckbox()
    {
        $field = new HtmlChoiceFormField($this->nativeNode('<select name="n"><option value="foo">foo</option></select>', 'select'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('You cannot untick "n" as it is not a checkbox (select).');

        $field->untick();
    }

    public function testAddChoiceToASelect()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><body><select name="n"><option value="foo">foo</option></select><select><option value="baz" selected>baz</option></select>', 0);
        $field = new HtmlChoiceFormField($document->querySelector('select'));

        $field->addChoice($document->querySelectorAll('option')[1]);

        $this->assertSame(['foo', 'baz'], $field->availableOptionValues());
        $this->assertSame('baz', $field->getValue());
    }

    public function testAddChoiceToARadio()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><body><input type="radio" name="n" value="foo"><input type="radio" name="n" value="bar" checked>', 0);
        $field = new HtmlChoiceFormField($document->querySelector('input'));

        $field->addChoice($document->querySelectorAll('input')[1]);

        $this->assertSame(['foo', 'bar'], $field->availableOptionValues());
        $this->assertSame('bar', $field->getValue());
    }

    public function testAddChoiceRejectsAMismatchedTag()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><body><select name="n"><option value="foo">foo</option></select><input value="bar">', 0);
        $field = new HtmlChoiceFormField($document->querySelector('select'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unable to add a choice for "n": expected an "option" tag, got "input".');

        $field->addChoice($document->querySelector('input'));
    }

    public function testAddChoiceRejectsACheckbox()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><body><input type="checkbox" name="n" value="foo"><input type="checkbox" value="bar">', 0);
        $field = new HtmlChoiceFormField($document->querySelector('input'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unable to add a choice for "n" as it is neither multiple, a radio button nor a select field (type is "checkbox").');

        $field->addChoice($document->querySelectorAll('input')[1]);
    }

    public function testSelectByText()
    {
        $field = new HtmlChoiceFormField($this->nativeNode('<select name="n"><option value="ok">  This  is ok  </option><option value="ko">This is ko</option></select>', 'select'));

        $field->selectByText('This is ok');
        $this->assertSame('ok', $field->getValue());

        $field->selectByText('This is ko');
        $this->assertSame('ko', $field->getValue());

        $field->selectByText("\tThis\nis  ok\n");
        $this->assertSame('ok', $field->getValue());
    }

    public function testSelectByTextRejectsAnUnknownText()
    {
        $field = new HtmlChoiceFormField($this->nativeNode('<select name="n"><option value="ok">Visible</option></select>', 'select'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Input "n" has no option with text "Unknown" (possible texts: "Visible").');

        $field->selectByText('Unknown');
    }

    public function testSelectByTextRejectsANonSelect()
    {
        $field = new HtmlChoiceFormField($this->nativeNode('<input type="checkbox" name="n" value="foo">', 'input'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('You cannot call selectByText() on "n" as it is not a select (checkbox).');

        $field->selectByText('foo');
    }

    public function testDisableValidation()
    {
        $field = new HtmlChoiceFormField($this->nativeNode('<select name="n"><option value="foo">foo</option></select>', 'select'));
        $field->disableValidation();
        $field->setValue('unknown');

        $this->assertSame('unknown', $field->getValue());
    }

    public function testInitializeRejectsAnUnsupportedTag()
    {
        $node = $this->createHtmlNode('textarea');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('An HtmlChoiceFormField can only be created from an input or select tag (textarea given).');

        new HtmlChoiceFormField($node);
    }

    public function testInitializeRejectsAnInputWithAnotherType()
    {
        $node = $this->createHtmlNode('input', '', ['type' => 'text']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('An HtmlChoiceFormField can only be created from an input tag with a type of checkbox or radio (given type is "text").');

        new HtmlChoiceFormField($node);
    }

    public function testInitializeRejectsAnInputWithoutAType()
    {
        $node = $this->createHtmlNode('input');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('An HtmlChoiceFormField can only be created from an input tag with a type of checkbox or radio (given type is "").');

        new HtmlChoiceFormField($node);
    }

    private function nativeNode(string $html, string $selector): \Dom\Element
    {
        return \Dom\HTMLDocument::createFromString('<!doctype html><body>'.$html, 0)->querySelector($selector);
    }

    private function classicNode(string $html, string $selector): \DOMElement
    {
        $document = new \DOMDocument();
        $document->loadHTML('<!doctype html><body>'.$html, \LIBXML_NOERROR);

        return $document->getElementsByTagName($selector)->item(0);
    }
}
