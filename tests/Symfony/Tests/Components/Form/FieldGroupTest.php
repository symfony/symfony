<?php

namespace Symfony\Tests\Components\Form;

require_once __DIR__ . '/Fixtures/Author.php';
require_once __DIR__ . '/Fixtures/TestField.php';

use Symfony\Components\Form\Field;
use Symfony\Components\Form\FieldInterface;
use Symfony\Components\Form\FieldGroup;
use Symfony\Components\Form\PropertyPath;
use Symfony\Tests\Components\Form\Fixtures\Author;
use Symfony\Tests\Components\Form\Fixtures\TestField;


abstract class FieldGroupTest_Field implements FieldInterface
{
    public $locales = array();

    public function setLocale($locale)
    {
        $this->locales[] = $locale;
    }
}


class FieldGroupTest extends \PHPUnit_Framework_TestCase
{
    public function testSupportsArrayAccess()
    {
        $group = new FieldGroup('author');
        $group->add($this->createMockField('firstName'));
        $this->assertEquals($group->get('firstName'), $group['firstName']);
        $this->assertTrue(isset($group['firstName']));
    }

    public function testSupportsUnset()
    {
        $group = new FieldGroup('author');
        $group->add($this->createMockField('firstName'));
        unset($group['firstName']);
        $this->assertFalse(isset($group['firstName']));
    }

    public function testDoesNotSupportAddingFields()
    {
        $group = new FieldGroup('author');
        $this->setExpectedException('LogicException');
        $group[] = $this->createMockField('lastName');
    }

    public function testSupportsCountable()
    {
        $group = new FieldGroup('group');
        $group->add($this->createMockField('firstName'));
        $group->add($this->createMockField('lastName'));
        $this->assertEquals(2, count($group));

        $group->add($this->createMockField('australian'));
        $this->assertEquals(3, count($group));
    }

    public function testSupportsIterable()
    {
        $group = new FieldGroup('group');
        $group->add($field1 = $this->createMockField('field1'));
        $group->add($field2 = $this->createMockField('field2'));
        $group->add($field3 = $this->createMockField('field3'));

        $expected = array(
            'field1' => $field1,
            'field2' => $field2,
            'field3' => $field3,
        );

        $this->assertEquals($expected, iterator_to_array($group));
    }

    public function testIsBound()
    {
        $group = new FieldGroup('author');
        $this->assertFalse($group->isBound());
        $group->bind(array('firstName' => 'Bernhard'));
        $this->assertTrue($group->isBound());
    }

    public function testValidIfAllFieldsAreValid()
    {
        $group = new FieldGroup('author');
        $group->add($this->createValidMockField('firstName'));
        $group->add($this->createValidMockField('lastName'));

        $group->bind(array('firstName' => 'Bernhard', 'lastName' => 'Potencier'));

        $this->assertTrue($group->isValid());
    }

    public function testInvalidIfFieldIsInvalid()
    {
        $group = new FieldGroup('author');
        $group->add($this->createInvalidMockField('firstName'));
        $group->add($this->createValidMockField('lastName'));

        $group->bind(array('firstName' => 'Bernhard', 'lastName' => 'Potencier'));

        $this->assertFalse($group->isValid());
    }

    public function testInvalidIfBoundWithExtraFields()
    {
        $group = new FieldGroup('author');
        $group->add($this->createValidMockField('firstName'));
        $group->add($this->createValidMockField('lastName'));

        $group->bind(array('foo' => 'bar', 'firstName' => 'Bernhard', 'lastName' => 'Potencier'));

        $this->assertTrue($group->isBoundWithExtraFields());
    }

    public function testBindForwardsBoundValues()
    {
        $field = $this->createMockField('firstName');
        $field->expects($this->once())
                    ->method('bind')
                    ->with($this->equalTo('Bernhard'));

        $group = new FieldGroup('author');
        $group->add($field);

        $group->bind(array('firstName' => 'Bernhard'));
    }

