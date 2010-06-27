<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\DomCrawler\Field;

require_once __DIR__.'/FormFieldTestCase.php';

use Symfony\Components\DomCrawler\Field\FileFormField;

class FileFormFieldTest extends FormFieldTestCase
{
    public function testInitialize()
    {
        $node = $this->createNode('input', '', array('type' => 'file'));
        $field = new FileFormField($node);

        $this->assertEquals(array('name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0), $field->getValue(), '->initialize() sets the value of the field to no file uploaded');

        $node = $this->createNode('textarea', '');
        try {
            $field = new FileFormField($node);
            $this->fail('->initialize() throws a \LogicException if the node is not an input field');
        } catch (\LogicException $e) {
            $this->assertTrue(true, '->initialize() throws a \LogicException if the node is not an input field');
        }

        $node = $this->createNode('input', '', array('type' => 'text'));
        try {
            $field = new FileFormField($node);
            $this->fail('->initialize() throws a \LogicException if the node is not a file input field');
        } catch (\LogicException $e) {
            $this->assertTrue(true, '->initialize() throws a \LogicException if the node is not a file input field');
        }
    }

    public function testSetValue()
    {
        $node = $this->createNode('input', '', array('type' => 'file'));
        $field = new FileFormField($node);

        $field->setValue(null);
        $this->assertEquals(array('name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0), $field->getValue(), '->setValue() clears the uploaded file if the value is null');

        $field->setValue(__FILE__);
        $this->assertEquals(array('name' => 'FileFormFieldTest.php', 'type' => '', 'tmp_name' => __FILE__, 'error' => 0, 'size' => filesize(__FILE__)), $field->getValue(), '->setValue() sets the value to the given file');
    }

    public function testUpload()
    {
        $node = $this->createNode('input', '', array('type' => 'file'));
        $field = new FileFormField($node);

        $field->upload(null);
        $this->assertEquals(array('name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0), $field->getValue(), '->upload() clears the uploaded file if the value is null');

        $field->upload(__FILE__);
        $this->assertEquals(array('name' => 'FileFormFieldTest.php', 'type' => '', 'tmp_name' => __FILE__, 'error' => 0, 'size' => filesize(__FILE__)), $field->getValue(), '->upload() sets the value to the given file');
    }

    public function testSetErrorCode()
    {
        $node = $this->createNode('input', '', array('type' => 'file'));
        $field = new FileFormField($node);

        $field->setErrorCode(UPLOAD_ERR_FORM_SIZE);
        $value = $field->getValue();
        $this->assertEquals(UPLOAD_ERR_FORM_SIZE, $value['error'], '->setErrorCode() sets the file input field error code');

        try {
            $field->setErrorCode('foobar');
            $this->fail('->setErrorCode() throws a \InvalidArgumentException if the error code is not valid');
        } catch (\InvalidArgumentException $e) {
            $this->assertTrue(true, '->setErrorCode() throws a \InvalidArgumentException if the error code is not valid');
        }
    }
}
