<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form;

require_once __DIR__ . '/Fixtures/Author.php';
require_once __DIR__ . '/Fixtures/TestField.php';

use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormContext;
use Symfony\Component\Form\Field;
use Symfony\Component\Form\HiddenField;
use Symfony\Component\Form\FieldGroup;
use Symfony\Component\Form\PropertyPath;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Tests\Component\Form\Fixtures\Author;
use Symfony\Tests\Component\Form\Fixtures\TestField;

class FormTest_PreconfiguredForm extends Form
{
    protected function configure()
    {
        $this->add(new Field('firstName'));

        parent::configure();
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

        parent::configure();
    }
}

class FormTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;
    protected $form;

    public static function setUpBeforeClass()
    {
        @session_start();
    }

    protected function setUp()
    {
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

    public function testNoCsrfProtectionByDefault()
    {
        $form = new Form('author', new Author(), $this->validator);

        $this->assertFalse($form->isCsrfProtected());
    }

    public function testCsrfProtectionCanBeEnabled()
    {
        $form = new Form('author', new Author(), $this->validator, array(
            'csrf_provider' => $this->createMockCsrfProvider(),
        ));

        $this->assertTrue($form->isCsrfProtected());
    }

    public function testCsrfFieldNameCanBeSet()
    {
        $form = new Form('author', new Author(), $this->validator, array(
            'csrf_provider' => $this->createMockCsrfProvider(),
            'csrf_field_name' => 'foobar',
        ));

        $this->assertEquals('foobar', $form->getCsrfFieldName());
    }

    public function testCsrfProtectedFormsHaveExtraField()
    {
        $provider = $this->createMockCsrfProvider();
        $provider->expects($this->once())
                ->method('generateCsrfToken')
                ->with($this->equalTo('Symfony\Component\Form\Form'))
                ->will($this->returnValue('ABCDEF'));

        $form = new Form('author', new Author(), $this->validator, array(
            'csrf_provider' => $provider,
        ));

        $this->assertTrue($form->has($this->form->getCsrfFieldName()));

        $field = $form->get($form->getCsrfFieldName());

        $this->assertTrue($field instanceof HiddenField);
        $this->assertEquals('ABCDEF', $field->getDisplayedData());
    }

    public function testIsCsrfTokenValidPassesIfCsrfProtectionIsDisabled()
    {
        $this->form->bind(array());

        $this->assertTrue($this->form->isCsrfTokenValid());
    }

    public function testIsCsrfTokenValidPasses()
    {
        $provider = $this->createMockCsrfProvider();
        $provider->expects($this->once())
                ->method('isCsrfTokenValid')
                ->with($this->equalTo('Symfony\Component\Form\Form'), $this->equalTo('ABCDEF'))
                ->will($this->returnValue(true));

        $form = new Form('author', new Author(), $this->validator, array(
            'csrf_provider' => $provider,
        ));

        $field = $form->getCsrfFieldName();

        $form->bind(array($field => 'ABCDEF'));

        $this->assertTrue($form->isCsrfTokenValid());
    }

    public function testIsCsrfTokenValidFails()
    {
        $provider = $this->createMockCsrfProvider();
        $provider->expects($this->once())
                ->method('isCsrfTokenValid')
                ->with($this->equalTo('Symfony\Component\Form\Form'), $this->equalTo('ABCDEF'))
                ->will($this->returnValue(false));

        $form = new Form('author', new Author(), $this->validator, array(
            'csrf_provider' => $provider,
        ));

        $field = $form->getCsrfFieldName();

        $form->bind(array($field => 'ABCDEF'));

        $this->assertFalse($form->isCsrfTokenValid());
    }

    public function testValidationGroupNullByDefault()
    {
        $this->assertNull($this->form->getValidationGroups());
    }

    public function testValidationGroupsCanBeSetToString()
    {
        $form = new Form('author', new Author(), $this->validator, array(
            'validation_groups' => 'group',
        ));

        $this->assertEquals(array('group'), $form->getValidationGroups());
    }

    public function testValidationGroupsCanBeSetToArray()
    {
        $form = new Form('author', new Author(), $this->validator, array(
            'validation_groups' => array('group1', 'group2'),
        ));

        $this->assertEquals(array('group1', 'group2'), $form->getValidationGroups());
    }

    public function testBindUsesValidationGroups()
    {
        $field = $this->createMockField('firstName');
        $form = new Form('author', new Author(), $this->validator, array(
            'validation_groups' => 'group',
        ));
        $form->add($field);

        $this->validator->expects($this->once())
                                        ->method('validate')
                                        ->with($this->equalTo($form), $this->equalTo(array('group')));

        $form->bind(array()); // irrelevant
    }

    public function testBindThrowsExceptionIfNoValidatorIsSet()
    {
        $field = $this->createMockField('firstName');
        $form = new Form('author', new Author());
        $form->add($field);

        $this->setExpectedException('Symfony\Component\Form\Exception\FormException');

        $form->bind(array()); // irrelevant
    }

    public function testBindRequest()
    {
        $values = array(
            'author' => array(
                'name' => 'Bernhard',
                'image' => array('filename' => 'foobar.png'),
            ),
        );
        $files = array(
            'author' => array(
                'error' => array('image' => array('file' => UPLOAD_ERR_OK)),
                'name' => array('image' => array('file' => 'upload.png')),
                'size' => array('image' => array('file' => 123)),
                'tmp_name' => array('image' => array('file' => 'abcdef.png')),
                'type' => array('image' => array('file' => 'image/png')),
            ),
        );

        $request = new Request(array(), $values, array(), array(), $files);

        $form = new Form('author', null, $this->createMockValidator());
        $form->add(new TestField('name'));
        $imageForm = new FieldGroup('image');
        $imageForm->add(new TestField('file'));
        $imageForm->add(new TestField('filename'));
        $form->add($imageForm);

        $form->bindRequest($request);

        $file = new UploadedFile('abcdef.png', 'upload.png', 'image/png', 123, UPLOAD_ERR_OK);

        $this->assertEquals('Bernhard', $form['name']->getData());
        $this->assertEquals('foobar.png', $form['image']['filename']->getData());
        $this->assertEquals($file, $form['image']['file']->getData());
    }

    public function testBindGlobals()
    {
        $_POST = array(
            'author' => array(
                'name' => 'Bernhard',
                'image' => array('filename' => 'foobar.png'),
            ),
        );
        $_FILES = array(
            'author' => array(
                'error' => array('image' => array('file' => UPLOAD_ERR_OK)),
                'name' => array('image' => array('file' => 'upload.png')),
                'size' => array('image' => array('file' => 123)),
                'tmp_name' => array('image' => array('file' => 'abcdef.png')),
                'type' => array('image' => array('file' => 'image/png')),
            ),
        );


        $form = new Form('author', null, $this->createMockValidator());
        $form->add(new TestField('name'));
        $imageForm = new FieldGroup('image');
        $imageForm->add(new TestField('file'));
        $imageForm->add(new TestField('filename'));
        $form->add($imageForm);

        $form->bindGlobals();

        $file = new UploadedFile('abcdef.png', 'upload.png', 'image/png', 123, UPLOAD_ERR_OK);

        $this->assertEquals('Bernhard', $form['name']->getData());
        $this->assertEquals('foobar.png', $form['image']['filename']->getData());
        $this->assertEquals($file, $form['image']['file']->getData());
    }

    public function testUpdateFromPropertyIsIgnoredIfFormHasObject()
    {
        $author = new Author();
        $author->child = new Author();
        $standaloneChild = new Author();

        $form = new Form('child', $standaloneChild);
        $form->updateFromProperty($author);

        // should not be $author->child!!
        $this->assertSame($standaloneChild, $form->getData());
    }

    public function testUpdateFromPropertyIsNotIgnoredIfFormHasNoObject()
    {
        $author = new Author();
        $author->child = new Author();

        $form = new Form('child');
        $form->updateFromProperty($author);

        // should not be $author->child!!
        $this->assertSame($author->child, $form->getData());
    }

    public function testUpdatePropertyIsIgnoredIfFormHasObject()
    {
        $author = new Author();
        $author->child = $child = new Author();
        $standaloneChild = new Author();

        $form = new Form('child', $standaloneChild);
        $form->updateProperty($author);

        // $author->child was not modified
        $this->assertSame($child, $author->child);
    }

    public function testUpdatePropertyIsNotIgnoredIfFormHasNoObject()
    {
        $author = new Author();
        $child = new Author();

        $form = new Form('child');
        $form->setData($child);
        $form->updateProperty($author);

        // $author->child was set
        $this->assertSame($child, $author->child);
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

    protected function createMockValidator()
    {
        return $this->getMock('Symfony\Component\Validator\ValidatorInterface');
    }

    protected function createMockCsrfProvider()
    {
        return $this->getMock('Symfony\Component\Form\CsrfProvider\CsrfProviderInterface');
    }
}