    public function testBindForwardsNullIfValueIsMissing()
    {
        $field = $this->createMockField('firstName');
        $field->expects($this->once())
                    ->method('bind')
                    ->with($this->equalTo(null));

        $group = new FieldGroup('author');
        $group->add($field);

        $group->bind(array());
    }

    public function testAddErrorMapsFieldValidationErrorsOntoFields()
    {
        $field = $this->createMockField('firstName');
        $field->expects($this->once())
                    ->method('addError')
                    ->with($this->equalTo('Message'));

        $group = new FieldGroup('author');
        $group->add($field);

        $group->addError('Message', new PropertyPath('fields[firstName].data'), FieldGroup::FIELD_ERROR);
    }

    public function testAddErrorKeepsFieldValidationErrorsIfFieldNotFound()
    {
        $field = $this->createMockField('foo');
        $field->expects($this->never())
                    ->method('addError');

        $group = new FieldGroup('author');
        $group->add($field);

        $group->addError('Message', new PropertyPath('fields[bar].data'), FieldGroup::FIELD_ERROR);

        $this->assertEquals(array('Message'), $group->getErrors());
    }

    public function testAddErrorKeepsFieldValidationErrorsIfFieldIsHidden()
    {
        $field = $this->createMockField('firstName');
        $field->expects($this->any())
                    ->method('isHidden')
                    ->will($this->returnValue(true));
        $field->expects($this->never())
                    ->method('addError');

        $group = new FieldGroup('author');
        $group->add($field);

        $group->addError('Message', new PropertyPath('fields[firstName].data'), FieldGroup::FIELD_ERROR);

        $this->assertEquals(array('Message'), $group->getErrors());
    }

    public function testAddErrorMapsDataValidationErrorsOntoFields()
    {
        // path is expected to point at "firstName"
        $expectedPath = new PropertyPath('firstName');

        $field = $this->createMockField('firstName');
        $field->expects($this->any())
                    ->method('getPropertyPath')
                    ->will($this->returnValue(new PropertyPath('firstName')));
        $field->expects($this->once())
                    ->method('addError')
                    ->with($this->equalTo('Message'), $this->equalTo($expectedPath), $this->equalTo(FieldGroup::DATA_ERROR));

        $group = new FieldGroup('author');
        $group->add($field);

        $group->addError('Message', new PropertyPath('firstName'), FieldGroup::DATA_ERROR);
    }

    public function testAddErrorKeepsDataValidationErrorsIfFieldNotFound()
    {
        $field = $this->createMockField('foo');
        $field->expects($this->any())
                    ->method('getPropertyPath')
                    ->will($this->returnValue(new PropertyPath('foo')));
        $field->expects($this->never())
                    ->method('addError');

        $group = new FieldGroup('author');
        $group->add($field);

        $group->addError('Message', new PropertyPath('bar'), FieldGroup::DATA_ERROR);
    }

    public function testAddErrorKeepsDataValidationErrorsIfFieldIsHidden()
    {
        $field = $this->createMockField('firstName');
        $field->expects($this->any())
                    ->method('isHidden')
                    ->will($this->returnValue(true));
        $field->expects($this->any())
                    ->method('getPropertyPath')
                    ->will($this->returnValue(new PropertyPath('firstName')));
        $field->expects($this->never())
                    ->method('addError');

        $group = new FieldGroup('author');
        $group->add($field);

        $group->addError('Message', new PropertyPath('firstName'), FieldGroup::DATA_ERROR);
    }

    public function testAddErrorMapsDataValidationErrorsOntoNestedFields()
    {
        // path is expected to point at "street"
        $expectedPath = new PropertyPath('address.street');
        $expectedPath->next();

        $field = $this->createMockField('address');
        $field->expects($this->any())
                    ->method('getPropertyPath')
                    ->will($this->returnValue(new PropertyPath('address')));
        $field->expects($this->once())
                    ->method('addError')
                    ->with($this->equalTo('Message'), $this->equalTo($expectedPath), $this->equalTo(FieldGroup::DATA_ERROR));

        $group = new FieldGroup('author');
        $group->add($field);

        $group->addError('Message', new PropertyPath('address.street'), FieldGroup::DATA_ERROR);
    }

