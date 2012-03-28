<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\EventListener;

use Symfony\Component\Form\Event\DataEvent;
use Symfony\Component\Form\Event\FilterDataEvent;
use Symfony\Component\Form\Extension\Core\EventListener\MergeCollectionListener;
use Symfony\Component\Form\FormBuilder;

class MergeCollectionListenerTest_Car
{
    // In the test, use a name that FormUtil can't uniquely singularify
    public function addAxis($axis) {}

    public function removeAxis($axis) {}

    public function getAxes() {}
}

class MergeCollectionListenerTest_CarCustomNames
{
    public function foo($axis) {}

    public function bar($axis) {}

    public function getAxes() {}
}

class MergeCollectionListenerTest_CarOnlyAdder
{
    public function addAxis($axis) {}

    public function getAxes() {}
}

class MergeCollectionListenerTest_CarOnlyRemover
{
    public function removeAxis($axis) {}

    public function getAxes() {}
}

class MergeCollectionListenerTest_CompositeCar
{
    public function getStructure() {}
}

class MergeCollectionListenerTest_CarStructure
{
    public function addAxis($axis) {}

    public function removeAxis($axis) {}

    public function getAxes() {}
}

abstract class MergeCollectionListenerTest extends \PHPUnit_Framework_TestCase
{
    private $dispatcher;
    private $factory;
    private $form;

    protected function setUp()
    {
        if (!class_exists('Symfony\Component\EventDispatcher\EventDispatcher')) {
            $this->markTestSkipped('The "EventDispatcher" component is not available');
        }

        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $this->factory = $this->getMock('Symfony\Component\Form\FormFactoryInterface');
        $this->form = $this->getForm('axes');
    }

    protected function tearDown()
    {
        $this->dispatcher = null;
        $this->factory = null;
        $this->form = null;
    }

    protected function getBuilder($name = 'name')
    {
        return new FormBuilder($name, $this->factory, $this->dispatcher);
    }

    protected function getForm($name = 'name', $propertyPath = null)
    {
        $propertyPath = $propertyPath ?: $name;

        return $this->getBuilder($name)->setAttribute('property_path', $propertyPath)->getForm();
    }

    protected function getMockForm()
    {
        return $this->getMock('Symfony\Component\Form\Tests\FormInterface');
    }

    public function getModesWithNormal()
    {
        if (!class_exists('Symfony\Component\EventDispatcher\EventDispatcher')) {
            return array(null);
        }

        return array(
            array(MergeCollectionListener::MERGE_NORMAL),
            array(MergeCollectionListener::MERGE_NORMAL | MergeCollectionListener::MERGE_INTO_PARENT),
        );
    }

    public function getModesWithMergeIntoParent()
    {
        if (!class_exists('Symfony\Component\EventDispatcher\EventDispatcher')) {
            return array(null);
        }

        return array(
            array(MergeCollectionListener::MERGE_INTO_PARENT),
            array(MergeCollectionListener::MERGE_INTO_PARENT | MergeCollectionListener::MERGE_NORMAL),
        );
    }

    public function getModesWithoutMergeIntoParent()
    {
        if (!class_exists('Symfony\Component\EventDispatcher\EventDispatcher')) {
            return array(null);
        }

        return array(
            array(MergeCollectionListener::MERGE_NORMAL),
        );
    }

    public function getInvalidModes()
    {
        return array(
            // 0 is a valid mode, because it is treated as "default" (=3)
            array(4),
            array(8),
        );
    }

    abstract protected function getData(array $data);

    /**
     * @dataProvider getModesWithNormal
     */
    public function testAddExtraEntriesIfAllowAdd($mode)
    {
        $originalData = $this->getData(array(1 => 'second'));
        $newData = $this->getData(array(0 => 'first', 1 => 'second', 2 => 'third'));

        $listener = new MergeCollectionListener(true, false, $mode);

        $this->form->setData($originalData);

        $event = new DataEvent($this->form, $newData);
        $listener->preBind($event);
        $event = new FilterDataEvent($this->form, $newData);
        $listener->onBindNormData($event);

        // The original object was modified
        if (is_object($originalData)) {
            $this->assertSame($originalData, $event->getData());
        }

        // The original object matches the new object
        $this->assertEquals($newData, $event->getData());
    }

