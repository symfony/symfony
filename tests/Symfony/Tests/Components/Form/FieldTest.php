<?php

namespace Symfony\Tests\Components\Form;

require_once __DIR__ . '/Fixtures/Author.php';
require_once __DIR__ . '/Fixtures/TestField.php';
require_once __DIR__ . '/Fixtures/InvalidField.php';
require_once __DIR__ . '/Fixtures/RequiredOptionsField.php';

use Symfony\Components\Form\ValueTransformer\ValueTransformerInterface;
use Symfony\Tests\Components\Form\Fixtures\Author;
use Symfony\Tests\Components\Form\Fixtures\TestField;
use Symfony\Tests\Components\Form\Fixtures\InvalidField;
use Symfony\Tests\Components\Form\Fixtures\RequiredOptionsField;

class FieldTest extends \PHPUnit_Framework_TestCase
{
    protected $field;

    protected function setUp()
    {
        $this->field = new TestField('title');
    }

    public function testPassRequiredAsOption()
    {
        $field = new TestField('title', array('required' => false));

        $this->assertFalse($field->isRequired());

        $field = new TestField('title', array('required' => true));

        $this->assertTrue($field->isRequired());
    }

    public function testPassDisabledAsOption()
    {
        $field = new TestField('title', array('disabled' => false));

        $this->assertFalse($field->isDisabled());

        $field = new TestField('title', array('disabled' => true));

        $this->assertTrue($field->isDisabled());
    }

    public function testFieldIsDisabledIfParentIsDisabled()
    {
        $field = new TestField('title', array('disabled' => false));
        $field->setParent(new TestField('title', array('disabled' => true)));

        $this->assertTrue($field->isDisabled());
    }

    public function testFieldWithNoErrorsIsValid()
    {
        $this->field->bind('data');

        $this->assertTrue($this->field->isValid());
    }

    public function testFieldWithErrorsIsInvalid()
    {
        $this->field->bind('data');
        $this->field->addError('Some error');

        $this->assertFalse($this->field->isValid());
    }

    public function testBindResetsErrors()
    {
        $this->field->addError('Some error');
        $this->field->bind('data');

        $this->assertTrue($this->field->isValid());
    }

    public function testUnboundFieldIsInvalid()
    {
        $this->assertFalse($this->field->isValid());
    }

    public function testGetNameReturnsKey()
    {
        $this->assertEquals('title', $this->field->getName());
    }

    public function testGetNameIncludesParent()
    {
        $this->field->setParent($this->createMockGroupWithName('news[article]'));

        $this->assertEquals('news[article][title]', $this->field->getName());
    }

    public function testGetIdReturnsKey()
    {
        $this->assertEquals('title', $this->field->getId());
    }

    public function testGetIdIncludesParent()
    {
        $this->field->setParent($this->createMockGroupWithId('news_article'));

        $this->assertEquals('news_article_title', $this->field->getId());
    }

    public function testLocaleIsPassedToLocalizableValueTransformer_setLocaleCalledBefore()
    {
        $transformer = $this->getMock('Symfony\Components\Form\ValueTransformer\ValueTransformerInterface');
        $transformer->expects($this->once())
                         ->method('setLocale')
                         ->with($this->equalTo('de_DE'));

        $this->field->setLocale('de_DE');
        $this->field->setValueTransformer($transformer);
    }

    public function testLocaleIsPassedToValueTransformer_setLocaleCalledAfter()
    {
        $transformer = $this->getMock('Symfony\Components\Form\ValueTransformer\ValueTransformerInterface');
        $transformer->expects($this->exactly(2))
                         ->method('setLocale'); // we can't test the params cause they differ :(

        $this->field->setValueTransformer($transformer);
        $this->field->setLocale('de_DE');
    }

    public function testIsRequiredReturnsOwnValueIfNoParent()
    {
        $this->field->setRequired(true);
        $this->assertTrue($this->field->isRequired());

        $this->field->setRequired(false);
        $this->assertFalse($this->field->isRequired());
    }