    public function testAddErrorMapsErrorsOntoFieldsInAnonymousGroups()
    {
        // path is expected to point at "address"
        $expectedPath = new PropertyPath('address');

        $field = $this->createMockField('address');
        $field->expects($this->any())
                    ->method('getPropertyPath')
                    ->will($this->returnValue(new PropertyPath('address')));
        $field->expects($this->once())
                    ->method('addError')
                    ->with($this->equalTo('Message'), $this->equalTo($expectedPath), $this->equalTo(FieldGroup::DATA_ERROR));

        $group = new FieldGroup('author');
        $group2 = new FieldGroup('anonymous', array('property_path' => null));
        $group2->add($field);
        $group->add($group2);

        $group->addError('Message', new PropertyPath('address'), FieldGroup::DATA_ERROR);
    }

    public function testAddThrowsExceptionIfAlreadyBound()
    {
        $group = new FieldGroup('author');
        $group->add($this->createMockField('firstName'));
        $group->bind(array('firstName' => 'Bernhard'));

        $this->setExpectedException('Symfony\Components\Form\Exception\AlreadyBoundException');
        $group->add($this->createMockField('lastName'));
    }

    public function testAddSetsFieldParent()
    {
        $group = new FieldGroup('author');

        $field = $this->createMockField('firstName');
        $field->expects($this->once())
                    ->method('setParent');
                    // PHPUnit fails to compare infinitely recursive objects
                    //->with($this->equalTo($group));

        $group->add($field);
    }

    public function testRemoveUnsetsFieldParent()
    {
        $group = new FieldGroup('author');

        $field = $this->createMockField('firstName');
        $field->expects($this->exactly(2))
                    ->method('setParent');
                    // PHPUnit fails to compare subsequent method calls with different arguments

        $group->add($field);
        $group->remove('firstName');
    }

    public function testMergeAddsFieldsFromAnotherGroup()
    {
        $group1 = new FieldGroup('author');
        $group1->add($field1 = new TestField('firstName'));

        $group2 = new FieldGroup('publisher');
        $group2->add($field2 = new TestField('lastName'));

        $group1->merge($group2);

        $this->assertTrue($group1->has('lastName'));
        $this->assertEquals(new PropertyPath('publisher.lastName'), $group1->get('lastName')->getPropertyPath());
    }

    public function testMergeThrowsExceptionIfOtherGroupAlreadyBound()
    {
        $group1 = new FieldGroup('author');
        $group2 = new FieldGroup('publisher');
        $group2->add($this->createMockField('firstName'));

        $group2->bind(array('firstName' => 'Bernhard'));

        $this->setExpectedException('Symfony\Components\Form\Exception\AlreadyBoundException');
        $group1->merge($group2);
    }

    public function testAddUpdatesFieldFromTransformedData()
    {
        $originalAuthor = new Author();
        $transformedAuthor = new Author();
        // the authors should differ to make sure the test works
        $transformedAuthor->firstName = 'Foo';

        $group = new FieldGroup('author');

        $transformer = $this->createMockTransformer();
        $transformer->expects($this->once())
                                ->method('transform')
                                ->with($this->equalTo($originalAuthor))
                                ->will($this->returnValue($transformedAuthor));

        $group->setValueTransformer($transformer);
        $group->setData($originalAuthor);

        $field = $this->createMockField('firstName');
        $field->expects($this->any())
                    ->method('getPropertyPath')
                    ->will($this->returnValue(new PropertyPath('firstName')));
        $field->expects($this->once())
                    ->method('updateFromObject')
                    ->with($this->equalTo($transformedAuthor));

        $group->add($field);
    }

