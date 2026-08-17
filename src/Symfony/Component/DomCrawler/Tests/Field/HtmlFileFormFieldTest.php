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
use Symfony\Component\DomCrawler\Field\HtmlFileFormField;

class HtmlFileFormFieldTest extends FormFieldTestCase
{
    public function testInitialize()
    {
        $node = $this->createHtmlNode('input', '', ['type' => 'file', 'name' => 'name']);
        $field = new HtmlFileFormField($node);

        $this->assertSame(['name' => '', 'type' => '', 'tmp_name' => '', 'error' => \UPLOAD_ERR_NO_FILE, 'size' => 0], $field->getValue());
    }

    public function testInitializeRejectsANonInputNode()
    {
        $node = $this->createHtmlNode('textarea');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('An HtmlFileFormField can only be created from an input tag (textarea given).');

        new HtmlFileFormField($node);
    }

    public function testInitializeRejectsAnInputWithAnotherType()
    {
        $node = $this->createHtmlNode('input', '', ['type' => 'text']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('An HtmlFileFormField can only be created from an input tag with a type of file (given type is "text").');

        new HtmlFileFormField($node);
    }

    public function testInitializeRejectsAnInputWithoutAType()
    {
        $node = $this->createHtmlNode('input');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('An HtmlFileFormField can only be created from an input tag with a type of file (given type is "").');

        new HtmlFileFormField($node);
    }

    #[DataProvider('provideSetValueMethods')]
    public function testSetValue(string $method)
    {
        $node = $this->createHtmlNode('input', '', ['type' => 'file', 'name' => 'name']);
        $field = new HtmlFileFormField($node);

        $field->$method(null);
        $this->assertSame(['name' => '', 'type' => '', 'tmp_name' => '', 'error' => \UPLOAD_ERR_NO_FILE, 'size' => 0], $field->getValue());

        $field->$method(__FILE__);
        $value = $field->getValue();

        $this->assertSame(basename(__FILE__), $value['name']);
        $this->assertSame('', $value['type']);
        $this->assertFileExists($value['tmp_name']);
        $this->assertSame(\UPLOAD_ERR_OK, $value['error']);
        $this->assertSame(filesize(__FILE__), $value['size']);
        $this->assertSame('php', pathinfo($value['tmp_name'], \PATHINFO_EXTENSION));
    }

    public static function provideSetValueMethods(): iterable
    {
        yield 'setValue' => ['setValue'];
        yield 'upload' => ['upload'];
    }

    public function testSetErrorCode()
    {
        $node = $this->createHtmlNode('input', '', ['type' => 'file', 'name' => 'name']);
        $field = new HtmlFileFormField($node);

        $field->setErrorCode(\UPLOAD_ERR_FORM_SIZE);
        $this->assertSame(\UPLOAD_ERR_FORM_SIZE, $field->getValue()['error']);
    }

    public function testSetErrorCodeRejectsAnUnknownCode()
    {
        $node = $this->createHtmlNode('input', '', ['type' => 'file', 'name' => 'name']);
        $field = new HtmlFileFormField($node);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The error code "12345" is not valid.');

        $field->setErrorCode(12345);
    }

    public function testSetFilePath()
    {
        $node = $this->createHtmlNode('input', '', ['type' => 'file', 'name' => 'name']);
        $field = new HtmlFileFormField($node);
        $field->setFilePath(__FILE__);

        $this->assertSame(__FILE__, $field->getValue());
    }
}
