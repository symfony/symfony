<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\EventListener;

use Symfony\Component\Form\Event\FilterDataEvent;
use Symfony\Component\Form\EventListener\ResizeFormListener;
use Symfony\Component\Form\Event\DataEvent;

class ResizeFormListenerTest extends \PHPUnit_Framework_TestCase
{
    private $factory;
    private $form;

    public function setUp()
    {
        $this->factory = $this->getMock('Symfony\Component\Form\FormFactoryInterface');
        $this->form = $this->getMock('Symfony\Component\Form\Form', array('add', 'has'), array(), '', false);
    }

    public function testResizePreSetData()
    {
        $expectedType = "text";

        $this->factory->expects($this->at(0))
                      ->method('create')
                      ->with($this->equalTo($expectedType), $this->equalTo( 0 ), array('property_path' => '[0]'))
                      ->will($this->returnValue($this->getMock('Symfony\Component\Form\FieldInterface')));
        $this->factory->expects($this->at(1))
                      ->method('create')
                      ->with($this->equalTo($expectedType), $this->equalTo( 1 ), array('property_path' => '[1]'))
                      ->will($this->returnValue($this->getMock('Symfony\Component\Form\FieldInterface')));

        $data = array("string", "string");
        $event = new DataEvent($this->form, $data);
        $listener = new ResizeFormListener($this->factory, $expectedType, false);
        $listener->preSetData($event);
    }

    public function testResizePreSetDataNoArrayThrowsException()
    {
        $this->setExpectedException('Symfony\Component\Form\Exception\UnexpectedTypeException');
        
        $data = "no array or traversable";
        $event = new DataEvent($this->form, $data);
        $listener = new ResizeFormListener($this->factory, "text", false);
        $listener->preSetData($event);
    }

    public function testResizePreSetDataNull()
    {
        $this->factory->expects($this->never())->method('create');

        $data = null;
        $event = new DataEvent($this->form, $data);
        $listener = new ResizeFormListener($this->factory, "text", false);
        $listener->preSetData($event);
    }

    public function testPreBind()
    {
        $expectedType = "text";

        $this->form->expects($this->once())->method('has')->with($this->equalTo('foo'))->will($this->returnValue( false ));
        $this->form->expects($this->once())->method('add')->with($this->isInstanceOf('Symfony\Component\Form\FieldInterface'));
        $this->factory->expects($this->at(0))
                      ->method('create')
                      ->with($this->equalTo($expectedType), $this->equalTo('foo'), $this->equalTo(array('property_path' => '[foo]')))
                      ->will($this->returnValue( $this->getMock('Symfony\Component\Form\FieldInterface') ));

        $data = array("foo" => "bar");
        $event = new DataEvent($this->form, $data);
        $listener = new ResizeFormListener($this->factory, "text", true);
        $listener->preBind($event);
    }
}