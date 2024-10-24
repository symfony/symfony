<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler\Tests\NativeCrawler\Field;

use Symfony\Component\DomCrawler\NativeCrawler\Field\InputFormField;

/**
 * @requires PHP 8.4
 */
class FormFieldTest extends FormFieldTestCase
{
    public function testGetName()
    {
        $node = $this->createNode('input', ['type' => 'text', 'name' => 'name', 'value' => 'value']);
        $field = new InputFormField($node);

        $this->assertEquals('name', $field->getName(), '->getName() returns the name of the field');
    }

    public function testGetSetHasValue()
    {
        $node = $this->createNode('input', ['type' => 'text', 'name' => 'name', 'value' => 'value']);
        $field = new InputFormField($node);

        $this->assertEquals('value', $field->getValue(), '->getValue() returns the value of the field');

        $field->setValue('foo');
        $this->assertEquals('foo', $field->getValue(), '->setValue() sets the value of the field');

        $this->assertTrue($field->hasValue(), '->hasValue() always returns true');
    }

    public function testLabelReturnsNullIfNoneIsDefined()
    {
        $dom = \DOM\HTMLDocument::createFromString('<!DOCTYPE html><html><form><input type="text" id="foo" name="foo" value="foo"><input type="submit"></form></html>');

        $field = new InputFormField($dom->getElementById('foo'));
        $this->assertNull($field->getLabel(), '->getLabel() returns null if no label is defined');
    }

    public function testLabelIsAssignedByForAttribute()
    {
        $dom = \DOM\HTMLDocument::createFromString('<!DOCTYPE html><html><form>
            <label for="foo">Foo label</label>
            <input type="text" id="foo" name="foo" value="foo">
            <input type="submit">
        </form></html>', \DOM\HTML_NO_DEFAULT_NS);

        $field = new InputFormField($dom->getElementById('foo'));
        $this->assertEquals('Foo label', $field->getLabel()->textContent, '->getLabel() returns the associated label');
    }

    public function testLabelIsAssignedByParentingRelation()
    {
        $dom = \DOM\HTMLDocument::createFromString('<!DOCTYPE html><html><form>
            <label for="foo">Foo label<input type="text" id="foo" name="foo" value="foo"></label>
            <input type="submit">
        </form></html>', \DOM\HTML_NO_DEFAULT_NS);

        $field = new InputFormField($dom->getElementById('foo'));
        $this->assertEquals('Foo label', $field->getLabel()->textContent, '->getLabel() returns the parent label');
    }
}