    public function testIsRequiredReturnsOwnValueIfParentIsRequired()
    {
        $group = $this->createMockGroup();
        $group->expects($this->any())
                    ->method('isRequired')
                    ->will($this->returnValue(true));

        $this->field->setParent($group);

        $this->field->setRequired(true);
        $this->assertTrue($this->field->isRequired());

        $this->field->setRequired(false);
        $this->assertFalse($this->field->isRequired());
    }

    public function testIsRequiredReturnsFalseIfParentIsNotRequired()
    {
        $group = $this->createMockGroup();
        $group->expects($this->any())
                    ->method('isRequired')
                    ->will($this->returnValue(false));

        $this->field->setParent($group);
        $this->field->setRequired(true);

        $this->assertFalse($this->field->isRequired());
    }

    public function testExceptionIfUnknownOption()
    {
        $this->setExpectedException('Symfony\Components\Form\Exception\InvalidOptionsException');

        new RequiredOptionsField('name', array('bar' => 'baz', 'moo' => 'maa'));
    }

    public function testExceptionIfMissingOption()
    {
        $this->setExpectedException('Symfony\Components\Form\Exception\MissingOptionsException');

        new RequiredOptionsField('name');
    }

    public function testIsBound()
    {
        $this->assertFalse($this->field->isBound());
        $this->field->bind('symfony');
        $this->assertTrue($this->field->isBound());
    }

    public function testDefaultValuesAreTransformedCorrectly()
    {
        $field = new TestField('name');

        $this->assertEquals(null, $this->field->getData());
        $this->assertEquals('', $this->field->getDisplayedData());
    }

    public function testValuesAreTransformedCorrectlyIfNull()
    {
        // The value is converted to an empty string and NOT passed to the
        // value transformer
        $transformer = $this->createMockTransformer();
        $transformer->expects($this->never())
                                ->method('transform');

        $this->field->setValueTransformer($transformer);
        $this->field->setData(null);

        $this->assertSame(null, $this->field->getData());
        $this->assertSame('', $this->field->getDisplayedData());
    }

    public function testValuesAreTransformedCorrectlyIfNull_noValueTransformer()
    {
        $this->field->setData(null);

        $this->assertSame(null, $this->field->getData());
        $this->assertSame('', $this->field->getDisplayedData());
    }

    public function testBoundValuesAreTransformedCorrectly()
    {
        $field = $this->getMock(
            'Symfony\Tests\Components\Form\Fixtures\TestField',
            array('processData'), // only mock processData()
            array('title')
        );

        // 1. The value is converted to a string and passed to the value transformer
        $transformer = $this->createMockTransformer();
        $transformer->expects($this->once())
                                ->method('reverseTransform')
                                ->with($this->identicalTo('0'))
                                ->will($this->returnValue('reverse[0]'));

        $field->setValueTransformer($transformer);

        // 2. The output of the reverse transformation is passed to processData()
        $field->expects($this->once())
                    ->method('processData')
                    ->with($this->equalTo('reverse[0]'))
                    ->will($this->returnValue('processed[reverse[0]]'));

        // 3. The processed data is transformed again (for displayed data)
        $transformer->expects($this->once())
                                ->method('transform')
                                ->with($this->equalTo('processed[reverse[0]]'))
                                ->will($this->returnValue('transform[processed[reverse[0]]]'));

        $field->bind(0);

        $this->assertEquals('processed[reverse[0]]', $field->getData());
        $this->assertEquals('transform[processed[reverse[0]]]', $field->getDisplayedData());
    }

