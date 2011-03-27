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
require_once __DIR__ . '/../Fixtures/FixedDataTransformer.php';
require_once __DIR__ . '/../Fixtures/FixedFilterListener.php';

use Symfony\Component\Form\DataTransformer\DataTransformerInterface;
use Symfony\Component\Form\Util\PropertyPath;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\DataTransformer\TransformationFailedException;
use Symfony\Tests\Component\Form\Fixtures\Author;
use Symfony\Tests\Component\Form\Fixtures\FixedDataTransformer;
use Symfony\Tests\Component\Form\Fixtures\FixedFilterListener;

class FieldTypeTest extends TestCase
{
    protected $form;

    protected function setUp()
    {
        parent::setUp();

        $this->form = $this->factory->create('field', 'title');
    }

    public function testGetPropertyPath_defaultPath()
    {
        $form = $this->factory->create('field', 'title');

        $this->assertEquals(new PropertyPath('title'), $form->getAttribute('property_path'));
    }

    public function testGetPropertyPath_pathIsZero()
    {
        $form = $this->factory->create('field', 'title', array('property_path' => '0'));

        $this->assertEquals(new PropertyPath('0'), $form->getAttribute('property_path'));
    }

    public function testGetPropertyPath_pathIsEmpty()
    {
        $form = $this->factory->create('field', 'title', array('property_path' => ''));

        $this->assertEquals(null, $form->getAttribute('property_path'));
    }

    public function testGetPropertyPath_pathIsFalse()
    {
        $form = $this->factory->create('field', 'title', array('property_path' => false));

        $this->assertEquals(null, $form->getAttribute('property_path'));
    }

    public function testGetPropertyPath_pathIsNull()
    {
        $form = $this->factory->create('field', 'title', array('property_path' => null));

        $this->assertEquals(new PropertyPath('title'), $form->getAttribute('property_path'));
    }

    public function testPassRequiredAsOption()
    {
        $form = $this->factory->create('field', 'title', array('required' => false));

        $this->assertFalse($form->isRequired());

        $form = $this->factory->create('field', 'title', array('required' => true));

        $this->assertTrue($form->isRequired());
    }

    public function testPassReadOnlyAsOption()
    {
        $form = $this->factory->create('field', 'title', array('read_only' => false));

        $this->assertFalse($form->isReadOnly());

        $form = $this->factory->create('field', 'title', array('read_only' => true));

        $this->assertTrue($form->isReadOnly());
    }

    public function testFieldIsReadOnlyIfParentIsReadOnly()
    {
        $form = $this->factory->create('field', 'title', array('read_only' => false));
        $form->setParent($this->factory->create('field', 'title', array('read_only' => true)));

        $this->assertTrue($form->isReadOnly());
    }

    public function testFieldWithNoErrorsIsValid()
    {
        $this->form->bind('data');

        $this->assertTrue($this->form->isValid());
    }

    public function testFieldWithErrorsIsInvalid()
    {
        $this->form->bind('data');
        $this->form->addError(new FormError('Some error'));

        $this->assertFalse($this->form->isValid());
    }

    public function testSubmitResetsErrors()
    {
        $this->form->addError(new FormError('Some error'));
        $this->form->bind('data');

        $this->assertTrue($this->form->isValid());
    }

    public function testUnboundFieldIsInvalid()
    {
        $this->assertFalse($this->form->isValid());
    }

    public function testIsRequiredReturnsOwnValueIfNoParent()
    {
        $form = $this->factory->create('field', 'test', array(
            'required' => true,
        ));

        $this->assertTrue($form->isRequired());

        $form = $this->factory->create('field', 'test', array(
            'required' => false,
        ));

        $this->assertFalse($form->isRequired());
    }

    public function testIsRequiredReturnsOwnValueIfParentIsRequired()
    {
        $group = $this->createMockGroup();
        $group->expects($this->any())
                    ->method('isRequired')
                    ->will($this->returnValue(true));

        $form = $this->factory->create('field', 'test', array(
            'required' => true,
        ));
        $form->setParent($group);

        $this->assertTrue($form->isRequired());

        $form = $this->factory->create('field', 'test', array(
            'required' => false,
        ));
        $form->setParent($group);

        $this->assertFalse($form->isRequired());
    }

    public function testIsRequiredReturnsFalseIfParentIsNotRequired()
    {
        $group = $this->createMockGroup();
        $group->expects($this->any())
                    ->method('isRequired')
                    ->will($this->returnValue(false));

        $form = $this->factory->create('field', 'test', array(
            'required' => true,
        ));
        $form->setParent($group);

        $this->assertFalse($form->isRequired());
    }

    public function testIsBound()
    {
        $this->assertFalse($this->form->isBound());
        $this->form->bind('symfony');
        $this->assertTrue($this->form->isBound());
    }

