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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\EventListener\MergeCollectionListener;
use Symfony\Component\Form\FormEvent;

abstract class MergeCollectionListenerTest extends TestCase
{
    protected $dispatcher;
    protected $factory;
    protected $form;

    protected function setUp()
    {
        $this->dispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock();
        $this->factory = $this->getMockBuilder('Symfony\Component\Form\FormFactoryInterface')->getMock();
        $this->form = $this->getForm('axes');
    }

    protected function tearDown()
    {
        $this->dispatcher = null;
        $this->factory = null;
        $this->form = null;
    }

    abstract protected function getBuilder($name = 'name');

    protected function getForm($name = 'name', $propertyPath = null)
    {
        $propertyPath = $propertyPath ?: $name;

        return $this->getBuilder($name)->setAttribute('property_path', $propertyPath)->getForm();
    }

    public function getBooleanMatrix1()
    {
        return [
            [true],
            [false],
        ];
    }

    public function getBooleanMatrix2()
    {
        return [
            [true, true],
            [true, false],
            [false, true],
            [false, false],
        ];
    }

    abstract protected function getData(array $data);

    /**
     * @dataProvider getBooleanMatrix1
     */
    public function testAddExtraEntriesIfAllowAdd($allowDelete)
    {
        $originalData = $this->getData([1 => 'second']);
        $newData = $this->getData([0 => 'first', 1 => 'second', 2 => 'third']);

        $listener = new MergeCollectionListener(true, $allowDelete);

        $this->form->setData($originalData);

        $event = new FormEvent($this->form, $newData);
        $listener->onSubmit($event);

        // The original object was modified
        if (\is_object($originalData)) {
            $this->assertSame($originalData, $event->getData());
        }

        // The original object matches the new object
        $this->assertEquals($newData, $event->getData());
    }

    /**
     * @dataProvider getBooleanMatrix1
     */
    public function testAddExtraEntriesIfAllowAddDontOverwriteExistingIndices($allowDelete)
    {
        $originalData = $this->getData([1 => 'first']);
        $newData = $this->getData([0 => 'first', 1 => 'second']);

        $listener = new MergeCollectionListener(true, $allowDelete);

        $this->form->setData($originalData);

        $event = new FormEvent($this->form, $newData);
        $listener->onSubmit($event);

        // The original object was modified
        if (\is_object($originalData)) {
            $this->assertSame($originalData, $event->getData());
        }

        // The original object matches the new object
        $this->assertEquals($this->getData([1 => 'first', 2 => 'second']), $event->getData());
    }

    /**
     * @dataProvider getBooleanMatrix1
     */
    public function testDoNothingIfNotAllowAdd($allowDelete)
    {
        $originalDataArray = [1 => 'second'];
        $originalData = $this->getData($originalDataArray);
        $newData = $this->getData([0 => 'first', 1 => 'second', 2 => 'third']);

        $listener = new MergeCollectionListener(false, $allowDelete);

        $this->form->setData($originalData);

        $event = new FormEvent($this->form, $newData);
        $listener->onSubmit($event);

        // We still have the original object
        if (\is_object($originalData)) {
            $this->assertSame($originalData, $event->getData());
        }

        // Nothing was removed
        $this->assertEquals($this->getData($originalDataArray), $event->getData());
    }

    /**
     * @dataProvider getBooleanMatrix1
     */
    public function testRemoveMissingEntriesIfAllowDelete($allowAdd)
    {
        $originalData = $this->getData([0 => 'first', 1 => 'second', 2 => 'third']);
        $newData = $this->getData([1 => 'second']);

        $listener = new MergeCollectionListener($allowAdd, true);

        $this->form->setData($originalData);

        $event = new FormEvent($this->form, $newData);
        $listener->onSubmit($event);

        // The original object was modified
        if (\is_object($originalData)) {
            $this->assertSame($originalData, $event->getData());
        }

        // The original object matches the new object
        $this->assertEquals($newData, $event->getData());
    }

    /**
     * @dataProvider getBooleanMatrix1
     */
    public function testDoNothingIfNotAllowDelete($allowAdd)
    {
        $originalDataArray = [0 => 'first', 1 => 'second', 2 => 'third'];
        $originalData = $this->getData($originalDataArray);
        $newData = $this->getData([1 => 'second']);

        $listener = new MergeCollectionListener($allowAdd, false);

        $this->form->setData($originalData);

        $event = new FormEvent($this->form, $newData);
        $listener->onSubmit($event);

        // We still have the original object
        if (\is_object($originalData)) {
            $this->assertSame($originalData, $event->getData());
        }

        // Nothing was removed
        $this->assertEquals($this->getData($originalDataArray), $event->getData());
    }

    /**
     * @dataProvider getBooleanMatrix2
     * @expectedException \Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testRequireArrayOrTraversable($allowAdd, $allowDelete)
    {
        $newData = 'no array or traversable';
        $event = new FormEvent($this->form, $newData);
        $listener = new MergeCollectionListener($allowAdd, $allowDelete);
        $listener->onSubmit($event);
    }

    public function testDealWithNullData()
    {
        $originalData = $this->getData([0 => 'first', 1 => 'second', 2 => 'third']);
        $newData = null;

        $listener = new MergeCollectionListener(false, false);

        $this->form->setData($originalData);

        $event = new FormEvent($this->form, $newData);
        $listener->onSubmit($event);

        $this->assertSame($originalData, $event->getData());
    }

    /**
     * @dataProvider getBooleanMatrix1
     */
    public function testDealWithNullOriginalDataIfAllowAdd($allowDelete)
    {
        $originalData = null;
        $newData = $this->getData([0 => 'first', 1 => 'second', 2 => 'third']);

        $listener = new MergeCollectionListener(true, $allowDelete);

        $this->form->setData($originalData);

        $event = new FormEvent($this->form, $newData);
        $listener->onSubmit($event);

        $this->assertSame($newData, $event->getData());
    }

    /**
     * @dataProvider getBooleanMatrix1
     */
    public function testDontDealWithNullOriginalDataIfNotAllowAdd($allowDelete)
    {
        $originalData = null;
        $newData = $this->getData([0 => 'first', 1 => 'second', 2 => 'third']);

        $listener = new MergeCollectionListener(false, $allowDelete);

        $this->form->setData($originalData);

        $event = new FormEvent($this->form, $newData);
        $listener->onSubmit($event);

        $this->assertNull($event->getData());
    }
}
