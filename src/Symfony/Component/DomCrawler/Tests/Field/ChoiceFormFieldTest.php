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

use Symfony\Component\DomCrawler\Field\ChoiceFormField;

class ChoiceFormFieldTest extends FormFieldTestCase
{
    public function testInitialize()
    {
        $node = $this->createNode('textarea', '');
        try {
            new ChoiceFormField($node);
            $this->fail('->initialize() throws a \LogicException if the node is not an input or a select');
        } catch (\LogicException $e) {
            $this->assertTrue(true, '->initialize() throws a \LogicException if the node is not an input or a select');
        }

        $node = $this->createNode('input', '', ['type' => 'text']);
        try {
            new ChoiceFormField($node);
            $this->fail('->initialize() throws a \LogicException if the node is an input with a type different from checkbox or radio');
        } catch (\LogicException $e) {
            $this->assertTrue(true, '->initialize() throws a \LogicException if the node is an input with a type different from checkbox or radio');
        }
    }

    public function testGetType()
    {
        $node = $this->createNode('input', '', ['type' => 'radio', 'name' => 'name', 'value' => 'foo']);
        $field = new ChoiceFormField($node);

        $this->assertEquals('radio', $field->getType(), '->getType() returns radio for radio buttons');

        $node = $this->createNode('input', '', ['type' => 'checkbox', 'name' => 'name', 'value' => 'foo']);
        $field = new ChoiceFormField($node);

        $this->assertEquals('checkbox', $field->getType(), '->getType() returns radio for a checkbox');

        $node = $this->createNode('select', '');
        $field = new ChoiceFormField($node);

        $this->assertEquals('select', $field->getType(), '->getType() returns radio for a select');
    }

    public function testIsMultiple()
    {
        $node = $this->createNode('input', '', ['type' => 'radio', 'name' => 'name', 'value' => 'foo']);
        $field = new ChoiceFormField($node);

        $this->assertFalse($field->isMultiple(), '->isMultiple() returns false for radio buttons');

        $node = $this->createNode('input', '', ['type' => 'checkbox', 'name' => 'name', 'value' => 'foo']);
        $field = new ChoiceFormField($node);

        $this->assertFalse($field->isMultiple(), '->isMultiple() returns false for checkboxes');

        $node = $this->createNode('select', '');
        $field = new ChoiceFormField($node);

        $this->assertFalse($field->isMultiple(), '->isMultiple() returns false for selects without the multiple attribute');

        $node = $this->createNode('select', '', ['multiple' => 'multiple']);
        $field = new ChoiceFormField($node);

        $this->assertTrue($field->isMultiple(), '->isMultiple() returns true for selects with the multiple attribute');

        $node = $this->createNode('select', '', ['multiple' => '']);
        $field = new ChoiceFormField($node);

        $this->assertTrue($field->isMultiple(), '->isMultiple() returns true for selects with an empty multiple attribute');
    }

    public function testSelects()
    {
        $node = $this->createSelectNode(['foo' => false, 'bar' => false]);
        $field = new ChoiceFormField($node);

        $this->assertTrue($field->hasValue(), '->hasValue() returns true for selects');
        $this->assertEquals('foo', $field->getValue(), '->getValue() returns the first option if none are selected');
        $this->assertFalse($field->isMultiple(), '->isMultiple() returns false when no multiple attribute is defined');

        $node = $this->createSelectNode(['foo' => false, 'bar' => true]);
        $field = new ChoiceFormField($node);

        $this->assertEquals('bar', $field->getValue(), '->getValue() returns the selected option');

        $field->setValue('foo');
        $this->assertEquals('foo', $field->getValue(), '->setValue() changes the selected option');

        try {
            $field->setValue('foobar');
            $this->fail('->setValue() throws an \InvalidArgumentException if the value is not one of the selected options');
        } catch (\InvalidArgumentException $e) {
            $this->assertTrue(true, '->setValue() throws an \InvalidArgumentException if the value is not one of the selected options');
        }

        try {
            $field->setValue(['foobar']);
            $this->fail('->setValue() throws an \InvalidArgumentException if the value is an array');
        } catch (\InvalidArgumentException $e) {
            $this->assertTrue(true, '->setValue() throws an \InvalidArgumentException if the value is an array');
        }
    }