    public function testDefaultDataIsTransformedCorrectly()
    {
        $form = $this->factory->create('field', 'name');

        $this->assertEquals(null, $this->form->getData());
        $this->assertEquals('', $this->form->getClientData());
    }

    public function testDataIsTransformedCorrectlyIfNull_noDataTransformer()
    {
        $this->form->setData(null);

        $this->assertSame(null, $this->form->getData());
        $this->assertSame('', $this->form->getClientData());
    }

    public function testDataIsTransformedCorrectlyIfNotNull_noDataTransformer()
    {
        $this->form->setData(123);

        // The values are synchronized
        // Without value transformer, the field can't know that the data
        // should be casted to an integer when the field is bound
        // Even without binding, the data will thus be a string
        $this->assertSame('123', $this->form->getData());
        $this->assertSame('123', $this->form->getClientData());
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

        $builder = $this->factory->createBuilder('field', 'title');
        $builder->addEventSubscriber($filter);
        $builder->setClientTransformer($clientTransformer);
        $builder->setNormTransformer($normTransformer);

        $form = $builder->getForm();
        $form->bind(0);

        $this->assertEquals('app[filter2[norm[filter1[0]]]]', $form->getData());
        $this->assertEquals('filter2[norm[filter1[0]]]', $form->getNormData());
        $this->assertEquals('client[filter2[norm[filter1[0]]]]', $form->getClientData());
    }

    public function testBoundDataIsTransformedCorrectlyIfEmpty_noDataTransformer()
    {
        $this->form->bind('');

        $this->assertSame(null, $this->form->getData());
        $this->assertEquals('', $this->form->getClientData());
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
        $form = $builder->getForm();

        $form->setData(0);

        $this->assertEquals(0, $form->getData());
        $this->assertEquals('norm[0]', $form->getNormData());
        $this->assertEquals('transform[norm[0]]', $form->getClientData());
    }

    public function testBoundDataIsTrimmedBeforeTransforming()
    {
        $clientTransformer = new FixedDataTransformer(array(
            null => '',
            'reverse[a]' => 'a',
        ));

        $builder = $this->factory->createBuilder('field', 'title');
        $builder->setClientTransformer($clientTransformer);
        $form = $builder->getForm();

        $form->bind(' a ');

        $this->assertEquals('a', $form->getClientData());
        $this->assertEquals('reverse[a]', $form->getData());
    }

    public function testBoundDataIsNotTrimmedBeforeTransformingIfReadOnly()
    {
        $clientTransformer = new FixedDataTransformer(array(
            null => '',
            'reverse[ a ]' => ' a ',
        ));

        $builder = $this->factory->createBuilder('field', 'title', array(
            'trim' => false,
        ));
        $builder->setClientTransformer($clientTransformer);
        $form = $builder->getForm();

        $form->bind(' a ');

        $this->assertEquals(' a ', $form->getClientData());
        $this->assertEquals('reverse[ a ]', $form->getData());
    }

    public function testIsTransformationSuccessfulReturnsTrueIfReverseTransformSucceeded()
    {
        $form = $this->factory->create('field', 'title', array(
            'trim' => false,
        ));

        $form->bind('a');

        $this->assertEquals('a', $form->getClientData());
        $this->assertTrue($form->isSynchronized());
    }

    public function testIsTransformationSuccessfulReturnsFalseIfReverseTransformThrowsException()
    {
        // The value is passed to the value transformer
        $clientTransformer = $this->createMockTransformer();

        $builder = $this->factory->createBuilder('field', 'title', array(
            'trim' => false,
        ));
        $builder->setClientTransformer($clientTransformer);
        $form = $builder->getForm();

        $clientTransformer->expects($this->once())
                ->method('reverseTransform')
                ->will($this->throwException(new TransformationFailedException()));

        $form->bind('a');

        $this->assertEquals('a', $form->getClientData());
        $this->assertFalse($form->isSynchronized());
    }

    public function testGetRootReturnsRootOfParentIfSet()
    {
        $parent = $this->createMockGroup();
        $parent->expects($this->any())
                ->method('getRoot')
                ->will($this->returnValue('ROOT'));

        $this->form->setParent($parent);

        $this->assertEquals('ROOT', $this->form->getRoot());
    }

    public function testGetRootReturnsFieldIfNoParent()
    {
        $this->assertEquals($this->form, $this->form->getRoot());
    }

    public function testIsEmptyReturnsTrueIfNull()
    {
        $this->form->setData(null);

        $this->assertTrue($this->form->isEmpty());
    }

    public function testIsEmptyReturnsTrueIfEmptyString()
    {
        $this->form->setData('');

        $this->assertTrue($this->form->isEmpty());
    }

    public function testIsEmptyReturnsFalseIfZero()
    {
        $this->form->setData(0);

        $this->assertFalse($this->form->isEmpty());
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
