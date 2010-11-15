<?php

namespace Symfony\Tests\Component\Form;

require_once __DIR__ . '/Fixtures/Author.php';
require_once __DIR__ . '/Fixtures/TestField.php';
require_once __DIR__ . '/Fixtures/InvalidField.php';
require_once __DIR__ . '/Fixtures/RequiredOptionsField.php';

use Symfony\Component\Form\ValueTransformer\ValueTransformerInterface;
use Symfony\Component\Form\PropertyPath;
use Symfony\Tests\Component\Form\Fixtures\Author;
use Symfony\Tests\Component\Form\Fixtures\TestField;
use Symfony\Tests\Component\Form\Fixtures\InvalidField;
use Symfony\Tests\Component\Form\Fixtures\RequiredOptionsField;

class FieldTest extends \PHPUnit_Framework_TestCase
{
    protected $field;

    protected function setUp()
    {
        $this->field = new TestField('title');
    }

    public function testGetPropertyPath_defaultPath()
    {
        $field = new TestField('title');

        $this->assertEquals(new PropertyPath('title'), $field->getPropertyPath());
    }

    public function testGetPropertyPath_pathIsZero()
    {
        $field = new TestField('title', array('property_path' => '0'));

        $this->assertEquals(new PropertyPath('0'), $field->getPropertyPath());
    }

    public function testGetPropertyPath_pathIsEmpty()
    {
        $field = new TestField('title', array('property_path' => ''));

        $this->assertEquals(null, $field->getPropertyPath());
    }

