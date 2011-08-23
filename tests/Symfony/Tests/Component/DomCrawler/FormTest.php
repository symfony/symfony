<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\DomCrawler;

use Symfony\Component\DomCrawler\Form;

class FormTest extends \PHPUnit_Framework_TestCase
{
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
            $form = new Form($nodes->item(0), 'http://example.com');
            $this->fail('__construct() throws a \\LogicException if the node has no form ancestor');
        } catch (\LogicException $e) {
            $this->assertTrue(true, '__construct() throws a \\LogicException if the node has no form ancestor');
        }

        try {
            $form = new Form($nodes->item(1), 'http://example.com');
            $this->fail('__construct() throws a \\LogicException if the input type is not submit, button, or image');
        } catch (\LogicException $e) {
            $this->assertTrue(true, '__construct() throws a \\LogicException if the input type is not submit, button, or image');
        }

        $nodes = $dom->getElementsByTagName('button');

        try {
            $form = new Form($nodes->item(0), 'http://example.com');
            $this->fail('__construct() throws a \\LogicException if the input type is not submit, button, or image');
        } catch (\LogicException $e) {
            $this->assertTrue(true, '__construct() throws a \\LogicException if the input type is not submit, button, or image');
        }
    }

    /**
     * @dataProvider provideInitializeValues
     */
    public function testConstructor($message, $form, $values)
    {
        $form = $this->createForm('<form>'.$form.'</form>');
        $this->assertEquals($values, array_map(function ($field) { return array(get_class($field), $field->getValue()); }, $form->all()), '->getDefaultValues() '.$message);
    }

    public function provideInitializeValues()
    {
        return array(
            array(
                'does not take into account input fields without a name attribute',
                '<input type="text" value="foo" />
                 <input type="submit" />',
                array(),
            ),
            array(
                'takes into account disabled input fields',
                '<input type="text" name="foo" value="foo" disabled="disabled" />
                 <input type="submit" />',
                array('foo' => array('Symfony\\Component\\DomCrawler\\Field\\InputFormField', 'foo')),
            ),
            array(
                'appends the submitted button value',
                '<input type="submit" name="bar" value="bar" />',
                array('bar' => array('Symfony\\Component\\DomCrawler\\Field\\InputFormField', 'bar')),
            ),
            array(
                'appends the submitted button value but not other submit buttons',
                '<input type="submit" name="bar" value="bar" />
                 <input type="submit" name="foobar" value="foobar" />',
                 array('foobar' => array('Symfony\\Component\\DomCrawler\\Field\\InputFormField', 'foobar')),
            ),
            array(
                'returns textareas',
                '<textarea name="foo">foo</textarea>
                 <input type="submit" />',
                 array('foo' => array('Symfony\\Component\\DomCrawler\\Field\\TextareaFormField', 'foo')),
            ),
            array(
                'returns inputs',
                '<input type="text" name="foo" value="foo" />
                 <input type="submit" />',
                 array('foo' => array('Symfony\\Component\\DomCrawler\\Field\\InputFormField', 'foo')),
            ),
            array(
                'returns checkboxes',
                '<input type="checkbox" name="foo" value="foo" checked="checked" />
                 <input type="submit" />',
                 array('foo' => array('Symfony\\Component\\DomCrawler\\Field\\ChoiceFormField', 'foo')),
            ),
            array(
                'returns not-checked checkboxes',
                '<input type="checkbox" name="foo" value="foo" />
                 <input type="submit" />',
                 array('foo' => array('Symfony\\Component\\DomCrawler\\Field\\ChoiceFormField', false)),
            ),
            array(
                'returns radio buttons',
                '<input type="radio" name="foo" value="foo" />
                 <input type="radio" name="foo" value="bar" checked="bar" />
                 <input type="submit" />',
                 array('foo' => array('Symfony\\Component\\DomCrawler\\Field\\ChoiceFormField', 'bar')),
            ),
            array(
                'returns file inputs',
                '<input type="file" name="foo" />
                 <input type="submit" />',
                 array('foo' => array('Symfony\\Component\\DomCrawler\\Field\\FileFormField', array('name' => '', 'type' => '', 'tmp_name' => '', 'error' => 4, 'size' => 0))),
            ),
        );
    }

    public function testGetFormNode()
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('<html><form><input type="submit" /></form></html>');

        $form = new Form($dom->getElementsByTagName('input')->item(0), 'http://example.com');

        $this->assertSame($dom->getElementsByTagName('form')->item(0), $form->getFormNode(), '->getFormNode() returns the form node associated with this form');
    }

    public function testGetMethod()
    {
        $form = $this->createForm('<form><input type="submit" /></form>');
        $this->assertEquals('GET', $form->getMethod(), '->getMethod() returns get if no method is defined');

        $form = $this->createForm('<form method="post"><input type="submit" /></form>');
        $this->assertEquals('POST', $form->getMethod(), '->getMethod() returns the method attribute value of the form');

        $form = $this->createForm('<form method="post"><input type="submit" /></form>', 'put');
        $this->assertEquals('PUT', $form->getMethod(), '->getMethod() returns the method defined in the constructor if provided');
    }

    public function testGetSetValue()
    {
        $form = $this->createForm('<form><input type="text" name="foo" value="foo" /><input type="submit" /></form>');

        $this->assertEquals('foo', $form['foo']->getValue(), '->offsetGet() returns the value of a form field');

        $form['foo'] = 'bar';

        $this->assertEquals('bar', $form['foo']->getValue(), '->offsetSet() changes the value of a form field');

        try {
            $form['foobar'] = 'bar';
            $this->fail('->offsetSet() throws an \InvalidArgumentException exception if the field does not exist');
        } catch (\InvalidArgumentException $e) {
            $this->assertTrue(true, '->offsetSet() throws an \InvalidArgumentException exception if the field does not exist');
        }

        try {
            $form['foobar'];
            $this->fail('->offsetSet() throws an \InvalidArgumentException exception if the field does not exist');
        } catch (\InvalidArgumentException $e) {
            $this->assertTrue(true, '->offsetSet() throws an \InvalidArgumentException exception if the field does not exist');
        }
    }

    public function testOffsetUnset()
    {
        $form = $this->createForm('<form><input type="text" name="foo" value="foo" /><input type="submit" /></form>');
        unset($form['foo']);
        $this->assertFalse(isset($form['foo']), '->offsetUnset() removes a field');
    }

    public function testOffsetExists()
    {
        $form = $this->createForm('<form><input type="text" name="foo" value="foo" /><input type="submit" /></form>');

        $this->assertTrue(isset($form['foo']), '->offsetExists() return true if the field exists');
        $this->assertFalse(isset($form['bar']), '->offsetExists() return false if the field does not exist');
    }

    public function testGetValues()
    {
        $form = $this->createForm('<form><input type="text" name="foo[bar]" value="foo" /><input type="text" name="bar" value="bar" /><input type="submit" /></form>');
        $this->assertEquals(array('foo[bar]' => 'foo', 'bar' => 'bar'), $form->getValues(), '->getValues() returns all form field values');

        $form = $this->createForm('<form><input type="checkbox" name="foo" value="foo" /><input type="text" name="bar" value="bar" /><input type="submit" /></form>');
        $this->assertEquals(array('bar' => 'bar'), $form->getValues(), '->getValues() does not include not-checked checkboxes');

        $form = $this->createForm('<form><input type="file" name="foo" value="foo" /><input type="text" name="bar" value="bar" /><input type="submit" /></form>');
        $this->assertEquals(array('bar' => 'bar'), $form->getValues(), '->getValues() does not include file input fields');

        $form = $this->createForm('<form><input type="text" name="foo" value="foo" disabled="disabled" /><input type="text" name="bar" value="bar" /><input type="submit" /></form>');
        $this->assertEquals(array('bar' => 'bar'), $form->getValues(), '->getValues() does not include disabled fields');
    }

    public function testSetValues()
    {
        $form = $this->createForm('<form><input type="checkbox" name="foo" value="foo" checked="checked" /><input type="text" name="bar" value="bar" /><input type="submit" /></form>');
        $form->setValues(array('foo' => false, 'bar' => 'foo'));
        $this->assertEquals(array('bar' => 'foo'), $form->getValues(), '->setValues() sets the values of fields');
    }

    public function testGetPhpValues()
    {
        $form = $this->createForm('<form><input type="text" name="foo[bar]" value="foo" /><input type="text" name="bar" value="bar" /><input type="submit" /></form>');
        $this->assertEquals(array('foo' => array('bar' => 'foo'), 'bar' => 'bar'), $form->getPhpValues(), '->getPhpValues() converts keys with [] to arrays');
    }

    public function testGetFiles()
    {
        $form = $this->createForm('<form><input type="file" name="foo[bar]" /><input type="text" name="bar" value="bar" /><input type="submit" /></form>');
        $this->assertEquals(array(), $form->getFiles(), '->getFiles() returns an empty array if method is get');

        $form = $this->createForm('<form method="post"><input type="file" name="foo[bar]" /><input type="text" name="bar" value="bar" /><input type="submit" /></form>');
        $this->assertEquals(array('foo[bar]' => array('name' => '', 'type' => '', 'tmp_name' => '', 'error' => 4, 'size' => 0)), $form->getFiles(), '->getFiles() only returns file fields');

        $form = $this->createForm('<form method="post"><input type="file" name="foo[bar]" disabled="disabled" /><input type="submit" /></form>');
        $this->assertEquals(array(), $form->getFiles(), '->getFiles() does not include disabled file fields');
    }

    public function testGetPhpFiles()
    {
        $form = $this->createForm('<form method="post"><input type="file" name="foo[bar]" /><input type="text" name="bar" value="bar" /><input type="submit" /></form>');
        $this->assertEquals(array('foo' => array('bar' => array('name' => '', 'type' => '', 'tmp_name' => '', 'error' => 4, 'size' => 0))), $form->getPhpFiles(), '->getPhpFiles() converts keys with [] to arrays');
    }

    /**
     * @dataProvider provideGetUriValues
     */
    public function testGetUri($message, $form, $values, $uri)
    {
        $form = $this->createForm($form);
        $form->setValues($values);

        $this->assertEquals('http://example.com'.$uri, $form->getUri(), '->getUri() '.$message);
    }

    public function testGetUriActionAbsolute()
    {
        $formHtml='<form id="login_form" action="https://login.foo.com/login.php?login_attempt=1" method="POST"><input type="text" name="foo" value="foo" /><input type="submit" /></form>';

        $form = $this->createForm($formHtml);
        $this->assertEquals('https://login.foo.com/login.php?login_attempt=1', $form->getUri(), '->getUri() returns absolute URIs set in the action form');

        $form = $this->createForm($formHtml, null, 'https://login.foo.com');
        $this->assertEquals('https://login.foo.com/login.php?login_attempt=1', $form->getUri(), '->getUri() returns absolute URIs set in the action form');

        $form = $this->createForm($formHtml, null, 'https://login.foo.com/bar/');
        $this->assertEquals('https://login.foo.com/login.php?login_attempt=1', $form->getUri(), '->getUri() returns absolute URIs set in the action form');

        // The action URI haven't the same domain Host have an another domain as Host
        $form = $this->createForm($formHtml, null, 'https://www.foo.com');
        $this->assertEquals('https://login.foo.com/login.php?login_attempt=1', $form->getUri(), '->getUri() returns absolute URIs set in the action form');

        $form = $this->createForm($formHtml, null, 'https://www.foo.com/bar/');
        $this->assertEquals('https://login.foo.com/login.php?login_attempt=1', $form->getUri(), '->getUri() returns absolute URIs set in the action form');
    }

    public function testGetUriAbsolute()
    {
        $form = $this->createForm('<form action="foo"><input type="submit" /></form>', null, 'http://localhost/foo/');
        $this->assertEquals('http://localhost/foo/foo', $form->getUri(), '->getUri() returns absolute URIs');

        $form = $this->createForm('<form action="/foo"><input type="submit" /></form>', null, 'http://localhost/foo/');
        $this->assertEquals('http://localhost/foo', $form->getUri(), '->getUri() returns absolute URIs');
    }

    public function testGetUriWithOnlyQueryString()
    {
        $form = $this->createForm('<form action="?get=param"><input type="submit" /></form>', null, 'http://localhost/foo/bar');
        $this->assertEquals('http://localhost/foo/bar?get=param', $form->getUri(), '->getUri() returns absolute URIs only if the host has been defined in the constructor');
    }

    public function testGetUriWithoutAction()
    {
        $form = $this->createForm('<form><input type="submit" /></form>', null, 'http://localhost/foo/bar');
        $this->assertEquals('http://localhost/foo/bar', $form->getUri(), '->getUri() returns path if no action defined');
    }

    public function provideGetUriValues()
    {
        return array(
            array(
                'returns the URI of the form',
                '<form action="/foo"><input type="submit" /></form>',
                array(),
                '/foo'
            ),
            array(
                'appends the form values if the method is get',
                '<form action="/foo"><input type="text" name="foo" value="foo" /><input type="submit" /></form>',
                array(),
                '/foo?foo=foo'
            ),
            array(
                'appends the form values and merges the submitted values',
                '<form action="/foo"><input type="text" name="foo" value="foo" /><input type="submit" /></form>',
                array('foo' => 'bar'),
                '/foo?foo=bar'
            ),
            array(
                'does not append values if the method is post',
                '<form action="/foo" method="post"><input type="text" name="foo" value="foo" /><input type="submit" /></form>',
                array(),
                '/foo'
            ),
            array(
                'appends the form values to an existing query string',
                '<form action="/foo?bar=bar"><input type="text" name="foo" value="foo" /><input type="submit" /></form>',
                array(),
                '/foo?bar=bar&foo=foo'
            ),
            array(
                'returns an empty URI if the action is empty',
                '<form><input type="submit" /></form>',
                array(),
                '/',
            ),
            array(
                'appends the form values even if the action is empty',
                '<form><input type="text" name="foo" value="foo" /><input type="submit" /></form>',
                array(),
                '/?foo=foo',
            ),
            array(
                'chooses the path if the action attribute value is a sharp (#)',
                '<form action="#" method="post"><input type="text" name="foo" value="foo" /><input type="submit" /></form>',
                array(),
                '/#',
            ),
        );
    }

    public function testHas()
    {
        $form = $this->createForm('<form method="post"><input type="text" name="bar" value="bar" /><input type="submit" /></form>');

        $this->assertFalse($form->has('foo'), '->has() returns false if a field is not in the form');
        $this->assertTrue($form->has('bar'), '->has() returns true if a field is in the form');
    }

    public function testRemove()
    {
        $form = $this->createForm('<form method="post"><input type="text" name="bar" value="bar" /><input type="submit" /></form>');
        $form->remove('bar');
        $this->assertFalse($form->has('bar'), '->remove() removes a field');
    }

    public function testGet()
    {
        $form = $this->createForm('<form method="post"><input type="text" name="bar" value="bar" /><input type="submit" /></form>');

        $this->assertEquals('Symfony\\Component\\DomCrawler\\Field\\InputFormField', get_class($form->get('bar')), '->get() returns the field object associated with the given name');

        try {
            $form->get('foo');
            $this->fail('->get() throws an \InvalidArgumentException if the field does not exist');
        } catch (\InvalidArgumentException $e) {
            $this->assertTrue(true, '->get() throws an \InvalidArgumentException if the field does not exist');
        }
    }

    public function testAll()
    {
        $form = $this->createForm('<form method="post"><input type="text" name="bar" value="bar" /><input type="submit" /></form>');

        $fields = $form->all();
        $this->assertEquals(1, count($fields), '->all() return an array of form field objects');
        $this->assertEquals('Symfony\\Component\\DomCrawler\\Field\\InputFormField', get_class($fields['bar']), '->all() return an array of form field objects');
    }

    public function testBase()
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('<form method="post" action="foo.php"><input type="text" name="bar" value="bar" /><input type="submit" /></form>');

        $nodes = $dom->getElementsByTagName('input');
        $form = new Form($nodes->item($nodes->length - 1), 'http://www.foo.com/');
        $this->assertEquals('http://www.foo.com/foo.php', $form->getUri());
    }

    public function testUriWithAnchor()
    {
        $form = $this->createForm('<form action="#foo"><input type="submit" /></form>', null, 'http://example.com/id/123');

        $this->assertEquals('http://example.com/id/123#foo', $form->getUri());
    }

    protected function createForm($form, $method = null, $currentUri = null)
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('<html>'.$form.'</html>');

        $nodes = $dom->getElementsByTagName('input');

        if (null === $currentUri) {
            $currentUri = 'http://example.com/';
        }

        return new Form($nodes->item($nodes->length - 1), $currentUri, $method);
    }
}
