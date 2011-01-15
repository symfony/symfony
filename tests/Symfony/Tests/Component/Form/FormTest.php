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
use Symfony\Component\Form\FormConfiguration;
use Symfony\Component\Form\Field;
use Symfony\Component\Form\HiddenField;
use Symfony\Component\Form\FieldGroup;
use Symfony\Component\Form\PropertyPath;
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
        FormConfiguration::disableDefaultCsrfProtection();
        FormConfiguration::setDefaultCsrfSecrets(array());
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
        FormConfiguration::enableDefaultCsrfProtection();
        $form = new Form('author', new Author(), $this->validator);

        $this->assertTrue($form->isCsrfProtected());
    }

    public function testGeneratedCsrfSecretByDefault()
    {
        $form = new Form('author', new Author(), $this->validator);
        $form->enableCsrfProtection();

        $this->assertTrue(strlen($form->getCsrfSecret()) >= 32);
    }

    public function testDefaultCsrfSecretsCanBeAdded()
    {
        FormConfiguration::addDefaultCsrfSecret('foobar');

        $form = new Form('author', new Author(), $this->validator);
        $form->enableCsrfProtection('_token', 'secret');

        $this->assertEquals(md5('secret'.get_class($form).'foobar'), $form['_token']->getData());
    }

    public function testDefaultCsrfSecretsCanBeAddedAsClosures()
    {
        FormConfiguration::addDefaultCsrfSecret(function () {
            return 'foobar';
        });

        $form = new Form('author', new Author(), $this->validator);
        $form->enableCsrfProtection('_token', 'secret');

        $this->assertEquals(md5('secret'.get_class($form).'foobar'), $form['_token']->getData());
    }

    public function testDefaultCsrfFieldNameCanBeSet()
    {
        FormConfiguration::setDefaultCsrfFieldName('foobar');
        $form = new Form('author', new Author(), $this->validator);
        $form->enableCsrfProtection();

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

    public function testBindThrowsExceptionIfNoValidatorIsSet()
    {
        $field = $this->createMockField('firstName');
        $form = new Form('author', new Author());
        $form->add($field);
        $form->setValidationGroups('group');

        $this->setExpectedException('Symfony\Component\Form\Exception\FormException');

        $form->bind(array()); // irrelevant
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
}
