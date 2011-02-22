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

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/Fixtures/Author.php';
require_once __DIR__ . '/Fixtures/InvalidField.php';

use Symfony\Component\Form\ValueTransformer\ValueTransformerInterface;
use Symfony\Component\Form\PropertyPath;
use Symfony\Component\Form\FieldError;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\ValueTransformer\TransformationFailedException;
use Symfony\Tests\Component\Form\Fixtures\Author;
use Symfony\Tests\Component\Form\Fixtures\InvalidField;

class FieldTest extends TestCase
{
    protected $field;

    protected function setUp()
    {
        parent::setUp();

        $this->field = $this->factory->getField('title');
    }

    public function testGetPropertyPath_defaultPath()
    {
        $field = $this->factory->getField('title');

        $this->assertEquals(new PropertyPath('title'), $field->getPropertyPath());
    }

    public function testGetPropertyPath_pathIsZero()
    {
        $field = $this->factory->getField('title', array('property_path' => '0'));

        $this->assertEquals(new PropertyPath('0'), $field->getPropertyPath());
    }

    public function testGetPropertyPath_pathIsEmpty()
    {
        $field = $this->factory->getField('title', array('property_path' => ''));

        $this->assertEquals(null, $field->getPropertyPath());
    }

    public function testGetPropertyPath_pathIsNull()
    {
        $field = $this->factory->getField('title', array('property_path' => null));

        $this->assertEquals(null, $field->getPropertyPath());
    }

    public function testPassRequiredAsOption()
    {
        $field = $this->factory->getField('title', array('required' => false));

        $this->assertFalse($field->isRequired());

        $field = $this->factory->getField('title', array('required' => true));

        $this->assertTrue($field->isRequired());
    }

    public function testPassDisabledAsOption()
    {
        $field = $this->factory->getField('title', array('disabled' => false));

        $this->assertFalse($field->isDisabled());

        $field = $this->factory->getField('title', array('disabled' => true));

        $this->assertTrue($field->isDisabled());
    }

    public function testFieldIsDisabledIfParentIsDisabled()
    {
        $field = $this->factory->getField('title', array('disabled' => false));
        $field->setParent($this->factory->getField('title', array('disabled' => true)));

        $this->assertTrue($field->isDisabled());
    }

    public function testFieldWithNoErrorsIsValid()
    {
        $this->field->submit('data');

        $this->assertTrue($this->field->isValid());
    }

    public function testFieldWithErrorsIsInvalid()
    {
        $this->field->submit('data');
        $this->field->addError(new FieldError('Some error'));

        $this->assertFalse($this->field->isValid());
    }

    public function testSubmitResetsErrors()
    {
        $this->field->addError(new FieldError('Some error'));
        $this->field->submit('data');

        $this->assertTrue($this->field->isValid());
    }