    public function testAddChoiceTracksSelectedDuplicateOption()
    {
        $field = new ChoiceFormField($this->createSelectNode(['duplicate' => true]));

        $field->addChoice($this->createNode('option', '', ['value' => 'duplicate', 'selected' => '', 'disabled' => '']));
        $this->assertSame('duplicate', $field->getValue());
        $this->assertTrue($field->isDisabled());

        $field->addChoice($this->createNode('option', '', ['value' => 'duplicate']));
        $this->assertTrue($field->isDisabled());

        $field->addChoice($this->createNode('option', '', ['value' => 'duplicate', 'selected' => '']));
        $this->assertSame('duplicate', $field->getValue());
        $this->assertFalse($field->isDisabled());

        $field->addChoice($this->createNode('option', '', ['value' => 'duplicate', 'disabled' => '']));
        $this->assertFalse($field->isDisabled());
    }

    public function testAddChoiceUsesExternalOptionText()
    {
        $field = new ChoiceFormField($this->createSelectNode(['initial' => true]));
        $document = new \DOMDocument();
        $option = $document->createElement('option');
        $option->setAttribute('selected', '');
        $option->appendChild($document->createTextNode(" \tfoo\n"));

        foreach (['', 'http://www.w3.org/1999/xhtml', 'http://www.w3.org/2000/svg'] as $namespace) {
            $script = $document->createElementNS($namespace, 'script');
            $script->appendChild($document->createTextNode('hidden'));
            $option->appendChild($script);
        }

        $script = $document->createElementNS('urn:example', 'script');
        $script->appendChild($document->createTextNode(' retained '));
        $option->appendChild($script);
        $option->appendChild($document->createTextNode("\rbar\f \t"));

        $field->addChoice($option);

        $this->assertSame('foo retained bar', $field->getValue());
    }

    public function testAddChoiceToSingleSelect()
    {
        $node = $this->createSelectNode(['foo' => false, 'bar' => false]);
        $field = new ChoiceFormField($node);

        $option = $this->createNode('option', 'Hello World', ['value' => 'hello_world']);
        $field->addChoice($option);

        $this->assertNotSame('hello_world', $field->getValue());
        $field->setValue('hello_world');
        $this->assertSame('hello_world', $field->getValue(), '->setValue() changes the selected option to dynamically added one');

        $option = $this->createNode('option', 'Mr. Robot', ['value' => 'mr_robot', 'selected' => true]);
        $field->addChoice($option);

        $this->assertSame('mr_robot', $field->getValue(), '->addChoice() changes the value to added choice if selected attribute is set');
    }

    public function testAddChoiceToMultipleSelectAppendsSelectedValue()
    {
        $node = $this->createSelectNode(['foo' => true, 'bar' => false], ['multiple' => 'multiple']);
        $field = new ChoiceFormField($node);

        $this->assertSame(['foo'], $field->getValue());

        $option = $this->createNode('option', '', ['value' => 'baz', 'selected' => true]);
        $field->addChoice($option);

        $this->assertSame(['foo', 'baz'], $field->getValue(), '->addChoice() appends to the value array for a multiple select');
    }

    public function testAddChoiceRejectsMismatchedTagForSelect()
    {
        $node = $this->createSelectNode(['foo' => false, 'bar' => false]);
        $field = new ChoiceFormField($node);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('expected an "option" tag, got "input"');

        $field->addChoice($this->createNode('input', '', ['value' => 'baz']));
    }

