<?php

namespace Symfony\Tests\Component\Form;

require_once __DIR__ . '/Fixtures/Author.php';
require_once __DIR__ . '/Fixtures/TestField.php';

use Symfony\Component\Form\Form;
use Symfony\Component\Form\Field;
use Symfony\Component\Form\HiddenField;
use Symfony\Component\Form\FieldGroup;
use Symfony\Component\Form\HtmlGenerator;
use Symfony\Component\Form\PropertyPath;
use Symfony\Component\File\UploadedFile;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Tests\Component\Form\Fixtures\Author;
use Symfony\Tests\Component\Form\Fixtures\TestField;

class FormTest_PreconfiguredForm extends Form
{
    protected function configure()
    {
        $this->add(new Field('firstName'));
    }
}

class TestSetDataBeforeConfigureForm extends Form
{
    protected $testCase;
    protected $object;

    public function __construct($testCase, $name, $object, $validator)
    {
        $this->testCase = $testCase;
        $this->object = $object;

        parent::__construct($name, $object, $validator);
    }

    protected function configure()
    {
        $this->testCase->assertEquals($this->object, $this->getData());
    }
}

class FormTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;
    protected $form;

    protected function setUp()
    {
        Form::disableDefaultCsrfProtection();
        Form::setDefaultCsrfSecret(null);
        $this->validator = $this->createMockValidator();
        $this->form = new Form('author', new Author(), $this->validator);
    }

    public function testConstructInitializesObject()
    {
        $this->assertEquals(new Author(), $this->form->getData());
    }

    public function testSetDataBeforeConfigure()
    {
        new TestSetDataBeforeConfigureForm($this, 'author', new Author(), $this->validator);
    }

    public function testIsCsrfProtected()
    {
        $this->assertFalse($this->form->isCsrfProtected());

        $this->form->enableCsrfProtection();

        $this->assertTrue($this->form->isCsrfProtected());

        $this->form->disableCsrfProtection();

        $this->assertFalse($this->form->isCsrfProtected());
    }

    public function testNoCsrfProtectionByDefault()
    {
        $form = new Form('author', new Author(), $this->validator);

        $this->assertFalse($form->isCsrfProtected());
    }

    public function testDefaultCsrfProtectionCanBeEnabled()
    {
        Form::enableDefaultCsrfProtection();
        $form = new Form('author', new Author(), $this->validator);

        $this->assertTrue($form->isCsrfProtected());
    }

    public function testGeneratedCsrfSecretByDefault()
    {
        $form = new Form('author', new Author(), $this->validator);

        $this->assertTrue(strlen($form->getCsrfSecret()) >= 32);
    }

    public function testDefaultCsrfSecretCanBeSet()
    {
        Form::setDefaultCsrfSecret('foobar');
        $form = new Form('author', new Author(), $this->validator);

        $this->assertEquals('foobar', $form->getCsrfSecret());
    }

    public function testDefaultCsrfFieldNameCanBeSet()
    {
        Form::setDefaultCsrfFieldName('foobar');
        $form = new Form('author', new Author(), $this->validator);

        $this->assertEquals('foobar', $form->getCsrfFieldName());
    }

    public function testCsrfProtectedFormsHaveExtraField()
    {
        $this->form->enableCsrfProtection();

        $this->assertTrue($this->form->has($this->form->getCsrfFieldName()));

        $field = $this->form->get($this->form->getCsrfFieldName());

        $this->assertTrue($field instanceof HiddenField);
        $this->assertGreaterThanOrEqual(32, strlen($field->getDisplayedData()));
    }

    public function testIsCsrfTokenValidPassesIfCsrfProtectionIsDisabled()
    {
        $this->form->bind(array());

        $this->assertTrue($this->form->isCsrfTokenValid());
    }

    public function testIsCsrfTokenValidPasses()
    {
        $this->form->enableCsrfProtection();

        $field = $this->form->getCsrfFieldName();
        $token = $this->form->get($field)->getDisplayedData();

        $this->form->bind(array($field => $token));

        $this->assertTrue($this->form->isCsrfTokenValid());
    }

    public function testIsCsrfTokenValidFails()
    {
        $this->form->enableCsrfProtection();

        $field = $this->form->getCsrfFieldName();

        $this->form->bind(array($field => 'foobar'));

        $this->assertFalse($this->form->isCsrfTokenValid());
    }

    public function testDefaultLocaleCanBeSet()
    {
        Form::setDefaultLocale('de-DE-1996');
        $form = new Form('author', new Author(), $this->validator);

        $field = $this->getMock('Symfony\Component\Form\Field', array(), array(), '', false, false);
        $field->expects($this->any())
                    ->method('getKey')
                    ->will($this->returnValue('firstName'));
        $field->expects($this->once())
                    ->method('setLocale')
                    ->with($this->equalTo('de-DE-1996'));

        $form->add($field);
    }

    public function testDefaultTranslatorCanBeSet()
    {
        $translator = $this->getMock('Symfony\Component\I18N\TranslatorInterface');
        Form::setDefaultTranslator($translator);
        $form = new Form('author', new Author(), $this->validator);

        $field = $this->getMock('Symfony\Component\Form\Field', array(), array(), '', false, false);
        $field->expects($this->any())
                    ->method('getKey')
                    ->will($this->returnValue('firstName'));
        $field->expects($this->once())
                    ->method('setTranslator')
                    ->with($this->equalTo($translator));

        $form->add($field);
    }

    public function testValidationGroupsCanBeSet()
    {
        $form = new Form('author', new Author(), $this->validator);

        $this->assertNull($form->getValidationGroups());
        $form->setValidationGroups('group');
        $this->assertEquals(array('group'), $form->getValidationGroups());
        $form->setValidationGroups(array('group1', 'group2'));
        $this->assertEquals(array('group1', 'group2'), $form->getValidationGroups());
        $form->setValidationGroups(null);
        $this->assertNull($form->getValidationGroups());
    }

    public function testBindUsesValidationGroups()
    {
        $field = $this->createMockField('firstName');
        $form = new Form('author', new Author(), $this->validator);
        $form->add($field);
        $form->setValidationGroups('group');

        $this->validator->expects($this->once())
                                        ->method('validate')
                                        ->with($this->equalTo($form), $this->equalTo(array('group')));

        $form->bind(array()); // irrelevant
    }

    public function testBindConvertsUploadedFiles()
    {
        $tmpFile = $this->createTempFile();
        $file = new UploadedFile($tmpFile, basename($tmpFile), 'text/plain', 100, 0);

        $field = $this->createMockField('file');
        $field->expects($this->once())
                    ->method('bind')
                    ->with($this->equalTo($file));

        $form = new Form('author', new Author(), $this->validator);
        $form->add($field);

        // test
        $form->bind(array(), array('file' => array(
            'name' => basename($tmpFile),
            'type' => 'text/plain',
            'tmp_name' => $tmpFile,
            'error' => 0,
            'size' => 100
        )));
    }

    public function testBindConvertsUploadedFilesWithPhpBug()
    {
        $tmpFile = $this->createTempFile();
        $file = new UploadedFile($tmpFile, basename($tmpFile), 'text/plain', 100, 0);

        $field = $this->createMockField('file');
        $field->expects($this->once())
                    ->method('bind')
                    ->with($this->equalTo($file));

        $form = new Form('author', new Author(), $this->validator);
        $form->add($field);

        // test
        $form->bind(array(), array(
            'name' => array(
                'file' => basename($tmpFile),
            ),
            'type' => array(
                'file' => 'text/plain',
            ),
            'tmp_name' => array(
                'file' => $tmpFile,
            ),
            'error' => array(
                'file' => 0,
            ),
            'size' => array(
                'file' => 100,
            ),
        ));
    }

    public function testBindConvertsNestedUploadedFilesWithPhpBug()
    {
        $tmpFile = $this->createTempFile();
        $file = new UploadedFile($tmpFile, basename($tmpFile), 'text/plain', 100, 0);

        $group = $this->getMock(
            'Symfony\Component\Form\FieldGroup',
            array('bind'),
            array('child', array('property_path' => null))
        );
        $group->expects($this->once())
                    ->method('bind')
                    ->with($this->equalTo(array('file' => $file)));

        $form = new Form('author', new Author(), $this->validator);
        $form->add($group);

        // test
        $form->bind(array(), array(
            'name' => array(
                'child' => array('file' => basename($tmpFile)),
            ),
            'type' => array(
                'child' => array('file' => 'text/plain'),
            ),
            'tmp_name' => array(
                'child' => array('file' => $tmpFile),
            ),
            'error' => array(
                'child' => array('file' => 0),
            ),
            'size' => array(
                'child' => array('file' => 100),
            ),
        ));
    }

    public function testMultipartFormsWithoutParentsRequireFiles()
    {
        $form = new Form('author', new Author(), $this->validator);
        $form->add($this->createMultipartMockField('file'));

        $this->setExpectedException('InvalidArgumentException');

        // should be given in second argument
        $form->bind(array('file' => 'test.txt'));
    }

    public function testMultipartFormsWithParentsRequireNoFiles()
    {
        $form = new Form('author', new Author(), $this->validator);
        $form->add($this->createMultipartMockField('file'));

        $form->setParent($this->createMockField('group'));

        // files are expected to be converted by the parent
        $form->bind(array('file' => 'test.txt'));
    }

    public function testRenderFormTagProducesValidXhtml()
    {
        $form = new Form('author', new Author(), $this->validator);

        $this->assertEquals('<form action="url" method="post">', $form->renderFormTag('url'));
    }

    public function testSetCharsetAdjustsGenerator()
    {
        $form = $this->getMock(
            'Symfony\Component\Form\Form',
            array('setGenerator'),
            array(),
            '',
            false // don't call original constructor
        );

        $form->expects($this->once())
                 ->method('setGenerator')
                 ->with($this->equalTo(new HtmlGenerator('iso-8859-1')));

        $form->setCharset('iso-8859-1');
    }

    protected function createMockField($key)
    {
        $field = $this->getMock(
            'Symfony\Component\Form\FieldInterface',
            array(),
            array(),
            '',
            false, // don't use constructor
            false  // don't call parent::__clone
        );

        $field->expects($this->any())
                    ->method('getKey')
                    ->will($this->returnValue($key));

        return $field;
    }

    protected function createMockFieldGroup($key)
    {
        $field = $this->getMock(
            'Symfony\Component\Form\FieldGroup',
            array(),
            array(),
            '',
            false, // don't use constructor
            false  // don't call parent::__clone
        );

        $field->expects($this->any())
                    ->method('getKey')
                    ->will($this->returnValue($key));

        return $field;
    }

    protected function createMultipartMockField($key)
    {
        $field = $this->createMockField($key);
        $field->expects($this->any())
                    ->method('isMultipart')
                    ->will($this->returnValue(true));

        return $field;
    }

    protected function createTempFile()
    {
        return tempnam(sys_get_temp_dir(), 'FormTest');
    }

    protected function createMockValidator()
    {
        return $this->getMock('Symfony\Component\Validator\ValidatorInterface');
    }
}
