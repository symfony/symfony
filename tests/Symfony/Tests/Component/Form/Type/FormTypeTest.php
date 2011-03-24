<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Type;

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../Fixtures/Author.php';

use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormContext;
use Symfony\Component\Form\Field;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\DataError;
use Symfony\Component\Form\HiddenField;
use Symfony\Component\Form\PropertyPath;
use Symfony\Component\Form\DataTransformer\CallbackTransformer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ExecutionContext;
use Symfony\Tests\Component\Form\Fixtures\Author;

class FormTest_AuthorWithoutRefSetter
{
    protected $reference;

    protected $referenceCopy;

    public function __construct($reference)
    {
        $this->reference = $reference;
        $this->referenceCopy = $reference;
    }

    // The returned object should be modified by reference without having
    // to provide a setReference() method
    public function getReference()
    {
        return $this->reference;
    }

    // The returned object is a copy, so setReferenceCopy() must be used
    // to update it
    public function getReferenceCopy()
    {
        return is_object($this->referenceCopy) ? clone $this->referenceCopy : $this->referenceCopy;
    }

    public function setReferenceCopy($reference)
    {
        $this->referenceCopy = $reference;
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

class FormTest extends TestCase
{
    protected $form;

    public static function setUpBeforeClass()
    {
        @session_start();
    }

    protected function setUp()
    {
        parent::setUp();

        $this->form = $this->factory->create('form', 'author');
    }

    public function testCsrfProtectionByDefault()
    {
        $builder =  $this->factory->create('form', 'author', array(
            'csrf_field_name' => 'csrf',
        ));

        $this->assertTrue($builder->has('csrf'));
    }

    public function testCsrfProtectionCanBeDisabled()
    {
        $form =  $this->factory->create('form', 'author', array(
            'csrf_protection' => false,
        ));

        $this->assertEquals(0, count($form));
    }

    public function testValidationGroupNullByDefault()
    {
        $this->assertNull($this->form->getAttribute('validation_groups'));
    }

    public function testValidationGroupsCanBeSetToString()
    {
        $form = $this->factory->create('form', 'author', array(
            'validation_groups' => 'group',
        ));

        $this->assertEquals(array('group'), $form->getAttribute('validation_groups'));
    }

    public function testValidationGroupsCanBeSetToArray()
    {
        $form = $this->factory->create('form', 'author', array(
            'validation_groups' => array('group1', 'group2'),
        ));

        $this->assertEquals(array('group1', 'group2'), $form->getAttribute('validation_groups'));
    }

    public function testBindValidatesData()
    {
        $builder = $this->factory->createBuilder('form', 'author', array(
            'validation_groups' => 'group',
        ));
        $builder->add('firstName', 'field');
        $form = $builder->getForm();

        $this->validator->expects($this->once())
        ->method('validate')
        ->with($this->equalTo($form));

        // specific data is irrelevant
        $form->bind(array());
    }

    public function testBindDoesNotValidateArrays()
    {
        $builder = $this->factory->createBuilder('form', 'author');
        $builder->add('firstName', 'field');
        $form = $builder->getForm();

        // only the form is validated
        $this->validator->expects($this->once())
        ->method('validate')
        ->with($this->equalTo($form));

        // specific data is irrelevant
        $form->bind(array());
    }

    public function testBindReadsRequestData()
    {
        $path = tempnam(sys_get_temp_dir(), 'sf2');
        touch($path);

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
                'tmp_name' => array('image' => array('file' => $path)),
                'type' => array('image' => array('file' => 'image/png')),
        ),
        );

        $builder = $this->factory->createBuilder('form', 'author');
        $builder->add('name', 'field');
        $builder->add('image', 'form');
        $builder->get('image')->add('file', 'field');
        $builder->get('image')->add('filename', 'field');
        $form = $builder->getForm();

        $form->bindRequest($this->getPostRequest($values, $files));

        $file = new UploadedFile($path, 'upload.png', 'image/png', 123, UPLOAD_ERR_OK);

        $this->assertEquals('Bernhard', $form['name']->getData());
        $this->assertEquals('foobar.png', $form['image']['filename']->getData());
        $this->assertEquals($file, $form['image']['file']->getData());
    }

