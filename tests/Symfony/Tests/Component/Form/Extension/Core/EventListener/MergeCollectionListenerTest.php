<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Core\EventListener;

use Symfony\Component\Form\Event\DataEvent;
use Symfony\Component\Form\Event\FilterDataEvent;
use Symfony\Component\Form\Extension\Core\EventListener\MergeCollectionListener;
use Symfony\Component\Form\FormBuilder;

class MergeCollectionListenerTest_Car
{
    // In the test, use a name that FormUtil can't uniquely singularify
    public function addAxis($axis) {}

    public function removeAxis($axis) {}
}

class MergeCollectionListenerTest_CarCustomNames
{
    public function foo($axis) {}

    public function bar($axis) {}
}

class MergeCollectionListenerTest_CarOnlyAdder
{
    public function addAxis($axis) {}
}

class MergeCollectionListenerTest_CarOnlyRemover
{
    public function removeAxis($axis) {}
}

abstract class MergeCollectionListenerTest extends \PHPUnit_Framework_TestCase
{
    private $dispatcher;
    private $factory;
    private $form;

    public function setUp()
    {
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

    protected function getForm($name = 'name')
    {
        return $this->getBuilder($name)->getForm();
    }

    protected function getMockForm()
    {
        return $this->getMock('Symfony\Tests\Component\Form\FormInterface');
    }

    abstract protected function getData(array $data);

    public function testAddExtraEntriesIfAllowAdd()
    {
        $originalData = $this->getData(array(1 => 'second'));
        $newData = $this->getData(array(0 => 'first', 1 => 'second', 2 => 'third'));

        $this->form->setData($originalData);

        $event = new FilterDataEvent($this->form, $newData);
        $listener = new MergeCollectionListener(true, false);
        $listener->onBindNormData($event);

        // The original object was modified
        if (is_object($originalData)) {
            $this->assertSame($originalData, $event->getData());
        }

        // The original object matches the new object
        $this->assertEquals($newData, $event->getData());
    }

    public function testAddExtraEntriesIfAllowAddDontOverwriteExistingIndices()
    {
        $originalData = $this->getData(array(1 => 'first'));
        $newData = $this->getData(array(0 => 'first', 1 => 'second'));

        $this->form->setData($originalData);

        $event = new FilterDataEvent($this->form, $newData);
        $listener = new MergeCollectionListener(true, false);
        $listener->onBindNormData($event);

        // The original object was modified
        if (is_object($originalData)) {
            $this->assertSame($originalData, $event->getData());
        }

        // The original object matches the new object
        $this->assertEquals($this->getData(array(1 => 'first', 2 => 'second')), $event->getData());
    }

    public function testDoNothingIfNotAllowAdd()
    {
        $originalDataArray = array(1 => 'second');
        $originalData = $this->getData($originalDataArray);
        $newData = $this->getData(array(0 => 'first', 1 => 'second', 2 => 'third'));

        $this->form->setData($originalData);

        $event = new FilterDataEvent($this->form, $newData);
        $listener = new MergeCollectionListener(false, false);
        $listener->onBindNormData($event);

        // We still have the original object
        if (is_object($originalData)) {
            $this->assertSame($originalData, $event->getData());
        }

        // Nothing was removed
        $this->assertEquals($this->getData($originalDataArray), $event->getData());
    }

    public function testRemoveMissingEntriesIfAllowDelete()
    {
        $originalData = $this->getData(array(0 => 'first', 1 => 'second', 2 => 'third'));
        $newData = $this->getData(array(1 => 'second'));

        $this->form->setData($originalData);

        $event = new FilterDataEvent($this->form, $newData);
        $listener = new MergeCollectionListener(false, true);
        $listener->onBindNormData($event);

        // The original object was modified
        if (is_object($originalData)) {
            $this->assertSame($originalData, $event->getData());
        }

        // The original object matches the new object
        $this->assertEquals($newData, $event->getData());
    }

    public function testDoNothingIfNotAllowDelete()
    {
        $originalDataArray = array(0 => 'first', 1 => 'second', 2 => 'third');
        $originalData = $this->getData($originalDataArray);
        $newData = $this->getData(array(1 => 'second'));

        $this->form->setData($originalData);

        $event = new FilterDataEvent($this->form, $newData);
        $listener = new MergeCollectionListener(false, false);
        $listener->onBindNormData($event);

        // We still have the original object
        if (is_object($originalData)) {
            $this->assertSame($originalData, $event->getData());
        }

        // Nothing was removed
        $this->assertEquals($this->getData($originalDataArray), $event->getData());
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testRequireArrayOrTraversable()
    {
        $newData = 'no array or traversable';
        $event = new FilterDataEvent($this->form, $newData);
        $listener = new MergeCollectionListener(true, false);
        $listener->onBindNormData($event);
    }

    public function testDealWithNullData()
    {
        $originalData = $this->getData(array(0 => 'first', 1 => 'second', 2 => 'third'));
        $newData = null;

        $this->form->setData($originalData);

        $event = new FilterDataEvent($this->form, $newData);
        $listener = new MergeCollectionListener(false, false);
        $listener->onBindNormData($event);

        $this->assertSame($originalData, $event->getData());
    }

    public function testDealWithNullOriginalDataIfAllowAdd()
    {
        $originalData = null;
        $newData = $this->getData(array(0 => 'first', 1 => 'second', 2 => 'third'));

        $this->form->setData($originalData);

        $event = new FilterDataEvent($this->form, $newData);
        $listener = new MergeCollectionListener(true, false);
        $listener->onBindNormData($event);

        $this->assertSame($newData, $event->getData());
    }

    public function testDontDealWithNullOriginalDataIfNotAllowAdd()
    {
        $originalData = null;
        $newData = $this->getData(array(0 => 'first', 1 => 'second', 2 => 'third'));

        $this->form->setData($originalData);

        $event = new FilterDataEvent($this->form, $newData);
        $listener = new MergeCollectionListener(false, false);
        $listener->onBindNormData($event);

        $this->assertNull($event->getData());
    }

    public function testCallAdderIfAllowAdd()
    {
        $parentData = $this->getMock(__CLASS__ . '_CarOnlyAdder');
        $parentForm = $this->getForm('car');
        $parentForm->setData($parentData);
        $parentForm->add($this->form);

        $originalDataArray = array(1 => 'second');
        $originalData = $this->getData($originalDataArray);
        $newData = $this->getData(array(0 => 'first', 1 => 'second', 2 => 'third'));

        $this->form->setData($originalData);

        $parentData->expects($this->at(0))
            ->method('addAxis')
            ->with('first');
        $parentData->expects($this->at(1))
            ->method('addAxis')
            ->with('third');

        $event = new FilterDataEvent($this->form, $newData);
        $listener = new MergeCollectionListener(true, false);
        $listener->onBindNormData($event);

        if (is_object($originalData)) {
            $this->assertSame($originalData, $event->getData());
        }

        // The data was not modified directly
        // Thus it should not be written back into the parent data!
        $this->assertEquals($this->getData($originalDataArray), $event->getData());
    }

    public function testDontCallAdderIfNotAllowAdd()
    {
        $parentData = $this->getMock(__CLASS__ . '_Car');
        $parentForm = $this->getForm('car');
        $parentForm->setData($parentData);
        $parentForm->add($this->form);

        $originalDataArray = array(1 => 'second');
        $originalData = $this->getData($originalDataArray);
        $newData = $this->getData(array(0 => 'first', 1 => 'second', 2 => 'third'));

        $this->form->setData($originalData);

        $parentData->expects($this->never())
            ->method('addAxis');

        $event = new FilterDataEvent($this->form, $newData);
        $listener = new MergeCollectionListener(false, false);
        $listener->onBindNormData($event);

        if (is_object($originalData)) {
            $this->assertSame($originalData, $event->getData());
        }

        // The data was not modified
        $this->assertEquals($this->getData($originalDataArray), $event->getData());
    }

    public function testDontCallAdderIfNotUseAccessors()
    {
        $parentData = $this->getMock(__CLASS__ . '_Car');
        $parentForm = $this->getForm('car');
        $parentForm->setData($parentData);
        $parentForm->add($this->form);

        $originalData = $this->getData(array(1 => 'second'));
        $newData = $this->getData(array(0 => 'first', 1 => 'second', 2 => 'third'));

        $this->form->setData($originalData);

        $parentData->expects($this->never())
            ->method('addAxis');

        $event = new FilterDataEvent($this->form, $newData);
        $listener = new MergeCollectionListener(true, false, false);
        $listener->onBindNormData($event);

        if (is_object($originalData)) {
            $this->assertSame($originalData, $event->getData());
        }

        // The data was modified without accessors
        $this->assertEquals($newData, $event->getData());
    }

    public function testCallRemoverIfAllowDelete()
    {
        $parentData = $this->getMock(__CLASS__ . '_CarOnlyRemover');
        $parentForm = $this->getForm('car');
        $parentForm->setData($parentData);
        $parentForm->add($this->form);

        $originalDataArray = array(0 => 'first', 1 => 'second', 2 => 'third');
        $originalData = $this->getData($originalDataArray);
        $newData = $this->getData(array(1 => 'second'));

        $this->form->setData($originalData);

        $parentData->expects($this->at(0))
            ->method('removeAxis')
            ->with('first');
        $parentData->expects($this->at(1))
            ->method('removeAxis')
            ->with('third');

        $event = new FilterDataEvent($this->form, $newData);
        $listener = new MergeCollectionListener(false, true);
        $listener->onBindNormData($event);

        if (is_object($originalData)) {
            $this->assertSame($originalData, $event->getData());
        }

        // The data was not modified directly
        // Thus it should not be written back into the parent data!
        $this->assertEquals($this->getData($originalDataArray), $event->getData());
    }

    public function testDontCallRemoverIfNotAllowDelete()
    {
        $parentData = $this->getMock(__CLASS__ . '_Car');
        $parentForm = $this->getForm('car');
        $parentForm->setData($parentData);
        $parentForm->add($this->form);

        $originalDataArray = array(0 => 'first', 1 => 'second', 2 => 'third');
        $originalData = $this->getData($originalDataArray);
        $newData = $this->getData(array(1 => 'second'));

        $this->form->setData($originalData);

        $parentData->expects($this->never())
            ->method('removeAxis');

        $event = new FilterDataEvent($this->form, $newData);
        $listener = new MergeCollectionListener(false, false);
        $listener->onBindNormData($event);

        if (is_object($originalData)) {
            $this->assertSame($originalData, $event->getData());
        }

        // The data was not modified
        $this->assertEquals($this->getData($originalDataArray), $event->getData());
    }

    public function testDontCallRemoverIfNotUseAccessors()
    {
        $parentData = $this->getMock(__CLASS__ . '_Car');
        $parentForm = $this->getForm('car');
        $parentForm->setData($parentData);
        $parentForm->add($this->form);

        $originalData = $this->getData(array(0 => 'first', 1 => 'second', 2 => 'third'));
        $newData = $this->getData(array(1 => 'second'));

        $this->form->setData($originalData);

        $parentData->expects($this->never())
            ->method('removeAxis');

        $event = new FilterDataEvent($this->form, $newData);
        $listener = new MergeCollectionListener(false, true, false);
        $listener->onBindNormData($event);

        if (is_object($originalData)) {
            $this->assertSame($originalData, $event->getData());
        }

        // The data was modified directly
        $this->assertEquals($newData, $event->getData());
    }

    public function testCallAdderAndDeleterIfAllowAll()
    {
        $parentData = $this->getMock(__CLASS__ . '_Car');
        $parentForm = $this->getForm('car');
        $parentForm->setData($parentData);
        $parentForm->add($this->form);

        $originalDataArray = array(1 => 'second');
        $originalData = $this->getData($originalDataArray);
        $newData = $this->getData(array(0 => 'first'));

        $this->form->setData($originalData);

        $parentData->expects($this->once())
            ->method('addAxis')
            ->with('first');
        $parentData->expects($this->once())
            ->method('removeAxis')
            ->with('second');

        $event = new FilterDataEvent($this->form, $newData);
        $listener = new MergeCollectionListener(true, true, true);
        $listener->onBindNormData($event);

        if (is_object($originalData)) {
            $this->assertSame($originalData, $event->getData());
        }

        // The data was not modified directly
        // Thus it should not be written back into the parent data!
        $this->assertEquals($this->getData($originalDataArray), $event->getData());
    }

    public function testCallAccessorsWithCustomNames()
    {
        $parentData = $this->getMock(__CLASS__ . '_CarCustomNames');
        $parentForm = $this->getForm('car');
        $parentForm->setData($parentData);
        $parentForm->add($this->form);

        $originalDataArray = array(1 => 'second');
        $originalData = $this->getData($originalDataArray);
        $newData = $this->getData(array(0 => 'first'));

        $this->form->setData($originalData);

        $parentData->expects($this->once())
            ->method('foo')
            ->with('first');
        $parentData->expects($this->once())
            ->method('bar')
            ->with('second');

        $event = new FilterDataEvent($this->form, $newData);
        $listener = new MergeCollectionListener(true, true, true, 'foo', 'bar');
        $listener->onBindNormData($event);

        if (is_object($originalData)) {
            $this->assertSame($originalData, $event->getData());
        }

        // The data was not modified directly
        // Thus it should not be written back into the parent data!
        $this->assertEquals($this->getData($originalDataArray), $event->getData());
    }

    public function testDontCallAdderWithCustomNameIfDisallowed()
    {
        $parentData = $this->getMock(__CLASS__ . '_CarCustomNames');
        $parentForm = $this->getForm('car');
        $parentForm->setData($parentData);
        $parentForm->add($this->form);

        $originalDataArray = array(1 => 'second');
        $originalData = $this->getData($originalDataArray);
        $newData = $this->getData(array(0 => 'first'));

        $this->form->setData($originalData);

        $parentData->expects($this->never())
            ->method('foo');
        $parentData->expects($this->once())
            ->method('bar')
            ->with('second');

        $event = new FilterDataEvent($this->form, $newData);
        $listener = new MergeCollectionListener(false, true, true, 'foo', 'bar');
        $listener->onBindNormData($event);

        if (is_object($originalData)) {
            $this->assertSame($originalData, $event->getData());
        }

        // The data was not modified
        $this->assertEquals($this->getData($originalDataArray), $event->getData());
    }

    public function testDontCallRemoverWithCustomNameIfDisallowed()
    {
        $parentData = $this->getMock(__CLASS__ . '_CarCustomNames');
        $parentForm = $this->getForm('car');
        $parentForm->setData($parentData);
        $parentForm->add($this->form);

        $originalDataArray = array(1 => 'second');
        $originalData = $this->getData($originalDataArray);
        $newData = $this->getData(array(0 => 'first'));

        $this->form->setData($originalData);

        $parentData->expects($this->once())
            ->method('foo')
            ->with('first');
        $parentData->expects($this->never())
            ->method('bar');

        $event = new FilterDataEvent($this->form, $newData);
        $listener = new MergeCollectionListener(true, false, true, 'foo', 'bar');
        $listener->onBindNormData($event);

        if (is_object($originalData)) {
            $this->assertSame($originalData, $event->getData());
        }

        // The data was not modified
        $this->assertEquals($this->getData($originalDataArray), $event->getData());
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\FormException
     */
    public function testThrowExceptionIfInvalidAdder()
    {
        $parentData = $this->getMock(__CLASS__ . '_CarCustomNames');
        $parentForm = $this->getForm('car');
        $parentForm->setData($parentData);
        $parentForm->add($this->form);

        $originalData = $this->getData(array(1 => 'second'));
        $newData = $this->getData(array(0 => 'first'));

        $this->form->setData($originalData);

        $event = new FilterDataEvent($this->form, $newData);
        $listener = new MergeCollectionListener(true, false, true, 'doesnotexist');
        $listener->onBindNormData($event);
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\FormException
     */
    public function testThrowExceptionIfInvalidRemover()
    {
        $parentData = $this->getMock(__CLASS__ . '_CarCustomNames');
        $parentForm = $this->getForm('car');
        $parentForm->setData($parentData);
        $parentForm->add($this->form);

        $originalData = $this->getData(array(1 => 'second'));
        $newData = $this->getData(array(0 => 'first'));

        $this->form->setData($originalData);

        $event = new FilterDataEvent($this->form, $newData);
        $listener = new MergeCollectionListener(false, true, true, null, 'doesnotexist');
        $listener->onBindNormData($event);
    }
}