    /**
     * @dataProvider getModesWithNormal
     */
    public function testAddExtraEntriesIfAllowAddDontOverwriteExistingIndices($mode)
    {
        $originalData = $this->getData(array(1 => 'first'));
        $newData = $this->getData(array(0 => 'first', 1 => 'second'));

        $listener = new MergeCollectionListener(true, false, $mode);

        $this->form->setData($originalData);

        $event = new DataEvent($this->form, $newData);
        $listener->preBind($event);
        $event = new FilterDataEvent($this->form, $newData);
        $listener->onBindNormData($event);

        // The original object was modified
        if (is_object($originalData)) {
            $this->assertSame($originalData, $event->getData());
        }

        // The original object matches the new object
        $this->assertEquals($this->getData(array(1 => 'first', 2 => 'second')), $event->getData());
    }

    /**
     * @dataProvider getModesWithNormal
     */
    public function testDoNothingIfNotAllowAdd($mode)
    {
        $originalDataArray = array(1 => 'second');
        $originalData = $this->getData($originalDataArray);
        $newData = $this->getData(array(0 => 'first', 1 => 'second', 2 => 'third'));

        $listener = new MergeCollectionListener(false, false, $mode);

        $this->form->setData($originalData);

        $event = new DataEvent($this->form, $newData);
        $listener->preBind($event);
        $event = new FilterDataEvent($this->form, $newData);
        $listener->onBindNormData($event);

        // We still have the original object
        if (is_object($originalData)) {
            $this->assertSame($originalData, $event->getData());
        }

        // Nothing was removed
        $this->assertEquals($this->getData($originalDataArray), $event->getData());
    }

    /**
     * @dataProvider getModesWithNormal
     */
    public function testRemoveMissingEntriesIfAllowDelete($mode)
    {
        $originalData = $this->getData(array(0 => 'first', 1 => 'second', 2 => 'third'));
        $newData = $this->getData(array(1 => 'second'));

        $listener = new MergeCollectionListener(false, true, $mode);

        $this->form->setData($originalData);

        $event = new DataEvent($this->form, $newData);
        $listener->preBind($event);
        $event = new FilterDataEvent($this->form, $newData);
        $listener->onBindNormData($event);

        // The original object was modified
        if (is_object($originalData)) {
            $this->assertSame($originalData, $event->getData());
        }

        // The original object matches the new object
        $this->assertEquals($newData, $event->getData());
    }

    /**
     * @dataProvider getModesWithNormal
     */
    public function testDoNothingIfNotAllowDelete($mode)
    {
        $originalDataArray = array(0 => 'first', 1 => 'second', 2 => 'third');
        $originalData = $this->getData($originalDataArray);
        $newData = $this->getData(array(1 => 'second'));

        $listener = new MergeCollectionListener(false, false, $mode);

        $this->form->setData($originalData);

        $event = new DataEvent($this->form, $newData);
        $listener->preBind($event);
        $event = new FilterDataEvent($this->form, $newData);
        $listener->onBindNormData($event);

        // We still have the original object
        if (is_object($originalData)) {
            $this->assertSame($originalData, $event->getData());
        }

        // Nothing was removed
        $this->assertEquals($this->getData($originalDataArray), $event->getData());
    }

    /**
     * @dataProvider getModesWithNormal
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testRequireArrayOrTraversable($mode)
    {
        $newData = 'no array or traversable';
        $event = new FilterDataEvent($this->form, $newData);
        $listener = new MergeCollectionListener(true, false, $mode);
        $listener->onBindNormData($event);
    }

    /**
     * @dataProvider getModesWithNormal
     */
    public function testDealWithNullData($mode)
    {
        $originalData = $this->getData(array(0 => 'first', 1 => 'second', 2 => 'third'));
        $newData = null;

        $listener = new MergeCollectionListener(false, false, $mode);

        $this->form->setData($originalData);

        $event = new DataEvent($this->form, $newData);
        $listener->preBind($event);
        $event = new FilterDataEvent($this->form, $newData);
        $listener->onBindNormData($event);

        $this->assertSame($originalData, $event->getData());
    }

