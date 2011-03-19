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
require_once __DIR__ . '/Fixtures/FixedDataTransformer.php';
require_once __DIR__ . '/Fixtures/FixedFilterListener.php';

use Symfony\Component\Form\DataTransformer\DataTransformerInterface;
use Symfony\Component\Form\PropertyPath;
use Symfony\Component\Form\FieldError;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\DataTransformer\TransformationFailedException;
use Symfony\Tests\Component\Form\Fixtures\Author;
use Symfony\Tests\Component\Form\Fixtures\InvalidField;
use Symfony\Tests\Component\Form\Fixtures\FixedDataTransformer;
use Symfony\Tests\Component\Form\Fixtures\FixedFilterListener;

class FieldTest extends TestCase
{
    protected $field;

    protected function setUp()
    {
        parent::setUp();

        $this->field = $this->factory->create('field', 'title');
    }

    public function testGetPropertyPath_defaultPath()
    {
        $field = $this->factory->create('field', 'title');

        $this->assertEquals(new PropertyPath('title'), $field->getAttribute('property_path'));
    }

    public function testGetPropertyPath_pathIsZero()
    {
        $field = $this->factory->create('field', 'title', array('property_path' => '0'));

        $this->assertEquals(new PropertyPath('0'), $field->getAttribute('property_path'));
    }

    public function testGetPropertyPath_pathIsEmpty()
    {
        $field = $this->factory->create('field', 'title', array('property_path' => ''));

        $this->assertEquals(null, $field->getAttribute('property_path'));
    }

    public function testGetPropertyPath_pathIsNull()
    {
        $field = $this->factory->create('field', 'title', array('property_path' => null));

        $this->assertEquals(null, $field->getAttribute('property_path'));
    }

    public function testPassRequiredAsOption()
    {
        $field = $this->factory->create('field', 'title', array('required' => false));

        $this->assertFalse($field->isRequired());

        $field = $this->factory->create('field', 'title', array('required' => true));

        $this->assertTrue($field->isRequired());
    }

    public function testPassDisabledAsOption()
    {
        $field = $this->factory->create('field', 'title', array('disabled' => false));

        $this->assertFalse($field->isDisabled());

        $field = $this->factory->create('field', 'title', array('disabled' => true));

        $this->assertTrue($field->isDisabled());
    }

    public function testFieldIsDisabledIfParentIsDisabled()
    {
        $field = $this->factory->create('field', 'title', array('disabled' => false));
        $field->setParent($this->factory->create('field', 'title', array('disabled' => true)));

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
        $this->field->addError(new FieldError('Some error'));

        $this->assertFalse($this->field->isValid());
    }

    public function testSubmitResetsErrors()
    {
        $this->field->addError(new FieldError('Some error'));
        $this->field->bind('data');

        $this->assertTrue($this->field->isValid());
    }

    public function testUnboundFieldIsInvalid()
    {
        $this->assertFalse($this->field->isValid());
    }

    public function testIsRequiredReturnsOwnValueIfNoParent()
    {
        $field = $this->factory->create('field', 'test', array(
            'required' => true,
        ));

        $this->assertTrue($field->isRequired());

        $field = $this->factory->create('field', 'test', array(
            'required' => false,
        ));

        $this->assertFalse($field->isRequired());
    }

    public function testIsRequiredReturnsOwnValueIfParentIsRequired()
    {
        $group = $this->createMockGroup();
        $group->expects($this->any())
                    ->method('isRequired')
                    ->will($this->returnValue(true));

        $field = $this->factory->create('field', 'test', array(
            'required' => true,
        ));
        $field->setParent($group);

        $this->assertTrue($field->isRequired());

        $field = $this->factory->create('field', 'test', array(
            'required' => false,
        ));
        $field->setParent($group);

        $this->assertFalse($field->isRequired());
    }

    public function testIsRequiredReturnsFalseIfParentIsNotRequired()
    {
        $group = $this->createMockGroup();
        $group->expects($this->any())
                    ->method('isRequired')
                    ->will($this->returnValue(false));

        $field = $this->factory->create('field', 'test', array(
            'required' => true,
        ));
        $field->setParent($group);

        $this->assertFalse($field->isRequired());
    }

    public function testIsBound()
    {
        $this->assertFalse($this->field->isBound());
        $this->field->bind('symfony');
        $this->assertTrue($this->field->isBound());
    }

    public function testDefaultDataIsTransformedCorrectly()
    {
        $field = $this->factory->create('field', 'name');

        $this->assertEquals(null, $this->field->getData());
        $this->assertEquals('', $this->field->getClientData());
    }

    public function testDataIsTransformedCorrectlyIfNull_noDataTransformer()
    {
        $this->field->setData(null);

        $this->assertSame(null, $this->field->getData());
        $this->assertSame('', $this->field->getClientData());
    }

    public function testDataIsTransformedCorrectlyIfNotNull_noDataTransformer()
    {
        $this->field->setData(123);

        // The values are synchronized
        // Without value transformer, the field can't know that the data
        // should be casted to an integer when the field is bound
        // Even without binding, the data will thus be a string
        $this->assertSame('123', $this->field->getData());
        $this->assertSame('123', $this->field->getClientData());
    }

