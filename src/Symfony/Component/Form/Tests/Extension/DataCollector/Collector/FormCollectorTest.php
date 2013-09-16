<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\DataCollector\Collector;

use Symfony\Component\Form\Extension\DataCollector\Collector\FormCollector;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class FormCollectorTest extends \PHPUnit_Framework_TestCase
{
    public function testSubscribedEvents()
    {
        $events = FormCollector::getSubscribedEvents();

        $this->assertInternalType('array', $events);
        $this->assertEquals(array(FormEvents::POST_SUBMIT => array('collectForm', -255)), $events);
    }

    public function testCollect()
    {
        $form = $this->getMock('Symfony\Component\Form\Test\FormInterface');
        $subForm = $this->getMock('Symfony\Component\Form\Test\FormInterface');

        $type = $this->getMock('Symfony\Component\Form\FormTypeInterface');
        $type->expects($this->atLeastOnce())->method('getName')->will($this->returnValue('fizz'));

        $config = $this->getMock('Symfony\Component\Form\FormConfigInterface');
        $config->expects($this->atLeastOnce())->method('getType')->will($this->returnValue($type));

        $form->expects($this->atLeastOnce())->method('all')->will($this->returnValue(array($subForm)));
        $form->expects($this->atLeastOnce())->method('isRoot')->will($this->returnValue(true));
        $form->expects($this->atLeastOnce())->method('getName')->will($this->returnValue('foo'));

        $subForm->expects($this->atLeastOnce())->method('all')->will($this->returnValue(array()));
        $subForm->expects($this->atLeastOnce())->method('getErrors')->will($this->returnValue(array('foo')));
        $subForm->expects($this->atLeastOnce())->method('getRoot')->will($this->returnValue($form));
        $subForm->expects($this->atLeastOnce())->method('getConfig')->will($this->returnValue($config));
        $subForm->expects($this->atLeastOnce())->method('getPropertyPath')->will($this->returnValue('bar'));
        $subForm->expects($this->atLeastOnce())->method('getViewData')->will($this->returnValue('bazz'));

        $event = new FormEvent($form, array());
        $c = new FormCollector();
        $c->collectForm($event);

        $this->assertInternalType('array', $c->getData());
        $this->assertEquals(1, $c->getErrorCount());
        $this->assertEquals(array('foo' => array('bar' => array('value' => 'bazz', 'root' => 'foo', 'type' => 'fizz', 'name' => 'bar', 'errors' => array('foo')))), $c->getData());
    }
}
 