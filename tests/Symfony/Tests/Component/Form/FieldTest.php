<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form;

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/Fixtures/Author.php';
require_once __DIR__ . '/Fixtures/InvalidField.php';
require_once __DIR__ . '/Fixtures/FixedValueTransformer.php';
require_once __DIR__ . '/Fixtures/FixedFilter.php';

use Symfony\Component\Form\ValueTransformer\ValueTransformerInterface;
use Symfony\Component\Form\PropertyPath;
use Symfony\Component\Form\FieldError;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\ValueTransformer\TransformationFailedException;
use Symfony\Tests\Component\Form\Fixtures\Author;
use Symfony\Tests\Component\Form\Fixtures\InvalidField;
use Symfony\Tests\Component\Form\Fixtures\FixedValueTransformer;
use Symfony\Tests\Component\Form\Fixtures\FixedFilter;

class FieldTest extends TestCase
{
    protected $field;

    protected function setUp()
    {
        parent::setUp();

        $this->field = $this->factory->getInstance('field', 'title');
    }

    public function testGetPropertyPath_defaultPath()
    {
        $field = $this->factory->getInstance('field', 'title');

        $this->assertEquals(new PropertyPath('title'), $field->getPropertyPath());
    }

    public function testGetPropertyPath_pathIsZero()
    {
        $field = $this->factory->getInstance('field', 'title', array('property_path' => '0'));

        $this->assertEquals(new PropertyPath('0'), $field->getPropertyPath());
    }

    public function testGetPropertyPath_pathIsEmpty()
    {
        $field = $this->factory->getInstance('field', 'title', array('property_path' => ''));

        $this->assertEquals(null, $field->getPropertyPath());
    }

    public function testGetPropertyPath_pathIsNull()
    {
        $field = $this->factory->getInstance('field', 'title', array('property_path' => null));

        $this->assertEquals(null, $field->getPropertyPath());
    }

    public function testPassRequiredAsOption()
    {
        $field = $this->factory->getInstance('field', 'title', array('required' => false));

        $this->assertFalse($field->isRequired());

        $field = $this->factory->getInstance('field', 'title', array('required' => true));

        $this->assertTrue($field->isRequired());
    }

    public function testPassDisabledAsOption()
    {
        $field = $this->factory->getInstance('field', 'title', array('disabled' => false));

        $this->assertFalse($field->isDisabled());

        $field = $this->factory->getInstance('field', 'title', array('disabled' => true));

        $this->assertTrue($field->isDisabled());
    }