    public function testBoundValuesAreTransformedCorrectlyIfEmpty_processDataReturnsValue()
    {
        $field = $this->getMock(
            'Symfony\Tests\Components\Form\Fixtures\TestField',
            array('processData'), // only mock processData()
            array('title')
        );

        // 1. Empty values are always converted to NULL. They are never passed to
        //    the value transformer
        $transformer = $this->createMockTransformer();
        $transformer->expects($this->never())
                                ->method('reverseTransform');

        $field->setValueTransformer($transformer);

        // 2. NULL is passed to processData()
        $field->expects($this->once())
                    ->method('processData')
                    ->with($this->identicalTo(null))
                    ->will($this->returnValue('processed'));

        // 3. The processed data is transformed (for displayed data)
        $transformer->expects($this->once())
                                ->method('transform')
                                ->with($this->equalTo('processed'))
                                ->will($this->returnValue('transform[processed]'));

        $field->bind('');

        $this->assertSame('processed', $field->getData());
        $this->assertEquals('transform[processed]', $field->getDisplayedData());
    }

    public function testBoundValuesAreTransformedCorrectlyIfEmpty_processDataReturnsNull()
    {
        // 1. Empty values are always converted to NULL. They are never passed to
        //    the value transformer
        $transformer = $this->createMockTransformer();
        $transformer->expects($this->never())
                                ->method('reverseTransform');

        $this->field->setValueTransformer($transformer);

        // 2. The processed data is NULL and therefore transformed to an empty
        //    string. It is NOT passed to the value transformer
        $transformer->expects($this->never())
                                ->method('transform');

        $this->field->bind('');

        $this->assertSame(null, $this->field->getData());
        $this->assertEquals('', $this->field->getDisplayedData());
    }

    public function testBoundValuesAreTransformedCorrectlyIfEmpty_processDataReturnsNull_noValueTransformer()
    {
        $this->field->bind('');

        $this->assertSame(null, $this->field->getData());
        $this->assertEquals('', $this->field->getDisplayedData());
    }

    public function testValuesAreTransformedCorrectly()
    {
        // The value is passed to the value transformer
        $transformer = $this->createMockTransformer();
        $transformer->expects($this->once())
                                ->method('transform')
                                ->with($this->identicalTo(0))
                                ->will($this->returnValue('transform[0]'));

        $this->field->setValueTransformer($transformer);
        $this->field->setData(0);

        $this->assertEquals(0, $this->field->getData());
        $this->assertEquals('transform[0]', $this->field->getDisplayedData());
    }

    public function testBoundValuesAreTrimmedBeforeTransforming()
    {
        // The value is passed to the value transformer
        $transformer = $this->createMockTransformer();
        $transformer->expects($this->once())
                                ->method('reverseTransform')
                                ->with($this->identicalTo('a'))
                                ->will($this->returnValue('reverse[a]'));

        $transformer->expects($this->once())
                                ->method('transform')
                                ->with($this->identicalTo('reverse[a]'))
                                ->will($this->returnValue('a'));

        $this->field->setValueTransformer($transformer);
        $this->field->bind(' a ');

        $this->assertEquals('a', $this->field->getDisplayedData());
        $this->assertEquals('reverse[a]', $this->field->getData());
    }

    public function testBoundValuesAreNotTrimmedBeforeTransformingIfDisabled()
    {
        // The value is passed to the value transformer
        $transformer = $this->createMockTransformer();
        $transformer->expects($this->once())
                                ->method('reverseTransform')
                                ->with($this->identicalTo(' a '))
                                ->will($this->returnValue('reverse[ a ]'));

        $transformer->expects($this->once())
                                ->method('transform')
                                ->with($this->identicalTo('reverse[ a ]'))
                                ->will($this->returnValue(' a '));

        $field = new TestField('title', array('trim' => false));
        $field->setValueTransformer($transformer);
        $field->bind(' a ');

        $this->assertEquals(' a ', $field->getDisplayedData());
        $this->assertEquals('reverse[ a ]', $field->getData());
    }

    public function testUpdateFromObjectReadsArray()
    {
        $array = array('firstName' => 'Bernhard');

        $field = new TestField('firstName');
        $field->updateFromObject($array);

        $this->assertEquals('Bernhard', $field->getData());
    }