    public function testAddDoesNotUpdateFieldsWithEmptyPropertyPath()
    {
        $group = new FieldGroup('author');
        $group->setData(new Author());

        $field = $this->createMockField('firstName');
        $field->expects($this->any())
                    ->method('getPropertyPath')
                    ->will($this->returnValue(null));
        $field->expects($this->never())
                    ->method('updateFromObject');

        $group->add($field);
    }

    public function testAddDoesNotUpdateFieldIfTransformedDataIsEmpty()
    {
        $originalAuthor = new Author();

        $group = new FieldGroup('author');

        $transformer = $this->createMockTransformer();
        $transformer->expects($this->once())
                                ->method('transform')
                                ->with($this->equalTo($originalAuthor))
                                ->will($this->returnValue(''));

        $group->setValueTransformer($transformer);
        $group->setData($originalAuthor);

        $field = $this->createMockField('firstName');
        $field->expects($this->never())
                    ->method('updateFromObject');

        $group->add($field);
    }

    public function testSetDataUpdatesAllFieldsFromTransformedData()
    {
        $originalAuthor = new Author();
        $transformedAuthor = new Author();
        // the authors should differ to make sure the test works
        $transformedAuthor->firstName = 'Foo';

        $group = new FieldGroup('author');

        $transformer = $this->createMockTransformer();
        $transformer->expects($this->once())
                                ->method('transform')
                                ->with($this->equalTo($originalAuthor))
                                ->will($this->returnValue($transformedAuthor));

        $group->setValueTransformer($transformer);

        $field = $this->createMockField('firstName');
        $field->expects($this->once())
                    ->method('updateFromObject')
                    ->with($this->equalTo($transformedAuthor));

        $group->add($field);

        $field = $this->createMockField('lastName');
        $field->expects($this->once())
                    ->method('updateFromObject')
                    ->with($this->equalTo($transformedAuthor));

        $group->add($field);

        $group->setData($originalAuthor);
    }

    public function testSetDataThrowsAnExceptionIfArgumentIsNotObjectOrArray()
    {
        $group = new FieldGroup('author');

        $this->setExpectedException('InvalidArgumentException');

        $group->setData('foobar');
    }

    public function testBindUpdatesTransformedDataFromAllFields()
    {
        $originalAuthor = new Author();
        $transformedAuthor = new Author();
        // the authors should differ to make sure the test works
        $transformedAuthor->firstName = 'Foo';

        $group = new FieldGroup('author');

        $transformer = $this->createMockTransformer();
        $transformer->expects($this->once())
                                ->method('transform')
                                ->with($this->equalTo($originalAuthor))
                                ->will($this->returnValue($transformedAuthor));

        $group->setValueTransformer($transformer);
        $group->setData($originalAuthor);

        $field = $this->createMockField('firstName');
        $field->expects($this->once())
                    ->method('updateObject')
                    ->with($this->equalTo($transformedAuthor));

        $group->add($field);

        $field = $this->createMockField('lastName');
        $field->expects($this->once())
                    ->method('updateObject')
                    ->with($this->equalTo($transformedAuthor));

        $group->add($field);

        $group->bind(array()); // irrelevant
    }

    public function testGetDataReturnsObject()
    {
        $group = new FieldGroup('author');
        $object = new \stdClass();
        $group->setData($object);
        $this->assertEquals($object, $group->getData());
    }

    public function testGetDisplayedDataForwardsCall()
    {
        $field = $this->createValidMockField('firstName');
        $field->expects($this->atLeastOnce())
                    ->method('getDisplayedData')
                    ->will($this->returnValue('Bernhard'));

        $group = new FieldGroup('author');
        $group->add($field);

        $this->assertEquals(array('firstName' => 'Bernhard'), $group->getDisplayedData());
    }

    public function testIsMultipartIfAnyFieldIsMultipart()
    {
        $group = new FieldGroup('author');
        $group->add($this->createMultipartMockField('firstName'));
        $group->add($this->createNonMultipartMockField('lastName'));

        $this->assertTrue($group->isMultipart());
    }