    public function testUnsubmittedFieldIsInvalid()
    {
        $this->assertFalse($this->field->isValid());
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

    public function testIsSubmitted()
    {
        $this->assertFalse($this->field->isSubmitted());
        $this->field->submit('symfony');
        $this->assertTrue($this->field->isSubmitted());
    }

    public function testDefaultValuesAreTransformedCorrectly()
    {
        $field = $this->factory->getField('name');

        $this->assertEquals(null, $this->field->getData());
        $this->assertEquals('', $this->field->getDisplayedData());
    }

    public function testValuesAreTransformedCorrectlyIfNull_noValueTransformer()
    {
        $this->field->setData(null);

        $this->assertSame(null, $this->field->getData());
        $this->assertSame('', $this->field->getDisplayedData());
    }

    public function testValuesAreTransformedCorrectlyIfNotNull_noValueTransformer()
    {
        $this->field->setData(123);

        $this->assertSame(123, $this->field->getData());
        $this->assertSame('123', $this->field->getDisplayedData());
    }

    public function testSubmittedValuesAreTransformedCorrectly()
    {
        $valueTransformer = $this->createMockTransformer();
        $normTransformer = $this->createMockTransformer();

        $field = $this->getMock(
            'Symfony\Component\Form\Field',
            array('processData'), // only mock processData()
            array('title')
        );
        $field->setValueTransformer($valueTransformer);
        $field->setNormalizationTransformer($normTransformer);

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

        $field->submit(0);

        $this->assertEquals('denorm[processed[reverse[0]]]', $field->getData());
        $this->assertEquals('processed[reverse[0]]', $field->getNormalizedData());
        $this->assertEquals('transform[processed[reverse[0]]]', $field->getDisplayedData());
    }

    public function testSubmittedValuesAreTransformedCorrectlyIfEmpty_processDataReturnsValue()
    {
        $transformer = $this->createMockTransformer();

        $field = $this->getMock(
            'Symfony\Component\Form\Field',
            array('processData'), // only mock processData()
            array('title')
        );
        $field->setValueTransformer($transformer);

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

        $field->submit('');

        $this->assertSame('processed', $field->getData());
        $this->assertEquals('transform[processed]', $field->getDisplayedData());
    }

    public function testSubmittedValuesAreTransformedCorrectlyIfEmpty_processDataReturnsNull()
    {
        $transformer = $this->createMockTransformer();

        $field = $this->factory->getField('title', array(
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

        $field->submit('');

        $this->assertSame(null, $field->getData());
        $this->assertEquals('', $field->getDisplayedData());
    }

    public function testSubmittedValuesAreTransformedCorrectlyIfEmpty_processDataReturnsNull_noValueTransformer()
    {
        $this->field->submit('');

        $this->assertSame(null, $this->field->getData());
        $this->assertEquals('', $this->field->getDisplayedData());
    }

    public function testValuesAreTransformedCorrectly()
    {
        // The value is first passed to the normalization transformer...
        $normTransformer = $this->createMockTransformer();
        $normTransformer->expects($this->once())
                                ->method('transform')
                                ->with($this->identicalTo(0))
                                ->will($this->returnValue('norm[0]'));

        // ...and then to the value transformer
        $valueTransformer = $this->createMockTransformer();
        $valueTransformer->expects($this->once())
                                ->method('transform')
                                ->with($this->identicalTo('norm[0]'))
                                ->will($this->returnValue('transform[norm[0]]'));

        $field = $this->factory->getField('title', array(
            'value_transformer' => $valueTransformer,
            'normalization_transformer' => $normTransformer,
        ));

        $field->setData(0);

        $this->assertEquals(0, $field->getData());
        $this->assertEquals('norm[0]', $field->getNormalizedData());
        $this->assertEquals('transform[norm[0]]', $field->getDisplayedData());
    }

    public function testSubmittedValuesAreTrimmedBeforeTransforming()
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

        $field = $this->factory->getField('title', array(
            'value_transformer' => $transformer,
        ));

        $field->submit(' a ');

        $this->assertEquals('a', $field->getDisplayedData());
        $this->assertEquals('reverse[a]', $field->getData());
    }

    public function testSubmittedValuesAreNotTrimmedBeforeTransformingIfDisabled()
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

        $field = $this->factory->getField('title', array(
            'trim' => false,
            'value_transformer' => $transformer,
        ));

        $field->submit(' a ');

        $this->assertEquals(' a ', $field->getDisplayedData());
        $this->assertEquals('reverse[ a ]', $field->getData());
    }

    /*
     * This is important so that submit() can work even if setData() was not called
     * before
     */
    public function testWritePropertyTreatsEmptyValuesAsArrays()
    {
        $array = null;

        $field = $this->factory->getField('firstName');
        $field->submit('Bernhard');
        $field->writeProperty($array);

        $this->assertEquals(array('firstName' => 'Bernhard'), $array);
    }

    public function testWritePropertyDoesNotWritePropertyIfPropertyPathIsEmpty()
    {
        $object = new Author();

        $field = $this->factory->getField('firstName', array('property_path' => null));
        $field->submit('Bernhard');
        $field->writeProperty($object);

        $this->assertEquals(null, $object->firstName);
    }

    public function testIsTransformationSuccessfulReturnsTrueIfReverseTransformSucceeded()
    {
        $field = $this->factory->getField('title', array(
            'trim' => false,
        ));

        $field->submit('a');

        $this->assertEquals('a', $field->getDisplayedData());
        $this->assertTrue($field->isTransformationSuccessful());
    }

    public function testIsTransformationSuccessfulReturnsFalseIfReverseTransformThrowsException()
    {
        // The value is passed to the value transformer
        $transformer = $this->createMockTransformer();
        $transformer->expects($this->once())
                ->method('reverseTransform')
                ->will($this->throwException(new TransformationFailedException()));

        $field = $this->factory->getField('title', array(
            'trim' => false,
            'value_transformer' => $transformer,
        ));

        $field->submit('a');

        $this->assertEquals('a', $field->getDisplayedData());
        $this->assertFalse($field->isTransformationSuccessful());
    }

    public function testGetRootReturnsRootOfParentIfSet()
    {
        $parent = $this->createMockGroup();
        $parent->expects($this->any())
                ->method('getRoot')
                ->will($this->returnValue('ROOT'));

        $this->field->setParent($parent);

        $this->assertEquals('ROOT', $this->field->getRoot());
    }

    public function testFieldsInitializedWithDataAreNotUpdatedWhenAddedToForms()
    {
        $author = new Author();
        $author->firstName = 'Bernhard';

        $field = $this->factory->getField('firstName', array(
            'data' => 'foobar',
        ));

        $form = new Form('author', array(
            'data' => $author,
        ));
        $form->add($field);

        $this->assertEquals('foobar', $field->getData());
    }

    public function testGetRootReturnsFieldIfNoParent()
    {
        $this->assertEquals($this->field, $this->field->getRoot());
    }

    public function testIsEmptyReturnsTrueIfNull()
    {
        $this->field->setData(null);

        $this->assertTrue($this->field->isEmpty());
    }

    public function testIsEmptyReturnsTrueIfEmptyString()
    {
        $this->field->setData('');

        $this->assertTrue($this->field->isEmpty());
    }

    public function testIsEmptyReturnsFalseIfZero()
    {
        $this->field->setData(0);

        $this->assertFalse($this->field->isEmpty());
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
            'Symfony\Component\Form\Form',
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