    public function testSupportsArrayAccess()
    {
        $builder = $this->factory->createBuilder('form', 'author');
        $builder->add('firstName', 'field');
        $form = $builder->getForm();

        $this->assertEquals($form->get('firstName'), $form['firstName']);
        $this->assertTrue(isset($form['firstName']));
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testSupportsUnset()
    {
        $form = $this->factory->create('form', 'author');

        unset($form['firstName']);
    }

    public function testDoesNotSupportAddingFields()
    {
        $form = $this->factory->create('form', 'author');

        $this->setExpectedException('LogicException');

        $form[] = $this->getMockForm('lastName');
    }

    public function testSupportsCountable()
    {
        $builder = $this->factory->createBuilder('form', 'group', array(
            'csrf_protection' => false,
        ));
        $builder->add('firstName', 'field');
        $builder->add('lastName', 'field');
        $form = $builder->getForm();

        $this->assertEquals(2, count($form));
    }

    public function testSupportsIterable()
    {
        $builder = $this->factory->createBuilder('form', 'group', array(
            'csrf_protection' => false,
        ));
        $builder->add('field1', 'field');
        $builder->add('field2', 'field');
        $builder->add('field3', 'field');
        $form = $builder->getForm();

        $expected = array(
            'field1' => $form->get('field1'),
            'field2' => $form->get('field2'),
            'field3' => $form->get('field3'),
        );

        $this->assertEquals($expected, iterator_to_array($form));
    }

    public function testIsBound()
    {
        $form = $this->factory->create('form', 'author');
        $this->assertFalse($form->isBound());
        $form->bind(array('firstName' => 'Bernhard'));
        $this->assertTrue($form->isBound());
    }

    public function testHasNoErrorsIfOnlyFieldHasErrors()
    {
        $builder = $this->factory->createBuilder('form', 'author');
        $builder->add('firstName', 'field');
        $form = $builder->getForm();

        $form->bind(array('firstName' => 'Bernhard'));

        $this->assertFalse($form->hasErrors());
    }

    public function testSetDataUpdatesAllFieldsFromTransformedData()
    {
        $originalAuthor = new Author();
        $transformedAuthor = new Author();
        $transformedAuthor->firstName = 'Foo';
        $transformedAuthor->setLastName('Bar');

        $transformer = $this->getMockTransformer();
        $transformer->expects($this->at(0))
        ->method('transform')
        ->with($this->equalTo(null))
        ->will($this->returnValue(''));
        $transformer->expects($this->at(1))
        ->method('transform')
        ->with($this->equalTo($originalAuthor))
        ->will($this->returnValue($transformedAuthor));

        $builder = $this->factory->createBuilder('form', 'author');
        $builder->setClientTransformer($transformer);
        $builder->add('firstName', 'field');
        $builder->add('lastName', 'field');
        $form = $builder->getForm();

        $form->setData($originalAuthor);

        $this->assertEquals('Foo', $form['firstName']->getData());
        $this->assertEquals('Bar', $form['lastName']->getData());
    }

    /**
     * The use case for this test are groups whose fields should be mapped
     * directly onto properties of the form's object.
     *
     * Example:
     *
     * <code>
     * $dateRangeField = $this->factory->create('form', 'dateRange');
     * $dateRangeField->add(new DateField('startDate'));
     * $dateRangeField->add(new DateField('endDate'));
     * $form->add($dateRangeField);
     * </code>
     *
     * If $dateRangeField is not virtual, the property "dateRange" must be
     * present on the form's object. In this property, an object or array
     * with the properties "startDate" and "endDate" is expected.
     *
     * If $dateRangeField is virtual though, it's children are mapped directly
     * onto the properties "startDate" and "endDate" of the form's object.
     */
    public function testSetDataSkipsVirtualForms()
    {
        $author = new Author();
        $author->firstName = 'Foo';
        $author->setLastName('Bar');

        $builder = $this->factory->createBuilder('form', 'author');
        $builder->setData($author);
        $builder->add('personalData', 'form', array(
            'virtual' => true,
        ));
        // both fields are in the nested group but receive the object of the
        // top-level group because the nested group is virtual
        $builder->get('personalData')->add('firstName', 'field');
        $builder->get('personalData')->add('lastName', 'field');
        $form = $builder->getForm();

        $this->assertEquals('Foo', $form['personalData']['firstName']->getData());
        $this->assertEquals('Bar', $form['personalData']['lastName']->getData());
    }

    public function testSetDataThrowsAnExceptionIfArgumentIsNotObjectOrArray()
    {
        $form = $this->factory->create('form', 'author');

        $this->setExpectedException('InvalidArgumentException');

        $form->setData('foobar');
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\FormException
     */
    public function testSetDataMatchesAgainstDataClass_fails()
    {
        $form = $this->factory->create('form', 'author', array(
            'data_class' => 'Symfony\Tests\Component\Form\Fixtures\Author',
        ));

        $form->setData(new \stdClass());
    }

    public function testSetDataMatchesAgainstDataClass_succeeds()
    {
        $form = $this->factory->create('form', 'author', array(
            'data_class' => 'Symfony\Tests\Component\Form\Fixtures\Author',
        ));

        $form->setData(new Author());
    }

    public function testSetDataToNullCreatesObjectIfClassAvailable()
    {
        $form = $this->factory->create('form', 'author', array(
            'data_class' => 'Symfony\Tests\Component\Form\Fixtures\Author',
        ));
        $form->setData(null);

        $this->assertEquals(new Author(), $form->getData());
    }

    public function testSetDataToNullUsesDataConstructorOption()
    {
        $author = new Author();
        $form = $this->factory->create('form', 'author', array(
            'data_constructor' => function () use ($author) {
        return $author;
            }
            ));

            $form->setData(null);

            $this->assertSame($author, $form->getData());
    }

    /*
     * We need something to write the field values into
     */
    public function testSetDataToNullCreatesArrayIfNoDataClassOrConstructor()
    {
        $author = new Author();
        $form = $this->factory->create('form', 'author');
        $form->setData(null);

        $this->assertSame(array(), $form->getData());
    }

    public function testSubmitUpdatesTransformedDataFromAllFields()
    {
        $originalAuthor = new Author();
        $transformedAuthor = new Author();

        $transformer = $this->getMockTransformer();
        $transformer->expects($this->at(0))
        ->method('transform')
        ->with($this->equalTo(null))
        ->will($this->returnValue(''));
        $transformer->expects($this->at(1))
        ->method('transform')
        ->with($this->equalTo($originalAuthor))
        ->will($this->returnValue($transformedAuthor));

        $builder = $this->factory->createBuilder('form', 'author');
        $builder->setClientTransformer($transformer);
        $builder->add('firstName', 'field');
        $builder->add('lastName', 'field');
        $builder->setData($originalAuthor);
        $form = $builder->getForm();

        $form->bind(array(
            'firstName' => 'Foo',
            'lastName' => 'Bar',
        ));

        $this->assertEquals('Foo', $transformedAuthor->firstName);
        $this->assertEquals('Bar', $transformedAuthor->getLastName());
    }

    public function testGetDataReturnsObject()
    {
        $form = $this->factory->create('form', 'author');
        $object = new \stdClass();
        $form->setData($object);
        $this->assertEquals($object, $form->getData());
    }

    public function testValidateData()
    {
        $graphWalker = $this->getMockGraphWalker();
        $metadataFactory = $this->getMockMetadataFactory();
        $context = new ExecutionContext('Root', $graphWalker, $metadataFactory);
        $object = $this->getMock('\stdClass');
        $form = $this->factory->create('form', 'author', array('validation_groups' => array(
            'group1',
            'group2',
        )));

        $graphWalker->expects($this->exactly(2))
        ->method('walkReference')
        ->with($object,
        // should test for groups - PHPUnit limitation
        $this->anything(),
                'data',
        true);

        $form->setData($object);
        $form->validateData($context);
    }

    public function testValidateDataAppendsPropertyPath()
    {
        $graphWalker = $this->getMockGraphWalker();
        $metadataFactory = $this->getMockMetadataFactory();
        $context = new ExecutionContext('Root', $graphWalker, $metadataFactory);
        $context->setPropertyPath('path');
        $object = $this->getMock('\stdClass');
        $form = $this->factory->create('form', 'author');

        $graphWalker->expects($this->once())
            ->method('walkReference')
            ->with($object, 'Default', 'path.data', true);

        $form->setData($object);
        $form->validateData($context);
    }

    public function testValidateDataSetsCurrentPropertyToData()
    {
        $graphWalker = $this->getMockGraphWalker();
        $metadataFactory = $this->getMockMetadataFactory();
        $context = new ExecutionContext('Root', $graphWalker, $metadataFactory);
        $object = $this->getMock('\stdClass');
        $form = $this->factory->create('form', 'author');
        $test = $this;

        $graphWalker->expects($this->once())
        ->method('walkReference')
        ->will($this->returnCallback(function () use ($context, $test) {
            $test->assertEquals('data', $context->getCurrentProperty());
        }));

        $form->setData($object);
        $form->validateData($context);
    }

    public function testValidateDataDoesNotWalkScalars()
    {
        $graphWalker = $this->getMockGraphWalker();
        $metadataFactory = $this->getMockMetadataFactory();
        $context = new ExecutionContext('Root', $graphWalker, $metadataFactory);
        $clientTransformer = $this->getMockTransformer();

        $builder = $this->factory->createBuilder('form', 'author');
        $builder->setClientTransformer($clientTransformer);
        $form = $builder->getForm();

        $graphWalker->expects($this->never())
        ->method('walkReference');

        $clientTransformer->expects($this->atLeastOnce())
        ->method('reverseTransform')
        ->will($this->returnValue('foobar'));

        $form->bind(array('foo' => 'bar')); // reverse transformed to "foobar"
        $form->validateData($context);
    }

    public function testSubformDoesntCallSetters()
    {
        $author = new FormTest_AuthorWithoutRefSetter(new Author());

        $builder = $this->factory->createBuilder('form', 'author');
        $builder->add('reference', 'form');
        $builder->get('reference')->add('firstName', 'field');
        $builder->setData($author);
        $form = $builder->getForm();

        $form->bind(array(
        // reference has a getter, but not setter
            'reference' => array(
                'firstName' => 'Foo',
        )
        ));

        $this->assertEquals('Foo', $author->getReference()->firstName);
    }

    public function testSubformCallsSettersIfTheObjectChanged()
    {
        // no reference
        $author = new FormTest_AuthorWithoutRefSetter(null);
        $newReference = new Author();

        $builder = $this->factory->createBuilder('form', 'author');
        $builder->add('referenceCopy', 'form');
        $builder->get('referenceCopy')->add('firstName', 'field');
        $builder->setData($author);
        $form = $builder->getForm();

        $form['referenceCopy']->setData($newReference); // new author object

        $form->bind(array(
        // referenceCopy has a getter that returns a copy
            'referenceCopy' => array(
                'firstName' => 'Foo',
        )
        ));

        $this->assertEquals('Foo', $author->getReferenceCopy()->firstName);
    }

    public function testSubformCallsSettersIfByReferenceIsFalse()
    {
        $author = new FormTest_AuthorWithoutRefSetter(new Author());

        $builder = $this->factory->createBuilder('form', 'author');
        $builder->add('referenceCopy', 'form', array('by_reference' => false));
        $builder->get('referenceCopy')->add('firstName', 'field');
        $builder->setData($author);
        $form = $builder->getForm();

        $form->bind(array(
        // referenceCopy has a getter that returns a copy
            'referenceCopy' => array(
                'firstName' => 'Foo',
        )
        ));

        // firstName can only be updated if setReferenceCopy() was called
        $this->assertEquals('Foo', $author->getReferenceCopy()->firstName);
    }

    public function testSubformCallsSettersIfReferenceIsScalar()
    {
        $author = new FormTest_AuthorWithoutRefSetter('scalar');

        $builder = $this->factory->createBuilder('form', 'author');
        $builder->add('referenceCopy', 'form');
        $builder->get('referenceCopy')->setClientTransformer(new CallbackTransformer(
        function () {},
        function ($value) { // reverseTransform
            return 'foobar';
        }
        ));
        $builder->setData($author);
        $form = $builder->getForm();

        $form->bind(array(
            'referenceCopy' => array(), // doesn't matter actually
        ));

        // firstName can only be updated if setReferenceCopy() was called
        $this->assertEquals('foobar', $author->getReferenceCopy());
    }

    public function testSubformAlwaysInsertsIntoArrays()
    {
        $ref1 = new Author();
        $ref2 = new Author();
        $author = array('referenceCopy' => $ref1);

        $builder = $this->factory->createBuilder('form', 'author');
        $builder->setData($author);
        $builder->add('referenceCopy', 'form');
        $builder->get('referenceCopy')->setClientTransformer(new CallbackTransformer(
        function () {},
        function ($value) use ($ref2) { // reverseTransform
            return $ref2;
        }
        ));
        $form = $builder->getForm();

        $form->bind(array(
            'referenceCopy' => array('a' => 'b'), // doesn't matter actually
        ));

        // the new reference was inserted into the array
        $author = $form->getData();
        $this->assertSame($ref2, $author['referenceCopy']);
    }

    public function testIsEmptyReturnsTrueIfAllFieldsAreEmpty()
    {
        $builder = $this->factory->createBuilder('form', 'name');
        $builder->add('foo', 'field', array('data' => ''));
        $builder->add('bar', 'field', array('data' => null));
        $form = $builder->getForm();

        $this->assertTrue($form->isEmpty());
    }

    public function testIsEmptyReturnsFalseIfAnyFieldIsFilled()
    {
        $builder = $this->factory->createBuilder('form', 'name');
        $builder->add('foo', 'field', array('data' => 'baz'));
        $builder->add('bar', 'field', array('data' => null));
        $form = $builder->getForm();

        $this->assertFalse($form->isEmpty());
    }

    /**
     * Create a group containing two fields, "visibleField" and "hiddenField"
     *
     * @return Form
     */
    protected function getGroupWithBothVisibleAndHiddenField()
    {
        $form = $this->factory->create('form', 'testGroup');

        // add a visible field
        $visibleField = $this->getMockForm('visibleField');
        $visibleField->expects($this->once())
        ->method('isHidden')
        ->will($this->returnValue(false));
        $form->add($visibleField);

        // add a hidden field
        $hiddenField = $this->getMockForm('hiddenField');
        $hiddenField->expects($this->once())
        ->method('isHidden')
        ->will($this->returnValue(true));
        $form->add($hiddenField);

        return $form;
    }

    protected function getMockForm($key)
    {
        $field = $this->getMock('Symfony\Tests\Component\Form\FormInterface');

        $field->expects($this->any())
        ->method('getName')
        ->will($this->returnValue($key));

        return $field;
    }

    protected function getMockTransformer()
    {
        return $this->getMock('Symfony\Component\Form\DataTransformer\DataTransformerInterface', array(), array(), '', false, false);
    }

    protected function getMockValidator()
    {
        return $this->getMock('Symfony\Component\Validator\ValidatorInterface');
    }

    protected function getMockCsrfProvider()
    {
        return $this->getMock('Symfony\Component\Form\CsrfProvider\CsrfProviderInterface');
    }

    protected function getMockGraphWalker()
    {
        return $this->getMockBuilder('Symfony\Component\Validator\GraphWalker')
        ->disableOriginalConstructor()
        ->getMock();
    }

    protected function getMockMetadataFactory()
    {
        return $this->getMock('Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface');
    }

    protected function getPostRequest(array $values = array(), array $files = array())
    {
        $server = array('REQUEST_METHOD' => 'POST');

        return new Request(array(), $values, array(), array(), $files, $server);
    }
}
