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

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\EventListener\ResizeFormListener;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormEvent;

class ResizeFormListenerTest extends TestCase
{
    private $dispatcher;
    private $factory;
    private $form;

    protected function setUp()
    {
        $this->dispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock();
        $this->factory = $this->getMockBuilder('Symfony\Component\Form\FormFactoryInterface')->getMock();
        $this->form = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->getForm();
    }

    protected function tearDown()
    {
        $this->dispatcher = null;
        $this->factory = null;
        $this->form = null;
    }

    protected function getBuilder($name = 'name')
    {
        return new FormBuilder($name, null, $this->dispatcher, $this->factory);
    }

    protected function getForm($name = 'name')
    {
        return $this->getBuilder($name)->getForm();
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getDataMapper()
    {
        return $this->getMockBuilder('Symfony\Component\Form\DataMapperInterface')->getMock();
    }

    protected function getMockForm()
    {
        return $this->getMockBuilder('Symfony\Component\Form\Test\FormInterface')->getMock();
    }

    public function testPreSetDataResizesForm()
    {
        $this->form->add($this->getForm('0'));
        $this->form->add($this->getForm('1'));

        $this->factory->expects($this->at(0))
            ->method('createNamed')
            ->with(1, 'text', null, array('property_path' => '[1]', 'attr' => array('maxlength' => 10), 'auto_initialize' => false))
            ->will($this->returnValue($this->getForm('1')));
        $this->factory->expects($this->at(1))
            ->method('createNamed')
            ->with(2, 'text', null, array('property_path' => '[2]', 'attr' => array('maxlength' => 10), 'auto_initialize' => false))
            ->will($this->returnValue($this->getForm('2')));

        $data = array(1 => 'string', 2 => 'string');
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener('text', array('attr' => array('maxlength' => 10)), false, false);
        $listener->preSetData($event);

        $this->assertFalse($this->form->has('0'));
        $this->assertTrue($this->form->has('1'));
        $this->assertTrue($this->form->has('2'));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testPreSetDataRequiresArrayOrTraversable()
    {
        $data = 'no array or traversable';
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener('text', array(), false, false);
        $listener->preSetData($event);
    }

    public function testPreSetDataDealsWithNullData()
    {
        $this->factory->expects($this->never())->method('createNamed');

        $data = null;
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener('text', array(), false, false);
        $listener->preSetData($event);
    }

    public function testPreSubmitResizesUpIfAllowAdd()
    {
        $this->form->add($this->getForm('0'));

        $this->factory->expects($this->once())
            ->method('createNamed')
            ->with(1, 'text', null, array('property_path' => '[1]', 'attr' => array('maxlength' => 10), 'auto_initialize' => false))
            ->will($this->returnValue($this->getForm('1')));

        $data = array(0 => 'string', 1 => 'string');
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener('text', array('attr' => array('maxlength' => 10)), true, false);
        $listener->preSubmit($event);

        $this->assertTrue($this->form->has('0'));
        $this->assertTrue($this->form->has('1'));
    }

    public function testPreSubmitResizesDownIfAllowDelete()
    {
        $this->form->add($this->getForm('0'));
        $this->form->add($this->getForm('1'));

        $data = array(0 => 'string');
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener('text', array(), false, true);
        $listener->preSubmit($event);

        $this->assertTrue($this->form->has('0'));
        $this->assertFalse($this->form->has('1'));
    }

    // fix for https://github.com/symfony/symfony/pull/493
    public function testPreSubmitRemovesZeroKeys()
    {
        $this->form->add($this->getForm('0'));

        $data = array();
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener('text', array(), false, true);
        $listener->preSubmit($event);

        $this->assertFalse($this->form->has('0'));
    }

    public function testPreSubmitDoesNothingIfNotAllowAddNorAllowDelete()
    {
        $this->form->add($this->getForm('0'));
        $this->form->add($this->getForm('1'));

        $data = array(0 => 'string', 2 => 'string');
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener('text', array(), false, false);
        $listener->preSubmit($event);

        $this->assertTrue($this->form->has('0'));
        $this->assertTrue($this->form->has('1'));
        $this->assertFalse($this->form->has('2'));
    }

    public function testPreSubmitDealsWithNoArrayOrTraversable()
    {
        $data = 'no array or traversable';
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener('text', array(), false, false);
        $listener->preSubmit($event);

        $this->assertFalse($this->form->has('1'));
    }

    public function testPreSubmitDealsWithNullData()
    {
        $this->form->add($this->getForm('1'));

        $data = null;
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener('text', array(), false, true);
        $listener->preSubmit($event);

        $this->assertFalse($this->form->has('1'));
    }

    // fixes https://github.com/symfony/symfony/pull/40
    public function testPreSubmitDealsWithEmptyData()
    {
        $this->form->add($this->getForm('1'));

        $data = '';
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener('text', array(), false, true);
        $listener->preSubmit($event);

        $this->assertFalse($this->form->has('1'));
    }

    public function testOnSubmitNormDataRemovesEntriesMissingInTheFormIfAllowDelete()
    {
        $this->form->add($this->getForm('1'));

        $data = array(0 => 'first', 1 => 'second', 2 => 'third');
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener('text', array(), false, true);
        $listener->onSubmit($event);

        $this->assertEquals(array(1 => 'second'), $event->getData());
    }

    public function testOnSubmitNormDataDoesNothingIfNotAllowDelete()
    {
        $this->form->add($this->getForm('1'));

        $data = array(0 => 'first', 1 => 'second', 2 => 'third');
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener('text', array(), false, false);
        $listener->onSubmit($event);

        $this->assertEquals($data, $event->getData());
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testOnSubmitNormDataRequiresArrayOrTraversable()
    {
        $data = 'no array or traversable';
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener('text', array(), false, false);
        $listener->onSubmit($event);
    }

    public function testOnSubmitNormDataDealsWithNullData()
    {
        $this->form->add($this->getForm('1'));

        $data = null;
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener('text', array(), false, true);
        $listener->onSubmit($event);

        $this->assertEquals(array(), $event->getData());
    }

    public function testOnSubmitDealsWithObjectBackedIteratorAggregate()
    {
        $this->form->add($this->getForm('1'));

        $data = new \ArrayObject(array(0 => 'first', 1 => 'second', 2 => 'third'));
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener('text', array(), false, true);
        $listener->onSubmit($event);

        $this->assertArrayNotHasKey(0, $event->getData());
        $this->assertArrayNotHasKey(2, $event->getData());
    }

    public function testOnSubmitDealsWithArrayBackedIteratorAggregate()
    {
        $this->form->add($this->getForm('1'));

        $data = new ArrayCollection(array(0 => 'first', 1 => 'second', 2 => 'third'));
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener('text', array(), false, true);
        $listener->onSubmit($event);

        $this->assertArrayNotHasKey(0, $event->getData());
        $this->assertArrayNotHasKey(2, $event->getData());
    }

    public function testOnSubmitDeleteEmptyNotCompoundEntriesIfAllowDelete()
    {
        $this->form->setData(array('0' => 'first', '1' => 'second'));
        $this->form->add($this->getForm('0'));
        $this->form->add($this->getForm('1'));

        $data = array(0 => 'first', 1 => '');
        foreach ($data as $child => $dat) {
            $this->form->get($child)->setData($dat);
        }
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener('text', array(), false, true, true);
        $listener->onSubmit($event);

        $this->assertEquals(array(0 => 'first'), $event->getData());
    }

    public function testOnSubmitDeleteEmptyCompoundEntriesIfAllowDelete()
    {
        $this->form->setData(array('0' => array('name' => 'John'), '1' => array('name' => 'Jane')));
        $form1 = $this->getBuilder('0')
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->getForm();
        $form1->add($this->getForm('name'));
        $form2 = $this->getBuilder('1')
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->getForm();
        $form2->add($this->getForm('name'));
        $this->form->add($form1);
        $this->form->add($form2);

        $data = array('0' => array('name' => 'John'), '1' => array('name' => ''));
        foreach ($data as $child => $dat) {
            $this->form->get($child)->setData($dat);
        }
        $event = new FormEvent($this->form, $data);
        $callback = function ($data) {
            return '' === $data['name'];
        };
        $listener = new ResizeFormListener('text', array(), false, true, $callback);
        $listener->onSubmit($event);

        $this->assertEquals(array('0' => array('name' => 'John')), $event->getData());
    }
}