    public function testGetPropertyPath_pathIsNull()
    {
        $field = new TestField('title', array('property_path' => null));

        $this->assertEquals(null, $field->getPropertyPath());
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

//    public function testLocaleIsPassedToLocalizableValueTransformer_setLocaleCalledBefore()
//    {
//        $transformer = $this->getMock('Symfony\Component\Form\ValueTransformer\ValueTransformerInterface');
//        $transformer->expects($this->once())
//                         ->method('setLocale')
//                         ->with($this->equalTo('de_DE'));
//
//        $this->field->setLocale('de_DE');
//        $this->field->setValueTransformer($transformer);
//    }

    public function testLocaleIsPassedToValueTransformer_setLocaleCalledAfter()
    {
        $transformer = $this->getMock('Symfony\Component\Form\ValueTransformer\ValueTransformerInterface');
        $transformer->expects($this->exactly(2))
                         ->method('setLocale'); // we can't test the params cause they differ :(

        $field = new TestField('title', array(
            'value_transformer' => $transformer,
        ));

        $field->setLocale('de_DE');
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
        $this->setExpectedException('Symfony\Component\Form\Exception\InvalidOptionsException');

        new RequiredOptionsField('name', array('bar' => 'baz', 'moo' => 'maa'));
    }

    public function testExceptionIfMissingOption()
    {
        $this->setExpectedException('Symfony\Component\Form\Exception\MissingOptionsException');

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

    public function testValuesAreTransformedCorrectlyIfNull_noValueTransformer()
    {
        $this->field->setData(null);

        $this->assertSame(null, $this->field->getData());
        $this->assertSame('', $this->field->getDisplayedData());
    }

    public function testBoundValuesAreTransformedCorrectly()
    {
        $valueTransformer = $this->createMockTransformer();
        $normTransformer = $this->createMockTransformer();

        $field = $this->getMock(
            'Symfony\Tests\Component\Form\Fixtures\TestField',
            array('processData'), // only mock processData()
            array('title', array(
                'value_transformer' => $valueTransformer,
                'normalization_transformer' => $normTransformer,
            ))
        );

        // 1a. The value is converted to a string and passed to the value transformer
        $valueTransformer->expects($this->once())
                                ->method('reverseTransform')
                                ->with($this->identicalTo('0'))
                                ->will($this->returnValue('reverse[0]'));

        // 2. The output of the reverse transformation is passed to processData()
        //    The processed data is accessible through getNormalizedData()
        $field->expects($this->once())
                    ->method('processData')
                    ->with($this->equalTo('reverse[0]'))
                    ->will($this->returnValue('processed[reverse[0]]'));

        // 3. The processed data is denormalized and then accessible through
        //    getData()
        $normTransformer->expects($this->once())
                                ->method('reverseTransform')
                                ->with($this->identicalTo('processed[reverse[0]]'))
                                ->will($this->returnValue('denorm[processed[reverse[0]]]'));

        // 4. The processed data is transformed again and then accessible
        //    through getDisplayedData()
        $valueTransformer->expects($this->once())
                                ->method('transform')
                                ->with($this->equalTo('processed[reverse[0]]'))
                                ->will($this->returnValue('transform[processed[reverse[0]]]'));

        $field->bind(0);

        $this->assertEquals('denorm[processed[reverse[0]]]', $field->getData());
        $this->assertEquals('processed[reverse[0]]', $field->getNormalizedData());
        $this->assertEquals('transform[processed[reverse[0]]]', $field->getDisplayedData());
    }

    public function testBoundValuesAreTransformedCorrectlyIfEmpty_processDataReturnsValue()
    {
        $transformer = $this->createMockTransformer();

        $field = $this->getMock(
            'Symfony\Tests\Component\Form\Fixtures\TestField',
            array('processData'), // only mock processData()
            array('title', array(
                'value_transformer' => $transformer,
            ))
        );

        // 1. Empty values are converted to NULL by convention
        $transformer->expects($this->once())
                                ->method('reverseTransform')
                                ->with($this->identicalTo(''))
                                ->will($this->returnValue(null));

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
        $transformer = $this->createMockTransformer();

        $field = new TestField('title', array(
            'value_transformer' => $transformer,
        ));

        // 1. Empty values are converted to NULL by convention
        $transformer->expects($this->once())
                                ->method('reverseTransform')
                                ->with($this->identicalTo(''))
                                ->will($this->returnValue(null));

        // 2. The processed data is NULL and therefore transformed to an empty
        //    string by convention
        $transformer->expects($this->once())
                                ->method('transform')
                                ->with($this->identicalTo(null))
                                ->will($this->returnValue(''));

        $field->bind('');

        $this->assertSame(null, $field->getData());
        $this->assertEquals('', $field->getDisplayedData());
    }

    public function testBoundValuesAreTransformedCorrectlyIfEmpty_processDataReturnsNull_noValueTransformer()
    {
        $this->field->bind('');

        $this->assertSame(null, $this->field->getData());
        $this->assertEquals('', $this->field->getDisplayedData());
    }

    public function testValuesAreTransformedCorrectly()
    {
        // The value is first passed to the normalization transformer...
        $normTransformer = $this->createMockTransformer();
        $normTransformer->expects($this->exactly(2))
                                ->method('transform')
                                // Impossible to test with PHPUnit because called twice
                                // ->with($this->identicalTo(0))
                                ->will($this->returnValue('norm[0]'));

        // ...and then to the value transformer
        $valueTransformer = $this->createMockTransformer();
        $valueTransformer->expects($this->exactly(2))
                                ->method('transform')
                                // Impossible to test with PHPUnit because called twice
                                // ->with($this->identicalTo('norm[0]'))
                                ->will($this->returnValue('transform[norm[0]]'));

        $field = new TestField('title', array(
            'value_transformer' => $valueTransformer,
            'normalization_transformer' => $normTransformer,
        ));

        $field->setData(0);

        $this->assertEquals(0, $field->getData());
        $this->assertEquals('norm[0]', $field->getNormalizedData());
        $this->assertEquals('transform[norm[0]]', $field->getDisplayedData());
    }

    public function testBoundValuesAreTrimmedBeforeTransforming()
    {
        // The value is passed to the value transformer
        $transformer = $this->createMockTransformer();
        $transformer->expects($this->once())
                                ->method('reverseTransform')
                                ->with($this->identicalTo('a'))
                                ->will($this->returnValue('reverse[a]'));

        $transformer->expects($this->exactly(2))
                                ->method('transform')
                                // Impossible to test with PHPUnit because called twice
                                // ->with($this->identicalTo('reverse[a]'))
                                ->will($this->returnValue('a'));

        $field = new TestField('title', array(
            'value_transformer' => $transformer,
        ));

        $field->bind(' a ');

        $this->assertEquals('a', $field->getDisplayedData());
        $this->assertEquals('reverse[a]', $field->getData());
    }

    public function testBoundValuesAreNotTrimmedBeforeTransformingIfDisabled()
    {
        // The value is passed to the value transformer
        $transformer = $this->createMockTransformer();
        $transformer->expects($this->once())
                                ->method('reverseTransform')
                                ->with($this->identicalTo(' a '))
                                ->will($this->returnValue('reverse[ a ]'));

        $transformer->expects($this->exactly(2))
                                ->method('transform')
                                // Impossible to test with PHPUnit because called twice
                                // ->with($this->identicalTo('reverse[ a ]'))
                                ->will($this->returnValue(' a '));

        $field = new TestField('title', array(
        	'trim' => false,
            'value_transformer' => $transformer,
        ));

        $field->bind(' a ');

        $this->assertEquals(' a ', $field->getDisplayedData());
        $this->assertEquals('reverse[ a ]', $field->getData());
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

    public function testUpdateObjectDoesNotUpdatePropertyIfPropertyPathIsEmpty()
    {
        $object = new Author();

        $field = new TestField('firstName', array('property_path' => null));
        $field->bind('Bernhard');
        $field->updateObject($object);

        $this->assertEquals(null, $object->firstName);
    }

    protected function createMockTransformer()
    {
        return $this->getMock('Symfony\Component\Form\ValueTransformer\ValueTransformerInterface', array(), array(), '', false, false);
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
            'Symfony\Component\Form\FieldGroup',
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
