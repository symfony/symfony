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
use Symfony\Component\Form\Extension\DataCollector\Collector\FormCollector;

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
     * @var FormCollector
     */
    private $collector;

    public function setUp()
    {
        $this->collector = $this->getMockBuilder('Symfony\Component\Form\Extension\DataCollector\Collector\FormCollector')->setMethods(array('addError'))->getMock();
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

    public function testAddToProfilerWithInValidForm()
    {
        $form = $this->getMock('Symfony\Component\Form\Test\FormInterface');
        $form->expects($this->atLeastOnce())->method('isRoot')->will($this->returnValue(true));
        $form->expects($this->atLeastOnce())->method('getErrors')->will($this->returnValue(array('foo')));
        $form->expects($this->once())->method('all')->will($this->returnValue(array()));

        $formEvent = new FormEvent($form, array());

        $this->collector->expects($this->atLeastOnce())->method('addError')->with($form);
        $this->eventSubscriber->addToProfiler($formEvent);
    }
}
 