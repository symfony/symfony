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
use Symfony\Component\Form\Extension\Core\EventListener\ResizeFormListener;
use Symfony\Component\Form\FormBuilder;

class ResizeFormListenerTest extends \PHPUnit_Framework_TestCase
{
    private $dispatcher;
    private $factory;
    private $form;

    public function setUp()
    {
        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $this->factory = $this->getMock('Symfony\Component\Form\FormFactoryInterface');
        $this->form = $this->getForm();
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

    public function testPreSetDataResizesForm()
    {
        $this->form->add($this->getForm('0'));
        $this->form->add($this->getForm('1'));

        $this->factory->expects($this->at(0))
            ->method('createNamed')
            ->with('text', 1, null, array('property_path' => '[1]', 'max_length' => 10))
            ->will($this->returnValue($this->getForm('1')));
        $this->factory->expects($this->at(1))
            ->method('createNamed')
            ->with('text', 2, null, array('property_path' => '[2]', 'max_length' => 10))
            ->will($this->returnValue($this->getForm('2')));

        $data = array(1 => 'string', 2 => 'string');
        $event = new DataEvent($this->form, $data);
        $listener = new ResizeFormListener($this->factory, 'text', array('max_length' => '10'), false, false);
        $listener->preSetData($event);

        $this->assertFalse($this->form->has('0'));
        $this->assertTrue($this->form->has('1'));
        $this->assertTrue($this->form->has('2'));
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testPreSetDataRequiresArrayOrTraversable()
    {
        $data = 'no array or traversable';
        $event = new DataEvent($this->form, $data);
        $listener = new ResizeFormListener($this->factory, 'text', array(), false, false);
        $listener->preSetData($event);
    }

    public function testPreSetDataDealsWithNullData()
    {
        $this->factory->expects($this->never())->method('createNamed');

        $data = null;
        $event = new DataEvent($this->form, $data);
        $listener = new ResizeFormListener($this->factory, 'text', array(), false, false);
        $listener->preSetData($event);
    }

    public function testPreBindResizesUpIfAllowAdd()
    {
        $this->form->add($this->getForm('0'));

        $this->factory->expects($this->once())
            ->method('createNamed')
            ->with('text', 1, null, array('property_path' => '[1]', 'max_length' => 10))
            ->will($this->returnValue($this->getForm('1')));

        $data = array(0 => 'string', 1 => 'string');
        $event = new DataEvent($this->form, $data);
        $listener = new ResizeFormListener($this->factory, 'text', array('max_length' => 10), true, false);
        $listener->preBind($event);

        $this->assertTrue($this->form->has('0'));
        $this->assertTrue($this->form->has('1'));
    }

    public function testPreBindResizesDownIfAllowDelete()
    {
        $this->form->add($this->getForm('0'));
        $this->form->add($this->getForm('1'));

        $data = array(0 => 'string');
        $event = new DataEvent($this->form, $data);
        $listener = new ResizeFormListener($this->factory, 'text', array(), false, true);
        $listener->preBind($event);

        $this->assertTrue($this->form->has('0'));
        $this->assertFalse($this->form->has('1'));
    }

    // fix for https://github.com/symfony/symfony/pull/493
    public function testPreBindRemovesZeroKeys()
    {
        $this->form->add($this->getForm('0'));

        $data = array();
        $event = new DataEvent($this->form, $data);
        $listener = new ResizeFormListener($this->factory, 'text', array(), false, true);
        $listener->preBind($event);

        $this->assertFalse($this->form->has('0'));
    }

    public function testPreBindDoesNothingIfNotAllowAddNorAllowDelete()
    {
        $this->form->add($this->getForm('0'));
        $this->form->add($this->getForm('1'));

        $data = array(0 => 'string', 2 => 'string');
        $event = new DataEvent($this->form, $data);
        $listener = new ResizeFormListener($this->factory, 'text', array(), false, false);
        $listener->preBind($event);

        $this->assertTrue($this->form->has('0'));
        $this->assertTrue($this->form->has('1'));
        $this->assertFalse($this->form->has('2'));
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testPreBindRequiresArrayOrTraversable()
    {
        $data = 'no array or traversable';
        $event = new DataEvent($this->form, $data);
        $listener = new ResizeFormListener($this->factory, 'text', array(), false, false);
        $listener->preBind($event);
    }

    public function testPreBindDealsWithNullData()
    {
        $this->form->add($this->getForm('1'));

        $data = null;
        $event = new DataEvent($this->form, $data);
        $listener = new ResizeFormListener($this->factory, 'text', array(), false, true);
        $listener->preBind($event);

        $this->assertFalse($this->form->has('1'));
    }

    // fixes https://github.com/symfony/symfony/pull/40
    public function testPreBindDealsWithEmptyData()
    {
        $this->form->add($this->getForm('1'));

        $data = '';
        $event = new DataEvent($this->form, $data);
        $listener = new ResizeFormListener($this->factory, 'text', array(), false, true);
        $listener->preBind($event);

        $this->assertFalse($this->form->has('1'));
    }

    public function testOnBindNormDataRemovesEntriesMissingInTheFormIfAllowDelete()
    {
        $this->form->add($this->getForm('1'));

        $data = array(0 => 'first', 1 => 'second', 2 => 'third');
        $event = new FilterDataEvent($this->form, $data);
        $listener = new ResizeFormListener($this->factory, 'text', array(), false, true);
        $listener->onBindNormData($event);

        $this->assertEquals(array(1 => 'second'), $event->getData());
    }

    public function testOnBindNormDataDoesNothingIfNotAllowDelete()
    {
        $this->form->add($this->getForm('1'));

        $data = array(0 => 'first', 1 => 'second', 2 => 'third');
        $event = new FilterDataEvent($this->form, $data);
        $listener = new ResizeFormListener($this->factory, 'text', array(), false, false);
        $listener->onBindNormData($event);

        $this->assertEquals($data, $event->getData());
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testOnBindNormDataRequiresArrayOrTraversable()
    {
        $data = 'no array or traversable';
        $event = new FilterDataEvent($this->form, $data);
        $listener = new ResizeFormListener($this->factory, 'text', array(), false, false);
        $listener->onBindNormData($event);
    }

    public function testOnBindNormDataDealsWithNullData()
    {
        $this->form->add($this->getForm('1'));

        $data = null;
        $event = new FilterDataEvent($this->form, $data);
        $listener = new ResizeFormListener($this->factory, 'text', array(), false, true);
        $listener->onBindNormData($event);

        $this->assertEquals(array(), $event->getData());
    }
}
