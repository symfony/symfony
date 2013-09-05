<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\DataCollector\EventListener;


use Symfony\Component\Form\Extension\DataCollector\EventListener\DataCollectorSubscriber;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

/**
 * @covers Symfony\Component\Form\Extension\DataCollector\EventListener\DataCollectorSubscriber
 */
class DataCollectorSubscriberTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var DataCollectorSubscriber
     */
    private $eventSubscriber;

    /**
     * @var DataCollectorInterface
     */
    private $collector;

    public function setUp()
    {
        $this->collector = $this->getMockBuilder('Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface')->setMethods(array('addError','collect','getName'))->getMock();
        $this->eventSubscriber = new DataCollectorSubscriber($this->collector);
    }

    public function testSubscribedEvents()
    {
        $events = DataCollectorSubscriber::getSubscribedEvents();

        $this->assertInternalType('array', $events);
        $this->assertEquals(array(FormEvents::POST_SUBMIT => array('addToProfiler', -255)), $events);
    }

    public function testAddToProfilerWithSubForm()
    {
        $form = $this->getMock('Symfony\Component\Form\Test\FormInterface');
        $form->expects($this->atLeastOnce())->method('isRoot')->will($this->returnValue(false));

        $formEvent = new FormEvent($form, array());

        $this->collector->expects($this->never())->method('addError');
        $this->eventSubscriber->addToProfiler($formEvent);
    }

    public function testAddToProfilerWithValidForm()
    {
        $form = $this->getMock('Symfony\Component\Form\Test\FormInterface');
        $form->expects($this->atLeastOnce())->method('isRoot')->will($this->returnValue(true));
        $form->expects($this->atLeastOnce())->method('isValid')->will($this->returnValue(true));

        $formEvent = new FormEvent($form, array());

        $this->collector->expects($this->never())->method('addError');
        $this->eventSubscriber->addToProfiler($formEvent);
    }

    public function testAddToProfilerWithInValidForm()
    {
        $form = $this->getMock('Symfony\Component\Form\Test\FormInterface');
        $config = $this->getMock('Symfony\Component\Form\FormConfigInterface');
        $type = $this->getMock('Symfony\Component\Form\FormTypeInterface');

        $form->expects($this->atLeastOnce())->method('isRoot')->will($this->returnValue(true));
        $form->expects($this->atLeastOnce())->method('isValid')->will($this->returnValue(false));
        $form->expects($this->atLeastOnce())->method('getErrors')->will($this->returnValue(array('foo')));
        $form->expects($this->any())->method('getRoot')->will($this->returnSelf());
        $form->expects($this->any())->method('getConfig')->will($this->returnValue($config));
        $form->expects($this->any())->method('all')->will($this->returnValue(array($form)));

        $config->expects($this->atLeastOnce())->method('getType')->will($this->returnValue($type));
        $formEvent = new FormEvent($form, array());

        $this->collector->expects($this->exactly(2))->method('addError')->with($this->isType('array'));
        $this->eventSubscriber->addToProfiler($formEvent);
    }
}
 