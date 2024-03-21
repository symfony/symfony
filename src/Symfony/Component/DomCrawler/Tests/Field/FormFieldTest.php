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

use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\DomCrawler\Field\InputFormField;

class FormFieldTest extends FormFieldTestCase
{
    use ExpectDeprecationTrait;

    /**
     * @group legacy
     */
    public function testGetLabelIsDeprecated()
    {
        $this->expectDeprecation('Since symfony/dom-crawler 7.1: The "Symfony\Component\DomCrawler\Field\DomFormField::getLabel()" method is deprecated, use "Symfony\Component\DomCrawler\Field\DomFormField::getDomLabel()" instead.');

        $dom = new \DOMDocument();
        $dom->loadHTML('<html><form>
            <label for="foo">Foo label</label>
            <input type="text" id="foo" name="foo" value="foo" />
            <input type="submit" />
        </form></html>');

        $field = new InputFormField($dom->getElementById('foo'));
        $this->assertEquals('Foo label', $field->getLabel()->nodeValue, '->getLabel() returns the associated label');
    }

    public function testGetName()
    {
        $node = $this->createNode('input', '', ['type' => 'text', 'name' => 'name', 'value' => 'value']);
        $field = new InputFormField($node);

        $this->assertEquals('name', $field->getName(), '->getName() returns the name of the field');
    }

    public function testGetSetHasValue()
    {
        $node = $this->createNode('input', '', ['type' => 'text', 'name' => 'name', 'value' => 'value']);
        $field = new InputFormField($node);

        $this->assertEquals('value', $field->getValue(), '->getValue() returns the value of the field');

        $field->setValue('foo');
        $this->assertEquals('foo', $field->getValue(), '->setValue() sets the value of the field');

        $this->assertTrue($field->hasValue(), '->hasValue() always returns true');
    }

    public function testLabelReturnsNullIfNoneIsDefined()
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('<html><form><input type="text" id="foo" name="foo" value="foo" /><input type="submit" /></form></html>');

        $field = new InputFormField($dom->getElementById('foo'));
        $this->assertNull($field->getDomLabel(), '->getDomLabel() returns null if no label is defined');
    }

    public function testLabelIsAssignedByForAttribute()
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('<html><form>
            <label for="foo">Foo label</label>
            <input type="text" id="foo" name="foo" value="foo" />
            <input type="submit" />
        </form></html>');

        $field = new InputFormField($dom->getElementById('foo'));
        $this->assertEquals('Foo label', $field->getDomLabel()->textContent, '->getLabel() returns the associated label');
    }

    public function testLabelIsAssignedByParentingRelation()
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('<html><form>
            <label for="foo">Foo label<input type="text" id="foo" name="foo" value="foo" /></label>
            <input type="submit" />
        </form></html>');

        $field = new InputFormField($dom->getElementById('foo'));
        $this->assertEquals('Foo label', $field->getDomLabel()->textContent, '->getLabel() returns the parent label');
    }
}