    public function testAddChoiceRejectsMismatchedTagForRadio()
    {
        $node = $this->createNode('input', '', ['type' => 'radio', 'name' => 'name', 'value' => 'foo']);
        $field = new ChoiceFormField($node);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('expected an "input" tag, got "option"');

        $field->addChoice($this->createNode('option', '', ['value' => 'bar']));
    }

    public function testSelectSizeUsesIntegerPrefix()
    {
        foreach (['1e2' => 'foo', '+1e2' => 'foo', '0001e2' => 'foo', '2junk' => null, '2.5' => null, '1.9' => 'foo', '-2' => 'foo', '+ 2' => 'foo', 'invalid' => 'foo', '' => 'foo'] as $size => $expected) {
            $field = new ChoiceFormField($this->createSelectNode(['foo' => false, 'bar' => false], ['size' => $size]));
            $this->assertSame($expected, $field->getValue(), $size);
        }
    }

    public function testSelectSizeSkipsOnlyAsciiWhitespace()
    {
        foreach ([" \t\n\f\r+2" => null, "\v2" => 'foo', "\u{00A0}2" => 'foo'] as $size => $expected) {
            $field = new ChoiceFormField($this->createSelectNode(['foo' => false, 'bar' => false], ['size' => $size]));
            $this->assertSame($expected, $field->getValue(), $size);
        }
    }

    public function testSelectWithEmptyBooleanAttribute()
    {
        $node = $this->createSelectNode(['foo' => false, 'bar' => true], [], '');
        $field = new ChoiceFormField($node);

        $this->assertEquals('bar', $field->getValue());
    }

    public function testSelectIsDisabled()
    {
        $node = $this->createSelectNode(['foo' => false, 'bar' => true], ['disabled' => 'disabled']);
        $field = new ChoiceFormField($node);

        $this->assertTrue($field->isDisabled(), '->isDisabled() returns true for selects with a disabled attribute');
    }

    public function testAddChoiceOnMultipleSelectKeepsExistingValues()
    {
        $node = $this->createSelectNode(['foo' => true, 'bar' => false], ['multiple' => 'multiple']);
        $field = new ChoiceFormField($node);

        $this->assertEquals(['foo'], $field->getValue());

        $document = new \DOMDocument();
        $newOption = $document->createElement('option', 'baz');
        $newOption->setAttribute('value', 'baz');
        $newOption->setAttribute('selected', 'selected');
        $field->addChoice($newOption);

        $this->assertEquals(['foo', 'baz'], $field->getValue());
    }

    public function testAddChoiceReadsTextFromAnotherDocument()
    {
        $field = new ChoiceFormField($this->createSelectNode(['foo' => true], ['multiple' => 'multiple']));
        $document = new \DOMDocument();
        $option = $document->createElement('option');
        $option->appendChild($document->createTextNode(" \tbar\n"));
        $option->appendChild($document->createElement('script', 'ignored'));
        $option->appendChild($document->createTextNode("\rbaz\f "));

        $field->addChoice($option);

        $this->assertSame(['foo', 'bar baz'], $field->availableOptionValues());
        $this->assertSame(['foo'], $field->getValue());

        $field->select('bar baz');

        $this->assertSame(['bar baz'], $field->getValue());
    }

    public function testMultipleSelects()
    {
        $node = $this->createSelectNode(['foo' => false, 'bar' => false], ['multiple' => 'multiple']);
        $field = new ChoiceFormField($node);

        $this->assertEquals([], $field->getValue(), '->setValue() returns an empty array if multiple is true and no option is selected');

        $field->setValue('foo');
        $this->assertEquals(['foo'], $field->getValue(), '->setValue() returns an array of options if multiple is true');

        $field->setValue('bar');
        $this->assertEquals(['bar'], $field->getValue(), '->setValue() returns an array of options if multiple is true');

        $field->setValue(['foo', 'bar']);
        $this->assertEquals(['foo', 'bar'], $field->getValue(), '->setValue() returns an array of options if multiple is true');

        $node = $this->createSelectNode(['foo' => true, 'bar' => true], ['multiple' => 'multiple']);
        $field = new ChoiceFormField($node);

        $this->assertEquals(['foo', 'bar'], $field->getValue(), '->getValue() returns the selected options');

        try {
            $field->setValue(['foobar']);
            $this->fail('->setValue() throws an \InvalidArgumentException if the value is not one of the options');
        } catch (\InvalidArgumentException $e) {
            $this->assertTrue(true, '->setValue() throws an \InvalidArgumentException if the value is not one of the options');
        }
    }