    public function testIsNotMultipartIfNoFieldIsMultipart()
    {
        $group = new FieldGroup('author');
        $group->add($this->createNonMultipartMockField('firstName'));
        $group->add($this->createNonMultipartMockField('lastName'));

        $this->assertFalse($group->isMultipart());
    }

    public function testRenderForwardsToRenderer()
    {
        $group = new FieldGroup('author');

        $renderer = $this->createMockRenderer();
        $renderer->expects($this->once())
                         ->method('render')
                         ->with($this->equalTo($group), $this->equalTo(array('foo' => 'bar')))
                         ->will($this->returnValue('HTML'));

        $group->setRenderer($renderer);

        // test
        $output = $group->render(array('foo' => 'bar'));

        $this->assertEquals('HTML', $output);
    }

    public function testRenderErrorsForwardsToRenderer()
    {
        $group = new FieldGroup('author');

        $renderer = $this->createMockRenderer();
        $renderer->expects($this->once())
                         ->method('renderErrors')
                         ->with($this->equalTo($group))
                         ->will($this->returnValue('HTML'));

        $group->setRenderer($renderer);

        // test
        $output = $group->renderErrors();

        $this->assertEquals('HTML', $output);
    }

    public function testLocaleIsPassedToRenderer()
    {
        $renderer = $this->getMock('Symfony\Components\Form\Renderer\RendererInterface');
        $renderer->expects($this->once())
                         ->method('setLocale')
                         ->with($this->equalTo('de_DE'));

        $group = new FieldGroup('author');
        $group->setRenderer($renderer);
        $group->setLocale('de_DE');
        $group->render();
    }

    public function testTranslatorIsPassedToRenderer()
    {
        $translator = $this->getMock('Symfony\Components\I18N\TranslatorInterface');
        $renderer = $this->getMock('Symfony\Components\Form\Renderer\RendererInterface');
        $renderer->expects($this->once())
                         ->method('setTranslator')
                         ->with($this->equalTo($translator));

        $group = new FieldGroup('author');
        $group->setRenderer($renderer);
        $group->setTranslator($translator);
        $group->render();
    }

    public function testTranslatorIsNotPassedToRendererIfNotSet()
    {
        $renderer = $this->getMock('Symfony\Components\Form\Renderer\RendererInterface');
        $renderer->expects($this->never())
                         ->method('setTranslator');

        $group = new FieldGroup('author');
        $group->setRenderer($renderer);
        $group->render();
    }

    public function testLocaleIsPassedToField_SetBeforeAddingTheField()
    {
        $field = $this->getMock('Symfony\Components\Form\Field', array(), array(), '', false, false);
        $field->expects($this->any())
                    ->method('getKey')
                    ->will($this->returnValue('firstName'));
        $field->expects($this->once())
                    ->method('setLocale')
                    ->with($this->equalTo('de_DE'));

        $group = new FieldGroup('author');
        $group->setLocale('de_DE');
        $group->add($field);
    }

    public function testLocaleIsPassedToField_SetAfterAddingTheField()
    {
        $field = $this->getMockForAbstractClass(__NAMESPACE__ . '\FieldGroupTest_Field', array(), '', false, false);
        $field->expects($this->any())
                    ->method('getKey')
                    ->will($this->returnValue('firstName'));
// DOESN'T WORK!
//    $field = $this->getMock(__NAMESPACE__ . '\Fixtures\Field', array(), array(), '', false, false);
//    $field->expects($this->once())
//          ->method('setLocale')
//          ->with($this->equalTo('de_AT'));
//    $field->expects($this->once())
//          ->method('setLocale')
//          ->with($this->equalTo('de_DE'));

        $group = new FieldGroup('author');
        $group->add($field);
        $group->setLocale('de_DE');

        $this->assertEquals(array(class_exists('\Locale', false) ? \Locale::getDefault() : 'en', 'de_DE'), $field->locales);
    }