    /**
     * @dataProvider getModesWithNormal
     */
    public function testDealWithNullOriginalDataIfAllowAdd($mode)
    {
        $originalData = null;
        $newData = $this->getData(array(0 => 'first', 1 => 'second', 2 => 'third'));

        $listener = new MergeCollectionListener(true, false, $mode);

        $this->form->setData($originalData);

        $event = new DataEvent($this->form, $newData);
        $listener->preBind($event);
        $event = new FilterDataEvent($this->form, $newData);
        $listener->onBindNormData($event);

        $this->assertSame($newData, $event->getData());
    }

    /**
     * @dataProvider getModesWithNormal
     */
    public function testDontDealWithNullOriginalDataIfNotAllowAdd($mode)
    {
        $originalData = null;
        $newData = $this->getData(array(0 => 'first', 1 => 'second', 2 => 'third'));

        $listener = new MergeCollectionListener(false, false, $mode);

        $this->form->setData($originalData);

        $event = new DataEvent($this->form, $newData);
        $listener->preBind($event);
        $event = new FilterDataEvent($this->form, $newData);
        $listener->onBindNormData($event);

        $this->assertNull($event->getData());
    }

    /**
     * @dataProvider getModesWithMergeIntoParent
     */
    public function testCallAdderIfAllowAdd($mode)
    {
        $parentData = $this->getMock(__CLASS__ . '_CarOnlyAdder');
        $parentForm = $this->getForm('car');
        $parentForm->setData($parentData);
        $parentForm->add($this->form);

        $originalDataArray = array(1 => 'second');
        $originalData = $this->getData($originalDataArray);
        $newData = $this->getData(array(0 => 'first', 1 => 'second', 2 => 'third'));

        $listener = new MergeCollectionListener(true, false, $mode);

        $this->form->setData($originalData);

        $event = new DataEvent($this->form, $newData);
        $listener->preBind($event);

        $parentData->expects($this->at(0))
            ->method('addAxis')
            ->with('first');
        $parentData->expects($this->at(1))
            ->method('addAxis')
            ->with('third');
        $parentData->expects($this->at(2))
            ->method('getAxes')
            ->will($this->returnValue('RESULT'));

        $event = new FilterDataEvent($this->form, $newData);
        $listener->onBindNormData($event);

        $this->assertEquals('RESULT', $event->getData());
    }

    /**
     * @dataProvider getModesWithMergeIntoParent
     */
    public function testCallAdderIfCustomPropertyPath($mode)
    {
        $this->form = $this->getForm('structure_axes', 'structure.axes');

        $parentData = $this->getMock(__CLASS__ . '_CompositeCar');
        $parentForm = $this->getForm('car');
        $parentForm->setData($parentData);
        $parentForm->add($this->form);

        $modifData = $this->getMock(__CLASS__ . '_CarStructure');

        $originalDataArray = array(1 => 'second');
        $originalData = $this->getData($originalDataArray);
        $newData = $this->getData(array(0 => 'first', 1 => 'second', 2 => 'third'));

        $listener = new MergeCollectionListener(true, false, $mode);

        $this->form->setData($originalData);

        $event = new DataEvent($this->form, $newData);
        $listener->preBind($event);

        $parentData->expects($this->once())
            ->method('getStructure')
            ->will($this->returnValue($modifData));

        $modifData->expects($this->at(0))
            ->method('addAxis')
            ->with('first');
        $modifData->expects($this->at(1))
            ->method('addAxis')
            ->with('third');
        $modifData->expects($this->at(2))
            ->method('getAxes')
            ->will($this->returnValue('RESULT'));

        $event = new FilterDataEvent($this->form, $newData);
        $listener->onBindNormData($event);

        $this->assertEquals('RESULT', $event->getData());
    }