    public function testRadioButtons()
    {
        $node = $this->createNode('input', '', ['type' => 'radio', 'name' => 'name', 'value' => 'foo']);
        $field = new ChoiceFormField($node);
        $node = $this->createNode('input', '', ['type' => 'radio', 'name' => 'name', 'value' => 'bar']);
        $field->addChoice($node);

        $this->assertFalse($field->hasValue(), '->hasValue() returns false when no radio button is selected');
        $this->assertNull($field->getValue(), '->getValue() returns null if no radio button is selected');
        $this->assertFalse($field->isMultiple(), '->isMultiple() returns false for radio buttons');

        $node = $this->createNode('input', '', ['type' => 'radio', 'name' => 'name', 'value' => 'foo']);
        $field = new ChoiceFormField($node);
        $node = $this->createNode('input', '', ['type' => 'radio', 'name' => 'name', 'value' => 'bar', 'checked' => 'checked']);
        $field->addChoice($node);

        $this->assertTrue($field->hasValue(), '->hasValue() returns true when a radio button is selected');
        $this->assertEquals('bar', $field->getValue(), '->getValue() returns the value attribute of the selected radio button');

        $field->setValue('foo');
        $this->assertEquals('foo', $field->getValue(), '->setValue() changes the selected radio button');

        try {
            $field->setValue('foobar');
            $this->fail('->setValue() throws an \InvalidArgumentException if the value is not one of the radio button values');
        } catch (\InvalidArgumentException $e) {
            $this->assertTrue(true, '->setValue() throws an \InvalidArgumentException if the value is not one of the radio button values');
        }
    }

    public function testRadioButtonsWithEmptyBooleanAttribute()
    {
        $node = $this->createNode('input', '', ['type' => 'radio', 'name' => 'name', 'value' => 'foo']);
        $field = new ChoiceFormField($node);
        $node = $this->createNode('input', '', ['type' => 'radio', 'name' => 'name', 'value' => 'bar', 'checked' => '']);
        $field->addChoice($node);

        $this->assertTrue($field->hasValue(), '->hasValue() returns true when a radio button is selected');
        $this->assertEquals('bar', $field->getValue(), '->getValue() returns the value attribute of the selected radio button');
    }

    public function testRadioButtonIsDisabled()
    {
        $node = $this->createNode('input', '', ['type' => 'radio', 'name' => 'name', 'value' => 'foo', 'disabled' => 'disabled']);
        $field = new ChoiceFormField($node);
        $node = $this->createNode('input', '', ['type' => 'radio', 'name' => 'name', 'value' => 'bar']);
        $field->addChoice($node);
        $node = $this->createNode('input', '', ['type' => 'radio', 'name' => 'name', 'value' => 'baz', 'disabled' => '']);
        $field->addChoice($node);

        $field->select('foo');
        $this->assertEquals('foo', $field->getValue(), '->getValue() returns the value attribute of the selected radio button');
        $this->assertTrue($field->isDisabled());

        $field->select('bar');
        $this->assertEquals('bar', $field->getValue(), '->getValue() returns the value attribute of the selected radio button');
        $this->assertFalse($field->isDisabled());

        $field->select('baz');
        $this->assertEquals('baz', $field->getValue(), '->getValue() returns the value attribute of the selected radio button');
        $this->assertTrue($field->isDisabled());
    }