    public function testTranslatorIsPassedToField_SetBeforeAddingTheField()
    {
        $translator = $this->getMock('Symfony\Components\I18N\TranslatorInterface');
        $field = $this->getMock('Symfony\Components\Form\Field', array(), array(), '', false, false);
        $field->expects($this->any())
                    ->method('getKey')
                    ->will($this->returnValue('firstName'));
        $field->expects($this->once())
                    ->method('setTranslator')
                    ->with($this->equalTo($translator));

        $group = new FieldGroup('author');
        $group->setTranslator($translator);
        $group->add($field);
    }

    public function testTranslatorIsPassedToField_SetAfterAddingTheField()
    {
        $translator = $this->getMock('Symfony\Components\I18N\TranslatorInterface');
        $field = $this->getMock('Symfony\Components\Form\Field', array(), array(), '', false, false);
        $field->expects($this->any())
                    ->method('getKey')
                    ->will($this->returnValue('firstName'));
        $field->expects($this->once())
                    ->method('setTranslator')
                    ->with($this->equalTo($translator));

        $group = new FieldGroup('author');
        $group->add($field);
        $group->setTranslator($translator);
    }

    public function testTranslatorIsNotPassedToFieldIfNotSet()
    {
        $field = $this->getMock('Symfony\Components\Form\Field', array(), array(), '', false, false);
        $field->expects($this->any())
                    ->method('getKey')
                    ->will($this->returnValue('firstName'));
        $field->expects($this->never())
                    ->method('setTranslator');

        $group = new FieldGroup('author');
        $group->add($field);
    }

    public function testSupportsClone()
    {
        $group = new FieldGroup('author');
        $group->add($this->createMockField('firstName'));

        $clone = clone $group;

        $this->assertNotSame($clone['firstName'], $group['firstName']);
    }

    public function testBindWithoutPriorSetData()
    {
        return; // TODO
        $field = $this->createMockField('firstName');
        $field->expects($this->any())
                    ->method('getData')
                    ->will($this->returnValue('Bernhard'));

        $group = new FieldGroup('author');
        $group->add($field);

        $group->bind(array('firstName' => 'Bernhard'));

        $this->assertEquals(array('firstName' => 'Bernhard'), $group->getData());
    }

    public function testSetGenerator_calledBeforeAdding()
    {
        $generator = $this->getMock('Symfony\Components\Form\HtmlGeneratorInterface');

        $field = $this->createMockField('firstName');
        $field->expects($this->once())
                    ->method('setGenerator')
                    ->with($this->equalTo($generator));

        $group = new FieldGroup('author');
        $group->setGenerator($generator);
        $group->add($field);
    }

    public function testSetGenerator_calledAfterAdding()
    {
        $generator = $this->getMock('Symfony\Components\Form\HtmlGeneratorInterface');

        $field = $this->createMockField('firstName');
        $field->expects($this->exactly(2)) // cannot test different arguments :(
                    ->method('setGenerator');

        $group = new FieldGroup('author');
        $group->add($field);
        $group->setGenerator($generator);
    }

    protected function createMockField($key)
    {
        $field = $this->getMock(
            'Symfony\Components\Form\FieldInterface',
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

    protected function createInvalidMockField($key)
    {
        $field = $this->createMockField($key);
        $field->expects($this->any())
                    ->method('isValid')
                    ->will($this->returnValue(false));

        return $field;
    }

    protected function createValidMockField($key)
    {
        $field = $this->createMockField($key);
        $field->expects($this->any())
                    ->method('isValid')
                    ->will($this->returnValue(true));

        return $field;
    }

    protected function createNonMultipartMockField($key)
    {
        $field = $this->createMockField($key);
        $field->expects($this->any())
                    ->method('isMultipart')
                    ->will($this->returnValue(false));

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

    protected function createMockRenderer()
    {
        return $this->getMock('Symfony\Components\Form\Renderer\RendererInterface');
    }

    protected function createMockTransformer()
    {
        return $this->getMock('Symfony\Components\Form\ValueTransformer\ValueTransformerInterface', array(), array(), '', false, false);
    }
}
