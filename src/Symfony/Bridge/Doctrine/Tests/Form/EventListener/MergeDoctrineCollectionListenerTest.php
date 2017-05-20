<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Form\EventListener;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Form\EventListener\MergeDoctrineCollectionListener;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class MergeDoctrineCollectionListenerTest extends TestCase
{
    /** @var \Doctrine\Common\Collections\ArrayCollection */
    private $collection;
    /** @var \Symfony\Component\EventDispatcher\EventDispatcher */
    private $dispatcher;
    private $factory;
    private $form;

    protected function setUp()
    {
        $this->collection = new ArrayCollection(array('test'));
        $this->dispatcher = new EventDispatcher();
        $this->factory = $this->getMockBuilder('Symfony\Component\Form\FormFactoryInterface')->getMock();
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

    /**
     * @group legacy
     */
    public function testLegacyChildClassOnSubmitCallParent()
    {
        $form = $this->getBuilder('name')
            ->setData($this->collection)
            ->addEventSubscriber(new TestClassExtendingMergeDoctrineCollectionListener())
            ->getForm();
        $submittedData = array();
        $event = new FormEvent($form, $submittedData);

        $this->dispatcher->dispatch(FormEvents::SUBMIT, $event);

        $this->assertTrue($this->collection->isEmpty());
        $this->assertTrue(TestClassExtendingMergeDoctrineCollectionListener::$onBindCalled);
    }
}

/**
 * @group legacy
 */
class TestClassExtendingMergeDoctrineCollectionListener extends MergeDoctrineCollectionListener
{
    public static $onBindCalled = false;

    public function onBind(FormEvent $event)
    {
        self::$onBindCalled = true;

        parent::onBind($event);
    }
}