    public function testUpdateFromObjectReadsArrayWithCustomPropertyPath()
    {
        $array = array('child' => array('index' => array('firstName' => 'Bernhard')));

        $field = new TestField('firstName', array('property_path' => 'child[index].firstName'));
        $field->updateFromObject($array);

        $this->assertEquals('Bernhard', $field->getData());
    }

    public function testUpdateFromObjectReadsProperty()
    {
        $object = new Author();
        $object->firstName = 'Bernhard';

        $field = new TestField('firstName');
        $field->updateFromObject($object);

        $this->assertEquals('Bernhard', $field->getData());
    }

    public function testUpdateFromObjectReadsPropertyWithCustomPropertyPath()
    {
        $object = new Author();
        $object->child = array();
        $object->child['index'] = new Author();
        $object->child['index']->firstName = 'Bernhard';

        $field = new TestField('firstName', array('property_path' => 'child[index].firstName'));
        $field->updateFromObject($object);

        $this->assertEquals('Bernhard', $field->getData());
    }

    public function testUpdateFromObjectReadsArrayAccess()
    {
        $object = new \ArrayObject();
        $object['firstName'] = 'Bernhard';

        $field = new TestField('firstName', array('property_path' => '[firstName]'));
        $field->updateFromObject($object);

        $this->assertEquals('Bernhard', $field->getData());
    }

    public function testUpdateFromObjectThrowsExceptionIfArrayAccessExpected()
    {
        $field = new TestField('firstName', array('property_path' => '[firstName]'));

        $this->setExpectedException('Symfony\Components\Form\Exception\InvalidPropertyException');
        $field->updateFromObject(new Author());
    }

    /*
     * The use case of this test is a field group with an empty property path.
     * Even if the field group itself is not associated to a specific property,
     * nested fields might be.
     */
    public function testUpdateFromObjectPassesObjectThroughIfPropertyPathIsEmpty()
    {
        $object = new Author();
        $object->firstName = 'Bernhard';

        $field = new TestField('firstName', array('property_path' => null));
        $field->updateFromObject($object);

        $this->assertEquals($object, $field->getData());
    }

    public function testUpdateFromObjectThrowsExceptionIfPropertyIsNotPublic()
    {
        $field = new TestField('privateProperty');

        $this->setExpectedException('Symfony\Components\Form\Exception\PropertyAccessDeniedException');
        $field->updateFromObject(new Author());
    }

    public function testUpdateFromObjectReadsGetters()
    {
        $object = new Author();
        $object->setLastName('Schussek');

        $field = new TestField('lastName');
        $field->updateFromObject($object);

        $this->assertEquals('Schussek', $field->getData());
    }

    public function testUpdateFromObjectThrowsExceptionIfGetterIsNotPublic()
    {
        $field = new TestField('privateGetter');

        $this->setExpectedException('Symfony\Components\Form\Exception\PropertyAccessDeniedException');
        $field->updateFromObject(new Author());
    }

    public function testUpdateFromObjectReadsIssers()
    {
        $object = new Author();
        $object->setAustralian(false);

        $field = new TestField('australian');
        $field->updateFromObject($object);

        $this->assertSame(false, $field->getData());
    }

    public function testUpdateFromObjectThrowsExceptionIfIsserIsNotPublic()
    {
        $field = new TestField('privateIsser');

        $this->setExpectedException('Symfony\Components\Form\Exception\PropertyAccessDeniedException');
        $field->updateFromObject(new Author());
    }

    public function testUpdateFromObjectThrowsExceptionIfPropertyDoesNotExist()
    {
        $field = new TestField('foobar');

        $this->setExpectedException('Symfony\Components\Form\Exception\InvalidPropertyException');
        $field->updateFromObject(new Author());
    }

    public function testUpdateObjectUpdatesArrays()
    {
        $array = array();

        $field = new TestField('firstName');
        $field->bind('Bernhard');
        $field->updateObject($array);

        $this->assertEquals(array('firstName' => 'Bernhard'), $array);
    }