    public function testFieldIsDisabledIfParentIsDisabled()
    {
        $field = $this->factory->getInstance('field', 'title', array('disabled' => false));
        $field->setParent($this->factory->getInstance('field', 'title', array('disabled' => true)));

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

    public function testDefaultDataIsTransformedCorrectly()
    {
        $field = $this->factory->getInstance('field', 'name');

        $this->assertEquals(null, $this->field->getData());
        $this->assertEquals('', $this->field->getDisplayedData());
    }

    public function testDataIsTransformedCorrectlyIfNull_noValueTransformer()
    {
        $this->field->setData(null);

        $this->assertSame(null, $this->field->getData());
        $this->assertSame('', $this->field->getDisplayedData());
    }

    public function testDataIsTransformedCorrectlyIfNotNull_noValueTransformer()
    {
        $this->field->setData(123);

        // The values are synchronized
        // Without value transformer, the field can't know that the data
        // should be casted to an integer when the field is bound
        // Even without binding, the data will thus be a string
        $this->assertSame('123', $this->field->getData());
        $this->assertSame('123', $this->field->getDisplayedData());
    }

    public function testSubmittedDataIsTransformedCorrectly()
    {
        $filter = new FixedFilter(array(
            'filterBoundDataFromClient' => array(
                // 1. The value is converted to a string and passed to the
                //    first filter
                '0' => 'filter1[0]',
            ),
            'filterBoundData' => array(
                // 3. The normalized value is passed to the second filter
                'norm[filter1[0]]' => 'filter2[norm[filter1[0]]]',
            ),
        ));
        $valueTransformer = new FixedValueTransformer(array(
            // 2. The filtered value is normalized
            'norm[filter1[0]]' => 'filter1[0]',
            // 4a. The filtered normalized value is converted to client
            //     representation
            'filter2[norm[filter1[0]]]' => 'client[filter2[norm[filter1[0]]]]'
        ));
        $normTransformer = new FixedValueTransformer(array(
            // 4b. The filtered normalized value is converted to app
            //     representation
            'app[filter2[norm[filter1[0]]]]' => 'filter2[norm[filter1[0]]]',
        ));

        $this->field->appendFilter($filter);
        $this->field->setValueTransformer($valueTransformer);
        $this->field->setNormalizationTransformer($normTransformer);
        $this->field->submit(0);

        $this->assertEquals('app[filter2[norm[filter1[0]]]]', $this->field->getData());
        $this->assertEquals('filter2[norm[filter1[0]]]', $this->field->getNormalizedData());
        $this->assertEquals('client[filter2[norm[filter1[0]]]]', $this->field->getDisplayedData());
    }

    public function testSubmittedDataIsTransformedCorrectlyIfEmpty_noValueTransformer()
    {
        $this->field->submit('');

        $this->assertSame(null, $this->field->getData());
        $this->assertEquals('', $this->field->getDisplayedData());
    }

    public function testSetDataIsTransformedCorrectly()
    {
        $normTransformer = new FixedValueTransformer(array(
            null => '',
            0 => 'norm[0]',
        ));

        $valueTransformer = new FixedValueTransformer(array(
            '' => '',
            'norm[0]' => 'transform[norm[0]]',
        ));

        $field = $this->factory->getInstance('field', 'title', array(
            'value_transformer' => $valueTransformer,
            'normalization_transformer' => $normTransformer,
        ));

        $field->setData(0);

        $this->assertEquals(0, $field->getData());
        $this->assertEquals('norm[0]', $field->getNormalizedData());
        $this->assertEquals('transform[norm[0]]', $field->getDisplayedData());
    }

    public function testSubmittedDataIsTrimmedBeforeTransforming()
    {
        $transformer = new FixedValueTransformer(array(
            null => '',
            'reverse[a]' => 'a',
        ));

        $field = $this->factory->getInstance('field', 'title', array(
            'value_transformer' => $transformer,
        ));

        $field->submit(' a ');

        $this->assertEquals('a', $field->getDisplayedData());
        $this->assertEquals('reverse[a]', $field->getData());
    }

    public function testSubmittedDataIsNotTrimmedBeforeTransformingIfDisabled()
    {
        $transformer = new FixedValueTransformer(array(
            null => '',
            'reverse[ a ]' => ' a ',
        ));

        $field = $this->factory->getInstance('field', 'title', array(
            'trim' => false,
            'value_transformer' => $transformer,
        ));

        $field->submit(' a ');

        $this->assertEquals(' a ', $field->getDisplayedData());
        $this->assertEquals('reverse[ a ]', $field->getData());
    }

    public function testWritePropertyDoesNotWritePropertyIfPropertyPathIsEmpty()
    {
        $object = new Author();

        $field = $this->factory->getInstance('field', 'firstName', array('property_path' => null));
        $field->submit('Bernhard');
        $field->writeProperty($object);

        $this->assertEquals(null, $object->firstName);
    }

    public function testIsTransformationSuccessfulReturnsTrueIfReverseTransformSucceeded()
    {
        $field = $this->factory->getInstance('field', 'title', array(
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

        $field = $this->factory->getInstance('field', 'title', array(
            'trim' => false,
            'value_transformer' => $transformer,
        ));

        $transformer->expects($this->once())
                ->method('reverseTransform')
                ->will($this->throwException(new TransformationFailedException()));

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