    /**
     * @dataProvider getModesWithMergeIntoParent
     */
    public function testCallAdderIfOriginalDataAlreadyModified($mode)
    {
        $parentData = $this->getMock(__CLASS__ . '_CarOnlyAdder');
        $parentForm = $this->getForm('car');
        $parentForm->setData($parentData);
        $parentForm->add($this->form);

        $originalDataArray = array(1 => 'second');
        $originalData = $this->getData($originalDataArray);
        $newData = $this->getData(array(0 => 'first', 1 => 'second', 2 => 'third'));

        $listener = new MergeCollectionListener(true, false, $mode);

        $this->form->setData($originalData);

        $event = new DataEvent($this->form, $newData);
        $listener->preBind($event);

        // The form already contains the new data
        // This happens if the data mapper maps the data of the child forms
        // back into the original collection.
        // The original collection is then both already modified and passed
        // as event argument.
        $this->form->setData($newData);

        $parentData->expects($this->at(0))
            ->method('addAxis')
            ->with('first');
        $parentData->expects($this->at(1))
            ->method('addAxis')
            ->with('third');
        $parentData->expects($this->at(2))
            ->method('getAxes')
            ->will($this->returnValue('RESULT'));

        $event = new FilterDataEvent($this->form, $newData);
        $listener->onBindNormData($event);

        $this->assertEquals('RESULT', $event->getData());
    }

    /**
     * @dataProvider getModesWithMergeIntoParent
     */
    public function testDontCallAdderIfNotAllowAdd($mode)
    {
        $parentData = $this->getMock(__CLASS__ . '_Car');
        $parentForm = $this->getForm('car');
        $parentForm->setData($parentData);
        $parentForm->add($this->form);

        $originalDataArray = array(1 => 'second');
        $originalData = $this->getData($originalDataArray);
        $newData = $this->getData(array(0 => 'first', 1 => 'second', 2 => 'third'));

        $listener = new MergeCollectionListener(false, false, $mode);

        $this->form->setData($originalData);

        $event = new DataEvent($this->form, $newData);
        $listener->preBind($event);

        $parentData->expects($this->never())
            ->method('addAxis');

        $event = new FilterDataEvent($this->form, $newData);
        $listener->onBindNormData($event);

        if (is_object($originalData)) {
            $this->assertSame($originalData, $event->getData());
        }

        // The data was not modified
        $this->assertEquals($this->getData($originalDataArray), $event->getData());
    }

    /**
     * @dataProvider getModesWithoutMergeIntoParent
     */
    public function testDontCallAdderIfNotMergeIntoParent($mode)
    {
        $parentData = $this->getMock(__CLASS__ . '_Car');
        $parentForm = $this->getForm('car');
        $parentForm->setData($parentData);
        $parentForm->add($this->form);

        $originalData = $this->getData(array(1 => 'second'));
        $newData = $this->getData(array(0 => 'first', 1 => 'second', 2 => 'third'));

        $listener = new MergeCollectionListener(true, false, $mode);

        $this->form->setData($originalData);

        $event = new DataEvent($this->form, $newData);
        $listener->preBind($event);

        $parentData->expects($this->never())
            ->method('addAxis');

        $event = new FilterDataEvent($this->form, $newData);
        $listener->onBindNormData($event);

        if (is_object($originalData)) {
            $this->assertSame($originalData, $event->getData());
        }

        // The data was modified without accessors
        $this->assertEquals($newData, $event->getData());
    }

    /**
     * @dataProvider getModesWithMergeIntoParent
     */
    public function testCallRemoverIfAllowDelete($mode)
    {
        $parentData = $this->getMock(__CLASS__ . '_CarOnlyRemover');
        $parentForm = $this->getForm('car');
        $parentForm->setData($parentData);
        $parentForm->add($this->form);

        $originalDataArray = array(0 => 'first', 1 => 'second', 2 => 'third');
        $originalData = $this->getData($originalDataArray);
        $newData = $this->getData(array(1 => 'second'));

        $listener = new MergeCollectionListener(false, true, $mode);

        $this->form->setData($originalData);

        $event = new DataEvent($this->form, $newData);
        $listener->preBind($event);

        $parentData->expects($this->at(0))
            ->method('removeAxis')
            ->with('first');
        $parentData->expects($this->at(1))
            ->method('removeAxis')
            ->with('third');
        $parentData->expects($this->at(2))
            ->method('getAxes')
            ->will($this->returnValue('RESULT'));

        $event = new FilterDataEvent($this->form, $newData);
        $listener->onBindNormData($event);

        $this->assertEquals('RESULT', $event->getData());
    }