    public function testUpdateObjectUpdatesArraysWithCustomPropertyPath()
    {
        $array = array();

        $field = new TestField('firstName', array('property_path' => 'child[index].firstName'));
        $field->bind('Bernhard');
        $field->updateObject($array);

        $this->assertEquals(array('child' => array('index' => array('firstName' => 'Bernhard'))), $array);
    }

    /*
     * This is important so that bind() can work even if setData() was not called
     * before
     */
    public function testUpdateObjectTreatsEmptyValuesAsArrays()
    {
        $array = null;

        $field = new TestField('firstName');
        $field->bind('Bernhard');
        $field->updateObject($array);

        $this->assertEquals(array('firstName' => 'Bernhard'), $array);
    }

    public function testUpdateObjectUpdatesProperties()
    {
        $object = new Author();

        $field = new TestField('firstName');
        $field->bind('Bernhard');
        $field->updateObject($object);

        $this->assertEquals('Bernhard', $object->firstName);
    }

    public function testUpdateObjectUpdatesPropertiesWithCustomPropertyPath()
    {
        $object = new Author();
        $object->child = array();
        $object->child['index'] = new Author();

        $field = new TestField('firstName', array('property_path' => 'child[index].firstName'));
        $field->bind('Bernhard');
        $field->updateObject($object);

        $this->assertEquals('Bernhard', $object->child['index']->firstName);
    }

    public function testUpdateObjectUpdatesArrayAccess()
    {
        $object = new \ArrayObject();

        $field = new TestField('firstName', array('property_path' => '[firstName]'));
        $field->bind('Bernhard');
        $field->updateObject($object);

        $this->assertEquals('Bernhard', $object['firstName']);
    }

    public function testUpdateObjectThrowsExceptionIfArrayAccessExpected()
    {
        $field = new TestField('firstName', array('property_path' => '[firstName]'));
        $field->bind('Bernhard');

        $this->setExpectedException('Symfony\Components\Form\Exception\InvalidPropertyException');
        $field->updateObject(new Author());
    }

    public function testUpdateObjectDoesNotUpdatePropertyIfPropertyPathIsEmpty()
    {
        $object = new Author();

        $field = new TestField('firstName', array('property_path' => null));
        $field->bind('Bernhard');
        $field->updateObject($object);

        $this->assertEquals(null, $object->firstName);
    }

    public function testUpdateObjectUpdatesSetters()
    {
        $object = new Author();

        $field = new TestField('lastName');
        $field->bind('Schussek');
        $field->updateObject($object);

        $this->assertEquals('Schussek', $object->getLastName());
    }

    public function testUpdateObjectThrowsExceptionIfGetterIsNotPublic()
    {
        $field = new TestField('privateSetter');
        $field->bind('foobar');

        $this->setExpectedException('Symfony\Components\Form\Exception\PropertyAccessDeniedException');
        $field->updateObject(new Author());
    }

    protected function createMockTransformer()
    {
        return $this->getMock('Symfony\Components\Form\ValueTransformer\ValueTransformerInterface', array(), array(), '', false, false);
    }

    protected function createMockTransformerTransformingTo($value)
    {
        $transformer = $this->createMockTransformer();
        $transformer->expects($this->any())
                                ->method('reverseTransform')
                                ->will($this->returnValue($value));

        return $transformer;
    }

    protected function createMockGroup()
    {
        return $this->getMock(
            'Symfony\Components\Form\FieldGroup',
            array(),
            array(),
            '',
            false // don't call constructor
        );
    }

    protected function createMockGroupWithName($name)
    {
        $group = $this->createMockGroup();
        $group->expects($this->any())
                                ->method('getName')
                                ->will($this->returnValue($name));

        return $group;
    }

    protected function createMockGroupWithId($id)
    {
        $group = $this->createMockGroup();
        $group->expects($this->any())
                                ->method('getId')
                                ->will($this->returnValue($id));

        return $group;
    }
}
