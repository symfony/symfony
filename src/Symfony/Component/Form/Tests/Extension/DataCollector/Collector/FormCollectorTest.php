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

/**
 * @covers Symfony\Component\Form\Extension\DataCollector\Collector\FormCollector
 */
class FormCollectorTest extends \PHPUnit_Framework_TestCase
{
    public function testAddError()
    {
        $form = $this->getMock('Symfony\Component\Form\Test\FormInterface');

        $type = $this->getMock('Symfony\Component\Form\FormTypeInterface');
        $type->expects($this->atLeastOnce())->method('getName')->will($this->returnValue('fizz'));

        $config = $this->getMock('Symfony\Component\Form\FormConfigInterface');
        $config->expects($this->atLeastOnce())->method('getType')->will($this->returnValue($type));

        $form->expects($this->atLeastOnce())->method('getRoot')->will($this->returnSelf());
        $form->expects($this->atLeastOnce())->method('getPropertyPath')->will($this->returnValue('bar'));
        $form->expects($this->atLeastOnce())->method('getName')->will($this->returnValue('foo'));
        $form->expects($this->atLeastOnce())->method('getViewData')->will($this->returnValue('bazz'));
        $form->expects($this->atLeastOnce())->method('getErrors')->will($this->returnValue(array('foo')));
        $form->expects($this->atLeastOnce())->method('getConfig')->will($this->returnValue($config));


        $c = new FormCollector();
        $c->addError($form);

        $this->assertInternalType('array', $c->getData());
        $this->assertEquals(1, $c->getDataCount());
        $this->assertEquals(array('foo'=>array('bar'=>array('value'=>'bazz','root'=>'foo','type'=>'fizz','name'=>'bar','errors'=>array('foo')))), $c->getData());
    }
}
 