    /**
     * @dataProvider getModesWithMergeIntoParent
     */
    public function testDontCallRemoverIfNotAllowDelete($mode)
    {
        $parentData = $this->getMock(__CLASS__ . '_Car');
        $parentForm = $this->getForm('car');
        $parentForm->setData($parentData);
        $parentForm->add($this->form);

        $originalDataArray = array(0 => 'first', 1 => 'second', 2 => 'third');
        $originalData = $this->getData($originalDataArray);
        $newData = $this->getData(array(1 => 'second'));

        $listener = new MergeCollectionListener(false, false, $mode);

        $this->form->setData($originalData);

        $event = new DataEvent($this->form, $newData);
        $listener->preBind($event);

        $parentData->expects($this->never())
            ->method('removeAxis');

        $event = new FilterDataEvent($this->form, $newData);
        $listener->onBindNormData($event);

        if (is_object($originalData)) {
            $this->assertSame($originalData, $event->getData());
        }

        // The data was not modified
        $this->assertEquals($this->getData($originalDataArray), $event->getData());
    }

    /**
     * @dataProvider getModesWithoutMergeIntoParent
     */
    public function testDontCallRemoverIfNotMergeIntoParent($mode)
    {
        $parentData = $this->getMock(__CLASS__ . '_Car');
        $parentForm = $this->getForm('car');
        $parentForm->setData($parentData);
        $parentForm->add($this->form);

        $originalData = $this->getData(array(0 => 'first', 1 => 'second', 2 => 'third'));
        $newData = $this->getData(array(1 => 'second'));

        $listener = new MergeCollectionListener(false, true, $mode);

        $this->form->setData($originalData);

        $event = new DataEvent($this->form, $newData);
        $listener->preBind($event);

        $parentData->expects($this->never())
            ->method('removeAxis');

        $event = new FilterDataEvent($this->form, $newData);
        $listener->onBindNormData($event);

        if (is_object($originalData)) {
            $this->assertSame($originalData, $event->getData());
        }

        // The data was modified directly
        $this->assertEquals($newData, $event->getData());
    }

    /**
     * @dataProvider getModesWithMergeIntoParent
     */
    public function testCallAdderAndDeleterIfAllowAll($mode)
    {
        $parentData = $this->getMock(__CLASS__ . '_Car');
        $parentForm = $this->getForm('car');
        $parentForm->setData($parentData);
        $parentForm->add($this->form);

        $originalDataArray = array(1 => 'second');
        $originalData = $this->getData($originalDataArray);
        $newData = $this->getData(array(0 => 'first'));

        $listener = new MergeCollectionListener(true, true, $mode);

        $this->form->setData($originalData);

        $event = new DataEvent($this->form, $newData);
        $listener->preBind($event);

        $parentData->expects($this->at(0))
            ->method('removeAxis')
            ->with('second');
        $parentData->expects($this->at(1))
            ->method('addAxis')
            ->with('first');
        $parentData->expects($this->at(2))
            ->method('getAxes')
            ->will($this->returnValue('RESULT'));

        $event = new FilterDataEvent($this->form, $newData);
        $listener->onBindNormData($event);

        $this->assertEquals('RESULT', $event->getData());
    }

    /**
     * @dataProvider getModesWithMergeIntoParent
     */
    public function testCallAccessorsWithCustomNames($mode)
    {
        $parentData = $this->getMock(__CLASS__ . '_CarCustomNames');
        $parentForm = $this->getForm('car');
        $parentForm->setData($parentData);
        $parentForm->add($this->form);

        $originalDataArray = array(1 => 'second');
        $originalData = $this->getData($originalDataArray);
        $newData = $this->getData(array(0 => 'first'));

        $listener = new MergeCollectionListener(true, true, $mode, 'foo', 'bar');

        $this->form->setData($originalData);

        $event = new DataEvent($this->form, $newData);
        $listener->preBind($event);

        $parentData->expects($this->at(0))
            ->method('bar')
            ->with('second');
        $parentData->expects($this->at(1))
            ->method('foo')
            ->with('first');
        $parentData->expects($this->at(2))
            ->method('getAxes')
            ->will($this->returnValue('RESULT'));

        $event = new FilterDataEvent($this->form, $newData);
        $listener->onBindNormData($event);

        $this->assertEquals('RESULT', $event->getData());
    }

