<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bridge\Doctrine\Tests\Form\EventListener;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Symphony\Bridge\Doctrine\Form\EventListener\MergeDoctrineCollectionListener;
use Symphony\Component\EventDispatcher\EventDispatcher;
use Symphony\Component\Form\FormBuilder;
use Symphony\Component\Form\FormEvent;
use Symphony\Component\Form\FormEvents;

class MergeDoctrineCollectionListenerTest extends TestCase
{
    /** @var \Doctrine\Common\Collections\ArrayCollection */
    private $collection;
    /** @var \Symphony\Component\EventDispatcher\EventDispatcher */
    private $dispatcher;
    private $factory;
    private $form;

    protected function setUp()
    {
        $this->collection = new ArrayCollection(array('test'));
        $this->dispatcher = new EventDispatcher();
        $this->factory = $this->getMockBuilder('Symphony\Component\Form\FormFactoryInterface')->getMock();
        $this->form = $this->getBuilder()
            ->getForm();
    }

    protected function tearDown()
    {
        $this->collection = null;
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
        return $this->getBuilder($name)
            ->setData($this->collection)
            ->addEventSubscriber(new MergeDoctrineCollectionListener())
            ->getForm();
    }

    public function testOnSubmitDoNothing()
    {
        $submittedData = array('test');
        $event = new FormEvent($this->getForm(), $submittedData);

        $this->dispatcher->dispatch(FormEvents::SUBMIT, $event);

        $this->assertTrue($this->collection->contains('test'));
        $this->assertSame(1, $this->collection->count());
    }

    public function testOnSubmitNullClearCollection()
    {
        $submittedData = array();
        $event = new FormEvent($this->getForm(), $submittedData);

        $this->dispatcher->dispatch(FormEvents::SUBMIT, $event);

        $this->assertTrue($this->collection->isEmpty());
    }
}
