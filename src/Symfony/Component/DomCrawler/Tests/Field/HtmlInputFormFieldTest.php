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

class HtmlInputFormFieldTest extends FormFieldTestCase
{
    public function testInitialize()
    {
        $node = $this->createHtmlNode('input', '', ['type' => 'text', 'name' => 'name', 'value' => 'value']);
        $field = new HtmlInputFormField($node);

        $this->assertSame('value', $field->getValue());
    }

    public function testInitializeWithoutAValueAttribute()
    {
        $node = $this->createHtmlNode('input', '', ['type' => 'text', 'name' => 'name']);
        $field = new HtmlInputFormField($node);

        $this->assertSame('', $field->getValue());
    }

    public function testInitializeFromAButton()
    {
        $node = $this->createHtmlNode('button', 'text', ['name' => 'name', 'value' => 'value']);
        $field = new HtmlInputFormField($node);

        $this->assertSame('value', $field->getValue());
    }

    #[DataProvider('provideRejectedNodes')]
    public function testInitializeRejectsUnsupportedNodes(string $tag, array $attributes, string $message)
    {
        $node = $this->createHtmlNode($tag, '', $attributes);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage($message);

        new HtmlInputFormField($node);
    }

    public static function provideRejectedNodes(): iterable
    {
        yield 'textarea' => ['textarea', [], 'An HtmlInputFormField can only be created from an input or button tag (textarea given).'];
        yield 'checkbox' => ['input', ['type' => 'checkbox'], 'Checkboxes should be instances of HtmlChoiceFormField.'];
        yield 'uppercased checkbox' => ['input', ['type' => 'CHECKBOX'], 'Checkboxes should be instances of HtmlChoiceFormField.'];
        yield 'file' => ['input', ['type' => 'file'], 'File inputs should be instances of HtmlFileFormField.'];
        yield 'uppercased file' => ['input', ['type' => 'FILE'], 'File inputs should be instances of HtmlFileFormField.'];
    }
}