    public function testCheckboxes()
    {
        $node = $this->createNode('input', '', ['type' => 'checkbox', 'name' => 'name']);
        $field = new ChoiceFormField($node);

        $this->assertFalse($field->hasValue(), '->hasValue() returns false when the checkbox is not checked');
        $this->assertNull($field->getValue(), '->getValue() returns null if the checkbox is not checked');
        $this->assertFalse($field->isMultiple(), '->hasValue() returns false for checkboxes');
        try {
            $field->addChoice(new \DOMElement('input'));
            $this->fail('->addChoice() throws a \LogicException for checkboxes');
        } catch (\LogicException $e) {
            $this->assertTrue(true, '->initialize() throws a \LogicException for checkboxes');
        }

        $node = $this->createNode('input', '', ['type' => 'checkbox', 'name' => 'name', 'checked' => 'checked']);
        $field = new ChoiceFormField($node);

        $this->assertTrue($field->hasValue(), '->hasValue() returns true when the checkbox is checked');
        $this->assertEquals('on', $field->getValue(), '->getValue() returns 1 if the checkbox is checked and has no value attribute');

        $node = $this->createNode('input', '', ['type' => 'checkbox', 'name' => 'name', 'checked' => 'checked', 'value' => 'foo']);
        $field = new ChoiceFormField($node);

        $this->assertEquals('foo', $field->getValue(), '->getValue() returns the value attribute if the checkbox is checked');

        $node = $this->createNode('input', '', ['type' => 'checkbox', 'name' => 'name', 'checked' => 'checked', 'value' => 'foo']);
        $field = new ChoiceFormField($node);

        $field->setValue(false);
        $this->assertNull($field->getValue(), '->setValue() unchecks the checkbox is value is false');

        $field->setValue(true);
        $this->assertEquals('foo', $field->getValue(), '->setValue() checks the checkbox is value is true');

        try {
            $field->setValue('bar');
            $this->fail('->setValue() throws an \InvalidArgumentException if the value is not one from the value attribute');
        } catch (\InvalidArgumentException $e) {
            $this->assertTrue(true, '->setValue() throws an \InvalidArgumentException if the value is not one from the value attribute');
        }
    }

    public function testCheckboxWithEmptyBooleanAttribute()
    {
        $node = $this->createNode('input', '', ['type' => 'checkbox', 'name' => 'name', 'value' => 'foo', 'checked' => '']);
        $field = new ChoiceFormField($node);

        $this->assertTrue($field->hasValue(), '->hasValue() returns true when the checkbox is checked');
        $this->assertEquals('foo', $field->getValue());
    }

    public function testInitializeClearsCheckedRadio()
    {
        $document = new \DOMDocument();
        $document->loadHTML('<form><input type="radio" name="r" value="a" /><input type="radio" name="r" value="b" checked disabled /></form>');
        $nodes = $document->getElementsByTagName('input');
        $field = new class($nodes->item(0)) extends ChoiceFormField {
            public function reload(): void
            {
                $this->initialize();
            }
        };
        $field->addChoice($nodes->item(1));
        $this->assertSame('b', $field->getValue());

        $field->reload();

        $this->assertNull($field->getValue());
        $this->assertFalse($field->hasValue());
        $this->assertFalse($field->isDisabled());
    }

    public function testCheckboxIsDisabled()
    {
        $node = $this->createNode('input', '', ['type' => 'checkbox', 'name' => 'name', 'disabled' => '']);
        $field = new ChoiceFormField($node);

        $this->assertTrue($field->isDisabled(), '->isDisabled() returns true when the checkbox is disabled, even if it is not checked');

        $field->tick();
        $this->assertTrue($field->isDisabled(), '->isDisabled() returns true when the checkbox is disabled, even if it is checked');
    }