    /**
     * @dataProvider getModesWithMergeIntoParent
     */
    public function testDontCallAdderWithCustomNameIfDisallowed($mode)
    {
        $parentData = $this->getMock(__CLASS__ . '_CarCustomNames');
        $parentForm = $this->getForm('car');
        $parentForm->setData($parentData);
        $parentForm->add($this->form);

        $originalDataArray = array(1 => 'second');
        $originalData = $this->getData($originalDataArray);
        $newData = $this->getData(array(0 => 'first'));

        $listener = new MergeCollectionListener(false, true, $mode, 'foo', 'bar');

        $this->form->setData($originalData);

        $event = new DataEvent($this->form, $newData);
        $listener->preBind($event);

        $parentData->expects($this->never())
            ->method('foo');
        $parentData->expects($this->at(0))
            ->method('bar')
            ->with('second');
        $parentData->expects($this->at(1))
            ->method('getAxes')
            ->will($this->returnValue('RESULT'));

        $event = new FilterDataEvent($this->form, $newData);
        $listener->onBindNormData($event);

        $this->assertEquals('RESULT', $event->getData());
    }

    /**
     * @dataProvider getModesWithMergeIntoParent
     */
    public function testDontCallRemoverWithCustomNameIfDisallowed($mode)
    {
        $parentData = $this->getMock(__CLASS__ . '_CarCustomNames');
        $parentForm = $this->getForm('car');
        $parentForm->setData($parentData);
        $parentForm->add($this->form);

        $originalDataArray = array(1 => 'second');
        $originalData = $this->getData($originalDataArray);
        $newData = $this->getData(array(0 => 'first'));

        $listener = new MergeCollectionListener(true, false, $mode, 'foo', 'bar');

        $this->form->setData($originalData);

        $event = new DataEvent($this->form, $newData);
        $listener->preBind($event);

        $parentData->expects($this->at(0))
            ->method('foo')
            ->with('first');
        $parentData->expects($this->never())
            ->method('bar');
        $parentData->expects($this->at(1))
            ->method('getAxes')
            ->will($this->returnValue('RESULT'));

        $event = new FilterDataEvent($this->form, $newData);
        $listener->onBindNormData($event);

        $this->assertEquals('RESULT', $event->getData());
    }

    /**
     * @dataProvider getModesWithMergeIntoParent
     * @expectedException Symfony\Component\Form\Exception\FormException
     */
    public function testThrowExceptionIfInvalidAdder($mode)
    {
        $parentData = $this->getMock(__CLASS__ . '_CarCustomNames');
        $parentForm = $this->getForm('car');
        $parentForm->setData($parentData);
        $parentForm->add($this->form);

        $originalData = $this->getData(array(1 => 'second'));
        $newData = $this->getData(array(0 => 'first'));

        $listener = new MergeCollectionListener(true, false, $mode, 'doesnotexist');

        $this->form->setData($originalData);

        $event = new DataEvent($this->form, $newData);
        $listener->preBind($event);
        $event = new FilterDataEvent($this->form, $newData);
        $listener->onBindNormData($event);
    }

    /**
     * @dataProvider getModesWithMergeIntoParent
     * @expectedException Symfony\Component\Form\Exception\FormException
     */
    public function testThrowExceptionIfInvalidRemover($mode)
    {
        $parentData = $this->getMock(__CLASS__ . '_CarCustomNames');
        $parentForm = $this->getForm('car');
        $parentForm->setData($parentData);
        $parentForm->add($this->form);

        $originalData = $this->getData(array(1 => 'second'));
        $newData = $this->getData(array(0 => 'first'));

        $listener = new MergeCollectionListener(false, true, $mode, null, 'doesnotexist');

        $this->form->setData($originalData);

        $event = new DataEvent($this->form, $newData);
        $listener->preBind($event);
        $event = new FilterDataEvent($this->form, $newData);
        $listener->onBindNormData($event);
    }

    /**
     * @dataProvider getInvalidModes
     * @expectedException Symfony\Component\Form\Exception\FormException
     */
    public function testThrowExceptionIfInvalidMode($mode)
    {
        new MergeCollectionListener(true, true, $mode);
    }
}
