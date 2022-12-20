<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Field\FormField;
use Symfony\Component\DomCrawler\Field\InputFormField;
use Symfony\Component\DomCrawler\Field\TextareaFormField;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\DomCrawler\FormFieldRegistry;

class FormTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // Ensure that the private helper class FormFieldRegistry is loaded
        class_exists(Form::class);
    }

    public function testConstructorThrowsExceptionIfTheNodeHasNoFormAncestor()
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('
            <html>
                <input type="submit" />
                <form>
                    <input type="foo" />
                </form>
                <button />
            </html>
        ');

        $nodes = $dom->getElementsByTagName('input');

        try {
            new Form($nodes->item(0), 'http://example.com');
            self::fail('__construct() throws a \\LogicException if the node has no form ancestor');
        } catch (\LogicException $e) {
            self::assertTrue(true, '__construct() throws a \\LogicException if the node has no form ancestor');
        }

        try {
            new Form($nodes->item(1), 'http://example.com');
            self::fail('__construct() throws a \\LogicException if the input type is not submit, button, or image');
        } catch (\LogicException $e) {
            self::assertTrue(true, '__construct() throws a \\LogicException if the input type is not submit, button, or image');
        }

        $nodes = $dom->getElementsByTagName('button');

        try {
            new Form($nodes->item(0), 'http://example.com');
            self::fail('__construct() throws a \\LogicException if the node has no form ancestor');
        } catch (\LogicException $e) {
            self::assertTrue(true, '__construct() throws a \\LogicException if the node has no form ancestor');
        }
    }

    /**
     * @dataProvider constructorThrowsExceptionIfNoRelatedFormProvider
     *
     * __construct() should throw a \LogicException if the form attribute is invalid.
     */
    public function testConstructorThrowsExceptionIfNoRelatedForm(\DOMElement $node)
    {
        self::expectException(\LogicException::class);

        new Form($node, 'http://example.com');
    }

    public function constructorThrowsExceptionIfNoRelatedFormProvider()
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('
            <html>
                <form id="bar">
                    <input type="submit" form="nonexistent" />
                </form>
                <input type="text" form="nonexistent" />
                <button />
            </html>
        ');

        $nodes = $dom->getElementsByTagName('input');

        return [
            [$nodes->item(0)],
            [$nodes->item(1)],
        ];
    }

    public function testConstructorLoadsOnlyFieldsOfTheRightForm()
    {
        $dom = $this->createTestMultipleForm();

        $nodes = $dom->getElementsByTagName('form');
        $buttonElements = $dom->getElementsByTagName('button');

        $form = new Form($nodes->item(0), 'http://example.com');
        self::assertCount(3, $form->all());

        $form = new Form($buttonElements->item(1), 'http://example.com');
        self::assertCount(5, $form->all());
    }

    public function testConstructorHandlesFormAttribute()
    {
        $dom = $this->createTestHtml5Form();

        $inputElements = $dom->getElementsByTagName('input');
        $buttonElements = $dom->getElementsByTagName('button');

        // Tests if submit buttons are correctly assigned to forms
        $form1 = new Form($buttonElements->item(1), 'http://example.com');
        self::assertSame($dom->getElementsByTagName('form')->item(0), $form1->getFormNode(), 'HTML5-compliant form attribute handled incorrectly');

        $form1 = new Form($inputElements->item(3), 'http://example.com');
        self::assertSame($dom->getElementsByTagName('form')->item(0), $form1->getFormNode(), 'HTML5-compliant form attribute handled incorrectly');

        $form2 = new Form($buttonElements->item(0), 'http://example.com');
        self::assertSame($dom->getElementsByTagName('form')->item(1), $form2->getFormNode(), 'HTML5-compliant form attribute handled incorrectly');
    }

    public function testConstructorHandlesFormValues()
    {
        $dom = $this->createTestHtml5Form();

        $inputElements = $dom->getElementsByTagName('input');
        $buttonElements = $dom->getElementsByTagName('button');

        $form1 = new Form($inputElements->item(3), 'http://example.com');
        $form2 = new Form($buttonElements->item(0), 'http://example.com');

        // Tests if form values are correctly assigned to forms
        $values1 = [
            'apples' => ['1', '2'],
            'form_name' => 'form-1',
            'button_1' => 'Capture fields',
            'outer_field' => 'success',
        ];
        $values2 = [
            'oranges' => ['1', '2', '3'],
            'form_name' => 'form_2',
            'button_2' => '',
            'app_frontend_form_type_contact_form_type' => ['contactType' => '', 'firstName' => 'John'],
        ];

        self::assertEquals($values1, $form1->getPhpValues(), 'HTML5-compliant form attribute handled incorrectly');
        self::assertEquals($values2, $form2->getPhpValues(), 'HTML5-compliant form attribute handled incorrectly');
    }

    public function testMultiValuedFields()
    {
        $form = $this->createForm('<form>
            <input type="text" name="foo[4]" value="foo" disabled="disabled" />
            <input type="text" name="foo" value="foo" disabled="disabled" />
            <input type="text" name="foo[2]" value="foo" disabled="disabled" />
            <input type="text" name="foo[]" value="foo" disabled="disabled" />
            <input type="text" name="bar[foo][]" value="foo" disabled="disabled" />
            <input type="text" name="bar[foo][foobar]" value="foo" disabled="disabled" />
            <input type="submit" />
        </form>
        ');

        self::assertEquals(['foo[2]', 'foo[3]', 'bar[foo][0]', 'bar[foo][foobar]'], array_keys($form->all()));

        self::assertEquals('foo', $form->get('foo[2]')->getValue());
        self::assertEquals('foo', $form->get('foo[3]')->getValue());
        self::assertEquals('foo', $form->get('bar[foo][0]')->getValue());
        self::assertEquals('foo', $form->get('bar[foo][foobar]')->getValue());

        $form['foo[2]'] = 'bar';
        $form['foo[3]'] = 'bar';

        self::assertEquals('bar', $form->get('foo[2]')->getValue());
        self::assertEquals('bar', $form->get('foo[3]')->getValue());

        $form['bar'] = ['foo' => ['0' => 'bar', 'foobar' => 'foobar']];

        self::assertEquals('bar', $form->get('bar[foo][0]')->getValue());
        self::assertEquals('foobar', $form->get('bar[foo][foobar]')->getValue());
    }

    /**
     * @dataProvider provideInitializeValues
     */
    public function testConstructor($message, $form, $values)
    {
        $form = $this->createForm('<form>'.$form.'</form>');
        self::assertEquals($values, array_map(
            function ($field) {
                $class = \get_class($field);

                return [substr($class, strrpos($class, '\\') + 1), $field->getValue()];
            },
            $form->all()
        ), '->getDefaultValues() '.$message);
    }

    public function provideInitializeValues()
    {
        return [
            [
                'does not take into account input fields without a name attribute',
                '<input type="text" value="foo" />
                 <input type="submit" />',
                [],
            ],
            [
                'does not take into account input fields with an empty name attribute value',
                '<input type="text" name="" value="foo" />
                 <input type="submit" />',
                [],
            ],
            [
                'takes into account disabled input fields',
                '<input type="text" name="foo" value="foo" disabled="disabled" />
                 <input type="submit" />',
                ['foo' => ['InputFormField', 'foo']],
            ],
            [
                'appends the submitted button value',
                '<input type="submit" name="bar" value="bar" />',
                ['bar' => ['InputFormField', 'bar']],
            ],
            [
                'appends the submitted button value for Button element',
                '<button type="submit" name="bar" value="bar">Bar</button>',
                ['bar' => ['InputFormField', 'bar']],
            ],
            [
                'appends the submitted button value but not other submit buttons',
                '<input type="submit" name="bar" value="bar" />
                 <input type="submit" name="foobar" value="foobar" />',
                 ['foobar' => ['InputFormField', 'foobar']],
            ],
            [
                'turns an image input into x and y fields',
                '<input type="image" name="bar" />',
                ['bar.x' => ['InputFormField', '0'], 'bar.y' => ['InputFormField', '0']],
            ],
            [
                'returns textareas',
                '<textarea name="foo">foo</textarea>
                 <input type="submit" />',
                 ['foo' => ['TextareaFormField', 'foo']],
            ],
            [
                'returns inputs',
                '<input type="text" name="foo" value="foo" />
                 <input type="submit" />',
                 ['foo' => ['InputFormField', 'foo']],
            ],
            [
                'returns checkboxes',
                '<input type="checkbox" name="foo" value="foo" checked="checked" />
                 <input type="submit" />',
                 ['foo' => ['ChoiceFormField', 'foo']],
            ],
            [
                'returns not-checked checkboxes',
                '<input type="checkbox" name="foo" value="foo" />
                 <input type="submit" />',
                 ['foo' => ['ChoiceFormField', false]],
            ],
            [
                'returns radio buttons',
                '<input type="radio" name="foo" value="foo" />
                 <input type="radio" name="foo" value="bar" checked="bar" />
                 <input type="submit" />',
                 ['foo' => ['ChoiceFormField', 'bar']],
            ],
            [
                'returns file inputs',
                '<input type="file" name="foo" />
                 <input type="submit" />',
                 ['foo' => ['FileFormField', ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => 4, 'size' => 0]]],
            ],
        ];
    }

    public function testGetFormNode()
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('<html><form><input type="submit" /></form></html>');

        $form = new Form($dom->getElementsByTagName('input')->item(0), 'http://example.com');

        self::assertSame($dom->getElementsByTagName('form')->item(0), $form->getFormNode(), '->getFormNode() returns the form node associated with this form');
    }

    public function testGetFormNodeFromNamedForm()
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('<html><form name="my_form"><input type="submit" /></form></html>');

        $form = new Form($dom->getElementsByTagName('form')->item(0), 'http://example.com');

        self::assertSame($dom->getElementsByTagName('form')->item(0), $form->getFormNode(), '->getFormNode() returns the form node associated with this form');
    }

    public function testGetMethod()
    {
        $form = $this->createForm('<form><input type="submit" /></form>');
        self::assertEquals('GET', $form->getMethod(), '->getMethod() returns get if no method is defined');

        $form = $this->createForm('<form method="post"><input type="submit" /></form>');
        self::assertEquals('POST', $form->getMethod(), '->getMethod() returns the method attribute value of the form');

        $form = $this->createForm('<form method="post"><input type="submit" /></form>', 'put');
        self::assertEquals('PUT', $form->getMethod(), '->getMethod() returns the method defined in the constructor if provided');

        $form = $this->createForm('<form method="post"><input type="submit" /></form>', 'delete');
        self::assertEquals('DELETE', $form->getMethod(), '->getMethod() returns the method defined in the constructor if provided');

        $form = $this->createForm('<form method="post"><input type="submit" /></form>', 'patch');
        self::assertEquals('PATCH', $form->getMethod(), '->getMethod() returns the method defined in the constructor if provided');
    }

    public function testGetMethodWithOverride()
    {
        $form = $this->createForm('<form method="get"><input type="submit" formmethod="post" /></form>');
        self::assertEquals('POST', $form->getMethod(), '->getMethod() returns the method attribute value of the form');
    }

    public function testGetName()
    {
        $form = $this->createForm('<form name="foo"><input type="submit" /></form>');
        self::assertSame('foo', $form->getName());
    }

    public function testGetNameOnFormWithoutName()
    {
        $form = $this->createForm('<form><input type="submit" /></form>');
        self::assertSame('', $form->getName());
    }

    public function testGetSetValue()
    {
        $form = $this->createForm('<form><input type="text" name="foo" value="foo" /><input type="submit" /></form>');

        self::assertEquals('foo', $form['foo']->getValue(), '->offsetGet() returns the value of a form field');

        $form['foo'] = 'bar';

        self::assertEquals('bar', $form['foo']->getValue(), '->offsetSet() changes the value of a form field');

        try {
            $form['foobar'] = 'bar';
            self::fail('->offsetSet() throws an \InvalidArgumentException exception if the field does not exist');
        } catch (\InvalidArgumentException $e) {
            self::assertTrue(true, '->offsetSet() throws an \InvalidArgumentException exception if the field does not exist');
        }

        try {
            $form['foobar'];
            self::fail('->offsetSet() throws an \InvalidArgumentException exception if the field does not exist');
        } catch (\InvalidArgumentException $e) {
            self::assertTrue(true, '->offsetSet() throws an \InvalidArgumentException exception if the field does not exist');
        }
    }

    public function testDisableValidation()
    {
        $form = $this->createForm('<form>
            <select name="foo[bar]">
                <option value="bar">bar</option>
            </select>
            <select name="foo[baz]">
                <option value="foo">foo</option>
            </select>
            <input type="submit" />
        </form>');

        $form->disableValidation();

        $form['foo[bar]']->select('foo');
        $form['foo[baz]']->select('bar');
        self::assertEquals('foo', $form['foo[bar]']->getValue(), '->disableValidation() disables validation of all ChoiceFormField.');
        self::assertEquals('bar', $form['foo[baz]']->getValue(), '->disableValidation() disables validation of all ChoiceFormField.');
    }

    public function testOffsetUnset()
    {
        $form = $this->createForm('<form><input type="text" name="foo" value="foo" /><input type="submit" /></form>');
        unset($form['foo']);
        self::assertArrayNotHasKey('foo', $form, '->offsetUnset() removes a field');
    }

    public function testOffsetExists()
    {
        $form = $this->createForm('<form><input type="text" name="foo" value="foo" /><input type="submit" /></form>');

        self::assertArrayHasKey('foo', $form, '->offsetExists() return true if the field exists');
        self::assertArrayNotHasKey('bar', $form, '->offsetExists() return false if the field does not exist');
    }

    public function testGetValues()
    {
        $form = $this->createForm('<form><input type="text" name="foo[bar]" value="foo" /><input type="text" name="bar" value="bar" /><select multiple="multiple" name="baz[]"></select><input type="submit" /></form>');
        self::assertEquals(['foo[bar]' => 'foo', 'bar' => 'bar', 'baz' => []], $form->getValues(), '->getValues() returns all form field values');

        $form = $this->createForm('<form><input type="checkbox" name="foo" value="foo" /><input type="text" name="bar" value="bar" /><input type="submit" /></form>');
        self::assertEquals(['bar' => 'bar'], $form->getValues(), '->getValues() does not include not-checked checkboxes');

        $form = $this->createForm('<form><input type="file" name="foo" value="foo" /><input type="text" name="bar" value="bar" /><input type="submit" /></form>');
        self::assertEquals(['bar' => 'bar'], $form->getValues(), '->getValues() does not include file input fields');

        $form = $this->createForm('<form><input type="text" name="foo" value="foo" disabled="disabled" /><input type="text" name="bar" value="bar" /><input type="submit" /></form>');
        self::assertEquals(['bar' => 'bar'], $form->getValues(), '->getValues() does not include disabled fields');

        $form = $this->createForm('<form><template><input type="text" name="foo" value="foo" /></template><input type="text" name="bar" value="bar" /><input type="submit" /></form>');
        self::assertEquals(['bar' => 'bar'], $form->getValues(), '->getValues() does not include template fields');
        self::assertFalse($form->has('foo'));
    }

    public function testSetValues()
    {
        $form = $this->createForm('<form><input type="checkbox" name="foo" value="foo" checked="checked" /><input type="text" name="bar" value="bar" /><input type="submit" /></form>');
        $form->setValues(['foo' => false, 'bar' => 'foo']);
        self::assertEquals(['bar' => 'foo'], $form->getValues(), '->setValues() sets the values of fields');
    }

    public function testMultiselectSetValues()
    {
        $form = $this->createForm('<form><select multiple="multiple" name="multi"><option value="foo">foo</option><option value="bar">bar</option></select><input type="submit" /></form>');
        $form->setValues(['multi' => ['foo', 'bar']]);
        self::assertEquals(['multi' => ['foo', 'bar']], $form->getValues(), '->setValue() sets the values of select');
    }

    public function testGetPhpValues()
    {
        $form = $this->createForm('<form><input type="text" name="foo[bar]" value="foo" /><input type="text" name="bar" value="bar" /><input type="submit" /></form>');
        self::assertEquals(['foo' => ['bar' => 'foo'], 'bar' => 'bar'], $form->getPhpValues(), '->getPhpValues() converts keys with [] to arrays');

        $form = $this->createForm('<form><input type="text" name="fo.o[ba.r]" value="foo" /><input type="text" name="ba r" value="bar" /><input type="submit" /></form>');
        self::assertEquals(['fo.o' => ['ba.r' => 'foo'], 'ba r' => 'bar'], $form->getPhpValues(), '->getPhpValues() preserves periods and spaces in names');

        $form = $this->createForm('<form><input type="text" name="fo.o[ba.r][]" value="foo" /><input type="text" name="fo.o[ba.r][ba.z]" value="bar" /><input type="submit" /></form>');
        self::assertEquals(['fo.o' => ['ba.r' => ['foo', 'ba.z' => 'bar']]], $form->getPhpValues(), '->getPhpValues() preserves periods and spaces in names recursively');

        $form = $this->createForm('<form><input type="text" name="foo[bar]" value="foo" /><input type="text" name="bar" value="bar" /><select multiple="multiple" name="baz[]"></select><input type="submit" /></form>');
        self::assertEquals(['foo' => ['bar' => 'foo'], 'bar' => 'bar'], $form->getPhpValues(), "->getPhpValues() doesn't return empty values");
    }

    public function testGetFiles()
    {
        $form = $this->createForm('<form><input type="file" name="foo[bar]" /><input type="text" name="bar" value="bar" /><input type="submit" /></form>');
        self::assertEquals([], $form->getFiles(), '->getFiles() returns an empty array if method is get');

        $form = $this->createForm('<form method="post"><input type="file" name="foo[bar]" /><input type="text" name="bar" value="bar" /><input type="submit" /></form>');
        self::assertEquals(['foo[bar]' => ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => 4, 'size' => 0]], $form->getFiles(), '->getFiles() only returns file fields for POST');

        $form = $this->createForm('<form method="post"><input type="file" name="foo[bar]" /><input type="text" name="bar" value="bar" /><input type="submit" /></form>', 'put');
        self::assertEquals(['foo[bar]' => ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => 4, 'size' => 0]], $form->getFiles(), '->getFiles() only returns file fields for PUT');

        $form = $this->createForm('<form method="post"><input type="file" name="foo[bar]" /><input type="text" name="bar" value="bar" /><input type="submit" /></form>', 'delete');
        self::assertEquals(['foo[bar]' => ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => 4, 'size' => 0]], $form->getFiles(), '->getFiles() only returns file fields for DELETE');

        $form = $this->createForm('<form method="post"><input type="file" name="foo[bar]" /><input type="text" name="bar" value="bar" /><input type="submit" /></form>', 'patch');
        self::assertEquals(['foo[bar]' => ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => 4, 'size' => 0]], $form->getFiles(), '->getFiles() only returns file fields for PATCH');

        $form = $this->createForm('<form method="post"><input type="file" name="foo[bar]" disabled="disabled" /><input type="submit" /></form>');
        self::assertEquals([], $form->getFiles(), '->getFiles() does not include disabled file fields');

        $form = $this->createForm('<form method="post"><template><input type="file" name="foo"/></template><input type="text" name="bar" value="bar"/><input type="submit"/></form>');
        self::assertEquals([], $form->getFiles(), '->getFiles() does not include template file fields');
        self::assertFalse($form->has('foo'));
    }

    public function testGetPhpFiles()
    {
        $form = $this->createForm('<form method="post"><input type="file" name="foo[bar]" /><input type="text" name="bar" value="bar" /><input type="submit" /></form>');
        self::assertEquals(['foo' => ['bar' => ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => 4, 'size' => 0]]], $form->getPhpFiles(), '->getPhpFiles() converts keys with [] to arrays');

        $form = $this->createForm('<form method="post"><input type="file" name="f.o o[bar]" /><input type="text" name="bar" value="bar" /><input type="submit" /></form>');
        self::assertEquals(['f.o o' => ['bar' => ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => 4, 'size' => 0]]], $form->getPhpFiles(), '->getPhpFiles() preserves periods and spaces in names');

        $form = $this->createForm('<form method="post"><input type="file" name="f.o o[bar][ba.z]" /><input type="file" name="f.o o[bar][]" /><input type="text" name="bar" value="bar" /><input type="submit" /></form>');
        self::assertEquals(['f.o o' => ['bar' => ['ba.z' => ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => 4, 'size' => 0], ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => 4, 'size' => 0]]]], $form->getPhpFiles(), '->getPhpFiles() preserves periods and spaces in names recursively');

        $form = $this->createForm('<form method="post"><input type="file" name="foo[bar]" /><input type="text" name="bar" value="bar" /><input type="submit" /></form>');
        $files = $form->getPhpFiles();

        self::assertSame(0, $files['foo']['bar']['size'], '->getPhpFiles() converts size to int');
        self::assertSame(4, $files['foo']['bar']['error'], '->getPhpFiles() converts error to int');

        $form = $this->createForm('<form method="post"><input type="file" name="size[error]" /><input type="text" name="error" value="error" /><input type="submit" /></form>');
        self::assertEquals(['size' => ['error' => ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => 4, 'size' => 0]]], $form->getPhpFiles(), '->getPhpFiles() int conversion does not collide with file names');
    }

    /**
     * @dataProvider provideGetUriValues
     */
    public function testGetUri($message, $form, $values, $uri, $method = null)
    {
        $form = $this->createForm($form, $method);
        $form->setValues($values);

        self::assertEquals('http://example.com'.$uri, $form->getUri(), '->getUri() '.$message);
    }

    public function testGetBaseUri()
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('<form method="post" action="foo.php"><input type="text" name="bar" value="bar" /><input type="submit" /></form>');

        $nodes = $dom->getElementsByTagName('input');
        $form = new Form($nodes->item($nodes->length - 1), 'http://www.foo.com/');
        self::assertEquals('http://www.foo.com/foo.php', $form->getUri());
    }

    public function testGetUriWithAnchor()
    {
        $form = $this->createForm('<form action="#foo"><input type="submit" /></form>', null, 'http://example.com/id/123');

        self::assertEquals('http://example.com/id/123#foo', $form->getUri());
    }

    public function testGetUriActionAbsolute()
    {
        $formHtml = '<form id="login_form" action="https://login.foo.com/login.php?login_attempt=1" method="POST"><input type="text" name="foo" value="foo" /><input type="submit" /></form>';

        $form = $this->createForm($formHtml);
        self::assertEquals('https://login.foo.com/login.php?login_attempt=1', $form->getUri(), '->getUri() returns absolute URIs set in the action form');

        $form = $this->createForm($formHtml, null, 'https://login.foo.com');
        self::assertEquals('https://login.foo.com/login.php?login_attempt=1', $form->getUri(), '->getUri() returns absolute URIs set in the action form');

        $form = $this->createForm($formHtml, null, 'https://login.foo.com/bar/');
        self::assertEquals('https://login.foo.com/login.php?login_attempt=1', $form->getUri(), '->getUri() returns absolute URIs set in the action form');

        // The action URI haven't the same domain Host have an another domain as Host
        $form = $this->createForm($formHtml, null, 'https://www.foo.com');
        self::assertEquals('https://login.foo.com/login.php?login_attempt=1', $form->getUri(), '->getUri() returns absolute URIs set in the action form');

        $form = $this->createForm($formHtml, null, 'https://www.foo.com/bar/');
        self::assertEquals('https://login.foo.com/login.php?login_attempt=1', $form->getUri(), '->getUri() returns absolute URIs set in the action form');
    }

    public function testGetUriAbsolute()
    {
        $form = $this->createForm('<form action="foo"><input type="submit" /></form>', null, 'http://localhost/foo/');
        self::assertEquals('http://localhost/foo/foo', $form->getUri(), '->getUri() returns absolute URIs');

        $form = $this->createForm('<form action="/foo"><input type="submit" /></form>', null, 'http://localhost/foo/');
        self::assertEquals('http://localhost/foo', $form->getUri(), '->getUri() returns absolute URIs');
    }

    public function testGetUriWithOnlyQueryString()
    {
        $form = $this->createForm('<form action="?get=param"><input type="submit" /></form>', null, 'http://localhost/foo/bar');
        self::assertEquals('http://localhost/foo/bar?get=param', $form->getUri(), '->getUri() returns absolute URIs only if the host has been defined in the constructor');
    }

    public function testGetUriWithoutAction()
    {
        $form = $this->createForm('<form><input type="submit" /></form>', null, 'http://localhost/foo/bar');
        self::assertEquals('http://localhost/foo/bar', $form->getUri(), '->getUri() returns path if no action defined');
    }

    public function testGetUriWithActionOverride()
    {
        $form = $this->createForm('<form action="/foo"><button type="submit" formaction="/bar" /></form>', null, 'http://localhost/foo/');
        self::assertEquals('http://localhost/bar', $form->getUri(), '->getUri() returns absolute URIs');
    }

    public function provideGetUriValues()
    {
        return [
            [
                'returns the URI of the form',
                '<form action="/foo"><input type="submit" /></form>',
                [],
                '/foo',
            ],
            [
                'appends the form values if the method is get',
                '<form action="/foo"><input type="text" name="foo" value="foo" /><input type="submit" /></form>',
                [],
                '/foo?foo=foo',
            ],
            [
                'appends the form values and merges the submitted values',
                '<form action="/foo"><input type="text" name="foo" value="foo" /><input type="submit" /></form>',
                ['foo' => 'bar'],
                '/foo?foo=bar',
            ],
            [
                'does not append values if the method is post',
                '<form action="/foo" method="post"><input type="text" name="foo" value="foo" /><input type="submit" /></form>',
                [],
                '/foo',
            ],
            [
                'does not append values if the method is patch',
                '<form action="/foo" method="post"><input type="text" name="foo" value="foo" /><input type="submit" /></form>',
                [],
                '/foo',
                'PUT',
            ],
            [
                'does not append values if the method is delete',
                '<form action="/foo" method="post"><input type="text" name="foo" value="foo" /><input type="submit" /></form>',
                [],
                '/foo',
                'DELETE',
            ],
            [
                'does not append values if the method is put',
                '<form action="/foo" method="post"><input type="text" name="foo" value="foo" /><input type="submit" /></form>',
                [],
                '/foo',
                'PATCH',
            ],
            [
                'appends the form values to an existing query string',
                '<form action="/foo?bar=bar"><input type="text" name="foo" value="foo" /><input type="submit" /></form>',
                [],
                '/foo?bar=bar&foo=foo',
            ],
            [
                'replaces query values with the form values',
                '<form action="/foo?bar=bar"><input type="text" name="bar" value="foo" /><input type="submit" /></form>',
                [],
                '/foo?bar=foo',
            ],
            [
                'returns an empty URI if the action is empty',
                '<form><input type="submit" /></form>',
                [],
                '/',
            ],
            [
                'appends the form values even if the action is empty',
                '<form><input type="text" name="foo" value="foo" /><input type="submit" /></form>',
                [],
                '/?foo=foo',
            ],
            [
                'chooses the path if the action attribute value is a sharp (#)',
                '<form action="#" method="post"><input type="text" name="foo" value="foo" /><input type="submit" /></form>',
                [],
                '/#',
            ],
        ];
    }

    public function testHas()
    {
        $form = $this->createForm('<form method="post"><input type="text" name="bar" value="bar" /><input type="submit" /></form>');

        self::assertFalse($form->has('foo'), '->has() returns false if a field is not in the form');
        self::assertTrue($form->has('bar'), '->has() returns true if a field is in the form');
    }

    public function testRemove()
    {
        $form = $this->createForm('<form method="post"><input type="text" name="bar" value="bar" /><input type="submit" /></form>');
        $form->remove('bar');
        self::assertFalse($form->has('bar'), '->remove() removes a field');
    }

    public function testGet()
    {
        $form = $this->createForm('<form method="post"><input type="text" name="bar" value="bar" /><input type="submit" /></form>');

        self::assertInstanceOf(InputFormField::class, $form->get('bar'), '->get() returns the field object associated with the given name');

        try {
            $form->get('foo');
            self::fail('->get() throws an \InvalidArgumentException if the field does not exist');
        } catch (\InvalidArgumentException $e) {
            self::assertTrue(true, '->get() throws an \InvalidArgumentException if the field does not exist');
        }
    }

    public function testAll()
    {
        $form = $this->createForm('<form method="post"><input type="text" name="bar" value="bar" /><input type="submit" /></form>');

        $fields = $form->all();
        self::assertCount(1, $fields, '->all() return an array of form field objects');
        self::assertInstanceOf(InputFormField::class, $fields['bar'], '->all() return an array of form field objects');
    }

    public function testSubmitWithoutAFormButton()
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('
            <html>
                <form>
                    <input type="foo" />
                </form>
            </html>
        ');

        $nodes = $dom->getElementsByTagName('form');
        $form = new Form($nodes->item(0), 'http://example.com');
        self::assertSame($nodes->item(0), $form->getFormNode(), '->getFormNode() returns the form node associated with this form');
    }

    public function testTypeAttributeIsCaseInsensitive()
    {
        $form = $this->createForm('<form method="post"><input type="IMAGE" name="example" /></form>');
        self::assertTrue($form->has('example.x'), '->has() returns true if the image input was correctly turned into an x and a y fields');
        self::assertTrue($form->has('example.y'), '->has() returns true if the image input was correctly turned into an x and a y fields');
    }

    public function testFormFieldRegistryAcceptAnyNames()
    {
        $field = $this->getFormFieldMock('[t:dbt%3adate;]data_daterange_enddate_value');

        $registry = new FormFieldRegistry();
        $registry->add($field);
        self::assertEquals($field, $registry->get('[t:dbt%3adate;]data_daterange_enddate_value'));
        $registry->set('[t:dbt%3adate;]data_daterange_enddate_value', null);

        $form = $this->createForm('<form><input type="text" name="[t:dbt%3adate;]data_daterange_enddate_value" value="bar" /><input type="submit" /></form>');
        $form['[t:dbt%3adate;]data_daterange_enddate_value'] = 'bar';

        $registry->remove('[t:dbt%3adate;]data_daterange_enddate_value');
    }

    public function testFormFieldRegistryGetThrowAnExceptionWhenTheFieldDoesNotExist()
    {
        self::expectException(\InvalidArgumentException::class);
        $registry = new FormFieldRegistry();
        $registry->get('foo');
    }

    public function testFormFieldRegistrySetThrowAnExceptionWhenTheFieldDoesNotExist()
    {
        self::expectException(\InvalidArgumentException::class);
        $registry = new FormFieldRegistry();
        $registry->set('foo', null);
    }

    public function testFormFieldRegistryHasReturnsTrueWhenTheFQNExists()
    {
        $registry = new FormFieldRegistry();
        $registry->add($this->getFormFieldMock('foo[bar]'));

        self::assertTrue($registry->has('foo'));
        self::assertTrue($registry->has('foo[bar]'));
        self::assertFalse($registry->has('bar'));
        self::assertFalse($registry->has('foo[foo]'));
    }

    public function testFormRegistryFieldsCanBeRemoved()
    {
        $registry = new FormFieldRegistry();
        $registry->add($this->getFormFieldMock('foo'));
        $registry->remove('foo');
        self::assertFalse($registry->has('foo'));
    }

    public function testFormRegistrySupportsMultivaluedFields()
    {
        $registry = new FormFieldRegistry();
        $registry->add($this->getFormFieldMock('foo[]'));
        $registry->add($this->getFormFieldMock('foo[]'));
        $registry->add($this->getFormFieldMock('bar[5]'));
        $registry->add($this->getFormFieldMock('bar[]'));
        $registry->add($this->getFormFieldMock('bar[baz]'));

        self::assertEquals(['foo[0]', 'foo[1]', 'bar[5]', 'bar[6]', 'bar[baz]'], array_keys($registry->all()));
    }

    public function testFormRegistrySetValues()
    {
        $registry = new FormFieldRegistry();
        $registry->add($f2 = $this->getFormFieldMock('foo[2]'));
        $registry->add($f3 = $this->getFormFieldMock('foo[3]'));
        $registry->add($fbb = $this->getFormFieldMock('foo[bar][baz]'));

        $f2
            ->expects(self::exactly(2))
            ->method('setValue')
            ->with(2)
        ;

        $f3
            ->expects(self::exactly(2))
            ->method('setValue')
            ->with(3)
        ;

        $fbb
            ->expects(self::exactly(2))
            ->method('setValue')
            ->with('fbb')
        ;

        $registry->set('foo[2]', 2);
        $registry->set('foo[3]', 3);
        $registry->set('foo[bar][baz]', 'fbb');

        $registry->set('foo', [
            2 => 2,
            3 => 3,
            'bar' => [
                'baz' => 'fbb',
             ],
        ]);
    }

    public function testFormRegistrySetValueOnCompoundField()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Cannot set value on a compound field "foo[bar]".');
        $registry = new FormFieldRegistry();
        $registry->add($this->getFormFieldMock('foo[bar][baz]'));

        $registry->set('foo[bar]', 'fbb');
    }

    public function testFormRegistrySetArrayOnNotCompoundField()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Unreachable field "0"');
        $registry = new FormFieldRegistry();
        $registry->add($this->getFormFieldMock('bar'));

        $registry->set('bar', ['baz']);
    }

    public function testDifferentFieldTypesWithSameName()
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('
            <html>
                <body>
                    <form action="/">
                        <input type="hidden" name="option" value="default">
                        <input type="radio" name="option" value="A">
                        <input type="radio" name="option" value="B">
                        <input type="hidden" name="settings[1]" value="0">
                        <input type="checkbox" name="settings[1]" value="1" id="setting-1">
                        <button>klickme</button>
                    </form>
                </body>
            </html>
        ');
        $form = new Form($dom->getElementsByTagName('form')->item(0), 'http://example.com');

        self::assertInstanceOf(ChoiceFormField::class, $form->get('option'));
    }

    protected function getFormFieldMock($name, $value = null)
    {
        $field = self::getMockBuilder(FormField::class)
            ->setMethods(['getName', 'getValue', 'setValue', 'initialize'])
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $field
            ->expects(self::any())
            ->method('getName')
            ->willReturn($name)
        ;

        $field
            ->expects(self::any())
            ->method('getValue')
            ->willReturn($value)
        ;

        return $field;
    }

    protected function createForm($form, $method = null, $currentUri = null)
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<html>'.$form.'</html>');

        $xPath = new \DOMXPath($dom);
        $nodes = $xPath->query('//input | //button');

        if (null === $currentUri) {
            $currentUri = 'http://example.com/';
        }

        return new Form($nodes->item($nodes->length - 1), $currentUri, $method);
    }

    protected function createTestHtml5Form()
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('
        <html>
            <h1>Hello form</h1>
            <form id="form-1" action="" method="POST">
                <div><input type="checkbox" name="apples[]" value="1" checked /></div>
                <input form="form_2" type="checkbox" name="oranges[]" value="1" checked />
                <div><label></label><input form="form-1" type="hidden" name="form_name" value="form-1" /></div>
                <input form="form-1" type="submit" name="button_1" value="Capture fields" />
                <button form="form_2" type="submit" name="button_2">Submit form_2</button>
            </form>
            <input form="form-1" type="checkbox" name="apples[]" value="2" checked />
            <form id="form_2" action="" method="POST">
                <div><div><input type="checkbox" name="oranges[]" value="2" checked />
                <input type="checkbox" name="oranges[]" value="3" checked /></div></div>
                <input form="form_2" type="hidden" name="form_name" value="form_2" />
                <input form="form-1" type="hidden" name="outer_field" value="success" />
                <button form="form-1" type="submit" name="button_3">Submit from outside the form</button>
                <div>
                    <label for="app_frontend_form_type_contact_form_type_contactType">Message subject</label>
                    <div>
                        <select name="app_frontend_form_type_contact_form_type[contactType]" id="app_frontend_form_type_contact_form_type_contactType"><option selected="selected" value="">Please select subject</option><option id="1">Test type</option></select>
                    </div>
                </div>
                <div>
                    <label for="app_frontend_form_type_contact_form_type_firstName">Firstname</label>
                    <input type="text" name="app_frontend_form_type_contact_form_type[firstName]" value="John" id="app_frontend_form_type_contact_form_type_firstName"/>
                </div>
            </form>
            <button />
        </html>');

        return $dom;
    }

    protected function createTestMultipleForm()
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('
        <html>
            <h1>Hello form</h1>
            <form action="" method="POST">
                <div><input type="checkbox" name="apples[]" value="1" checked /></div>
                <input type="checkbox" name="oranges[]" value="1" checked />
                <div><label></label><input type="hidden" name="form_name" value="form-1" /></div>
                <input type="submit" name="button_1" value="Capture fields" />
                <button type="submit" name="button_2">Submit form_2</button>
            </form>
            <form action="" method="POST">
                <div><div><input type="checkbox" name="oranges[]" value="2" checked />
                <input type="checkbox" name="oranges[]" value="3" checked /></div></div>
                <input type="hidden" name="form_name" value="form_2" />
                <input type="hidden" name="outer_field" value="success" />
                <button type="submit" name="button_3">Submit from outside the form</button>
            </form>
            <button />
        </html>');

        return $dom;
    }

    public function testGetPhpValuesWithEmptyTextarea()
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('
            <html>
                <form>
                    <textarea name="example"></textarea>
                </form>
            </html>'
        );

        $nodes = $dom->getElementsByTagName('form');
        $form = new Form($nodes->item(0), 'http://example.com');
        self::assertEquals(['example' => ''], $form->getPhpValues());
    }

    public function testGetReturnTypes()
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('
            <html>
                <form>
                    <textarea name="foo[collection][0][bar]">item 0</textarea>
                </form>
            </html>'
        );

        $nodes = $dom->getElementsByTagName('form');
        $form = new Form($nodes->item(0), 'http://example.com');

        // FormField
        self::assertInstanceOf(TextareaFormField::class, $textareaFormField = $form->get('foo[collection][0][bar]'));

        // Array of FormField
        self::assertSame([
            'bar' => $textareaFormField,
        ], $form->get('foo[collection][0]'));

        // Array of array of FormField
        self::assertSame([
            [
                'bar' => $textareaFormField,
            ],
        ], $form->get('foo[collection]'));
    }
}