    public function testTick()
    {
        $node = $this->createSelectNode(['foo' => false, 'bar' => false]);
        $field = new ChoiceFormField($node);

        try {
            $field->tick();
            $this->fail('->tick() throws a \LogicException for select boxes');
        } catch (\LogicException $e) {
            $this->assertTrue(true, '->tick() throws a \LogicException for select boxes');
        }

        $node = $this->createNode('input', '', ['type' => 'checkbox', 'name' => 'name']);
        $field = new ChoiceFormField($node);
        $field->tick();
        $this->assertEquals('on', $field->getValue(), '->tick() ticks checkboxes');
    }

    public function testUntick()
    {
        $node = $this->createSelectNode(['foo' => false, 'bar' => false]);
        $field = new ChoiceFormField($node);

        try {
            $field->untick();
            $this->fail('->untick() throws a \LogicException for select boxes');
        } catch (\LogicException $e) {
            $this->assertTrue(true, '->untick() throws a \LogicException for select boxes');
        }

        $node = $this->createNode('input', '', ['type' => 'checkbox', 'name' => 'name', 'checked' => 'checked']);
        $field = new ChoiceFormField($node);
        $field->untick();
        $this->assertNull($field->getValue(), '->untick() unticks checkboxes');
    }

    public function testSelect()
    {
        $node = $this->createNode('input', '', ['type' => 'checkbox', 'name' => 'name', 'checked' => 'checked']);
        $field = new ChoiceFormField($node);
        $field->select(true);
        $this->assertEquals('on', $field->getValue(), '->select() changes the value of the field');
        $field->select(false);
        $this->assertNull($field->getValue(), '->select() changes the value of the field');

        $node = $this->createSelectNode(['foo' => false, 'bar' => false]);
        $field = new ChoiceFormField($node);
        $field->select('foo');
        $this->assertEquals('foo', $field->getValue(), '->select() changes the selected option');
    }

    public function testOptionWithNoValue()
    {
        $node = $this->createSelectNodeWithEmptyOption(['foo' => false, 'bar' => false]);
        $field = new ChoiceFormField($node);
        $this->assertEquals('foo', $field->getValue());

        $node = $this->createSelectNodeWithEmptyOption(['foo' => false, 'bar' => true]);
        $field = new ChoiceFormField($node);
        $this->assertEquals('bar', $field->getValue());
        $field->select('foo');
        $this->assertEquals('foo', $field->getValue(), '->select() changes the selected option');
    }

    public function testOptionWithNoValueUsesTextWithWhitespaceCollapsed()
    {
        $document = new \DOMDocument();
        $document->loadHTML("<select name=\"name\"><option>0</option><option selected>\n  Two \t\n  words  </option></select>");
        $field = new ChoiceFormField($document->getElementsByTagName('select')->item(0));

        $this->assertSame('Two words', $field->getValue());
        $this->assertSame(['0', 'Two words'], $field->availableOptionValues());
    }

    public function testSelectWithoutSelectedOptionTakesFirstEnabledOption()
    {
        $document = new \DOMDocument();
        $document->loadHTML('<select name="name"><option disabled>foo</option><optgroup disabled><option>bar</option></optgroup><optgroup><option>baz</option></optgroup></select>');
        $field = new ChoiceFormField($document->getElementsByTagName('select')->item(0));

        $this->assertSame('baz', $field->getValue(), '->getValue() returns the first option that is not disabled if none are selected');
        $this->assertFalse($field->isDisabled());

        $document = new \DOMDocument();
        $document->loadHTML('<select name="name" size="2"><option disabled>foo</option><option>bar</option></select>');
        $field = new ChoiceFormField($document->getElementsByTagName('select')->item(0));

        $this->assertNull($field->getValue(), '->getValue() returns null for an unselected list box');
    }

    public function testSelectDefaultWithSizeUsesHtmlIntegerPrefix()
    {
        foreach (['1e2', '+1e2', '0001e2', "\v2", "\u{00A0}2", '-2', '+ 2'] as $size) {
            $node = $this->createSelectNode(['foo' => false, 'bar' => false], ['size' => $size]);
            $node->firstChild->setAttribute('disabled', '');
            $field = new ChoiceFormField($node);

            $this->assertSame('bar', $field->getValue(), $size);
            $this->assertFalse($field->isDisabled());
        }
    }