    public function testBoundDataIsTransformedCorrectly()
    {
        $filter = new FixedFilterListener(array(
            'filterBoundClientData' => array(
                // 1. The value is converted to a string and passed to the
                //    first filter
                '0' => 'filter1[0]',
            ),
            'filterBoundNormData' => array(
                // 3. The normalized value is passed to the second filter
                'norm[filter1[0]]' => 'filter2[norm[filter1[0]]]',
            ),
        ));
        $clientTransformer = new FixedDataTransformer(array(
            // 0. Empty initialization
            null => null,
            // 2. The filtered value is normalized
            'norm[filter1[0]]' => 'filter1[0]',
            // 4a. The filtered normalized value is converted to client
            //     representation
            'filter2[norm[filter1[0]]]' => 'client[filter2[norm[filter1[0]]]]',
        ));
        $normTransformer = new FixedDataTransformer(array(
            // 0. Empty initialization
            null => null,
            // 4b. The filtered normalized value is converted to app
            //     representation
            'app[filter2[norm[filter1[0]]]]' => 'filter2[norm[filter1[0]]]',
        ));

        $this->builder->addEventSubscriber($filter);
        $this->builder->setClientTransformer($clientTransformer);
        $this->builder->setNormTransformer($normTransformer);

        $field = $this->builder->getInstance();
        $field->bind(0);

        $this->assertEquals('app[filter2[norm[filter1[0]]]]', $field->getData());
        $this->assertEquals('filter2[norm[filter1[0]]]', $field->getNormData());
        $this->assertEquals('client[filter2[norm[filter1[0]]]]', $field->getClientData());
    }

    public function testBoundDataIsTransformedCorrectlyIfEmpty_noDataTransformer()
    {
        $this->field->bind('');

        $this->assertSame(null, $this->field->getData());
        $this->assertEquals('', $this->field->getClientData());
    }

    public function testSetDataIsTransformedCorrectly()
    {
        $normTransformer = new FixedDataTransformer(array(
            null => '',
            0 => 'norm[0]',
        ));

        $clientTransformer = new FixedDataTransformer(array(
            '' => '',
            'norm[0]' => 'transform[norm[0]]',
        ));

        $builder = $this->factory->createBuilder('field', 'title');
        $builder->setNormTransformer($normTransformer);
        $builder->setClientTransformer($clientTransformer);
        $field = $builder->getInstance();

        $field->setData(0);

        $this->assertEquals(0, $field->getData());
        $this->assertEquals('norm[0]', $field->getNormData());
        $this->assertEquals('transform[norm[0]]', $field->getClientData());
    }

    public function testBoundDataIsTrimmedBeforeTransforming()
    {
        $clientTransformer = new FixedDataTransformer(array(
            null => '',
            'reverse[a]' => 'a',
        ));

        $builder = $this->factory->createBuilder('field', 'title');
        $builder->setClientTransformer($clientTransformer);
        $field = $builder->getInstance();

        $field->bind(' a ');

        $this->assertEquals('a', $field->getClientData());
        $this->assertEquals('reverse[a]', $field->getData());
    }

    public function testBoundDataIsNotTrimmedBeforeTransformingIfDisabled()
    {
        $clientTransformer = new FixedDataTransformer(array(
            null => '',
            'reverse[ a ]' => ' a ',
        ));

        $builder = $this->factory->createBuilder('field', 'title', array(
            'trim' => false,
        ));
        $builder->setClientTransformer($clientTransformer);
        $field = $builder->getInstance();

        $field->bind(' a ');

        $this->assertEquals(' a ', $field->getClientData());
        $this->assertEquals('reverse[ a ]', $field->getData());
    }

    public function testIsTransformationSuccessfulReturnsTrueIfReverseTransformSucceeded()
    {
        $field = $this->factory->create('field', 'title', array(
            'trim' => false,
        ));

        $field->bind('a');

        $this->assertEquals('a', $field->getClientData());
        $this->assertTrue($field->isTransformationSuccessful());
    }

    public function testIsTransformationSuccessfulReturnsFalseIfReverseTransformThrowsException()
    {
        // The value is passed to the value transformer
        $clientTransformer = $this->createMockTransformer();

        $builder = $this->factory->createBuilder('field', 'title', array(
            'trim' => false,
        ));
        $builder->setClientTransformer($clientTransformer);
        $field = $builder->getInstance();

        $clientTransformer->expects($this->once())
                ->method('reverseTransform')
                ->will($this->throwException(new TransformationFailedException()));

        $field->bind('a');

        $this->assertEquals('a', $field->getClientData());
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
        return $this->getMock('Symfony\Component\Form\DataTransformer\DataTransformerInterface', array(), array(), '', false, false);
    }

    protected function createMockTransformerTransformingTo($value)
    {
        $clientTransformer = $this->createMockTransformer();
        $clientTransformer->expects($this->any())
                                ->method('reverseTransform')
                                ->will($this->returnValue($value));

        return $clientTransformer;
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