    public function testOptionWithNoValueSkipsScriptText()
    {
        foreach ([
            '<option>A<script type="application/json">ignored</script>B</option>' => 'AB',
            '<option><script>ignored</script></option>' => '',
            '<option>A<span>B</span><script>ignored</script>C</option>' => 'ABC',
            '<option>A<span><script>ignored</script>B</span>C</option>' => 'ABC',
            '<option value="explicit">A<script>ignored</script>B</option>' => 'explicit',
            '<option value="">A<script>ignored</script>B</option>' => '',
        ] as $html => $expected) {
            $document = new \DOMDocument();
            $document->loadHTML('<select name="name">'.$html.'</select>');
            $field = new ChoiceFormField($document->getElementsByTagName('select')->item(0));

            $this->assertSame($expected, $field->getValue());
            $this->assertSame([$expected], $field->availableOptionValues());
        }
    }

    public function testOptionWithNoValueSkipsNamespacedScriptText()
    {
        $document = new \DOMDocument();
        $select = $document->appendChild($document->createElement('select'));
        $select->setAttribute('name', 'name');
        $option = $select->appendChild($document->createElement('option'));
        $option->appendChild($document->createTextNode('A'));
        $option->appendChild($document->createElementNS('http://www.w3.org/1999/xhtml', 'html:script', 'ignored'));
        $option->appendChild($document->createElementNS('http://www.w3.org/2000/svg', 'svg:script', 'ignored'));
        $option->appendChild($document->createElementNS('urn:other', 'other:script', 'kept'));
        $option->appendChild($document->createCDATASection('B'));
        $field = new ChoiceFormField($select);

        $this->assertSame('AkeptB', $field->getValue());
    }

    public function testDisableValidation()
    {
        $node = $this->createSelectNode(['foo' => false, 'bar' => false]);
        $field = new ChoiceFormField($node);
        $field->disableValidation();
        $field->setValue('foobar');
        $this->assertEquals('foobar', $field->getValue(), '->disableValidation() allows to set a value which is not in the selected options.');

        $node = $this->createSelectNode(['foo' => false, 'bar' => false], ['multiple' => 'multiple']);
        $field = new ChoiceFormField($node);
        $field->disableValidation();
        $field->setValue(['foobar']);
        $this->assertEquals(['foobar'], $field->getValue(), '->disableValidation() allows to set a value which is not in the selected options.');
    }

    public function testSelectWithEmptyValue()
    {
        $node = $this->createSelectNodeWithEmptyOption(['' => true, 'Female' => false, 'Male' => false]);
        $field = new ChoiceFormField($node);

        $this->assertSame('', $field->getValue());
    }

    protected function createSelectNode($options, $attributes = [], $selectedAttrText = 'selected')
    {
        $document = new \DOMDocument();
        $node = $document->createElement('select');

        foreach ($attributes as $name => $value) {
            $node->setAttribute($name, $value);
        }
        $node->setAttribute('name', 'name');

        foreach ($options as $value => $selected) {
            $option = $document->createElement('option', $value);
            $option->setAttribute('value', $value);
            if ($selected) {
                $option->setAttribute('selected', $selectedAttrText);
            }
            $node->appendChild($option);
        }

        return $node;
    }

    protected function createSelectNodeWithEmptyOption($options, $attributes = [])
    {
        $document = new \DOMDocument();
        $node = $document->createElement('select');

        foreach ($attributes as $name => $value) {
            $node->setAttribute($name, $value);
        }
        $node->setAttribute('name', 'name');

        foreach ($options as $value => $selected) {
            $option = $document->createElement('option', $value);
            if ($selected) {
                $option->setAttribute('selected', 'selected');
            }
            $node->appendChild($option);
        }

        return $node;
    }
}
