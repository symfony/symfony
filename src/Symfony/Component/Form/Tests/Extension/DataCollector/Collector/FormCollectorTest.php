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
        $c = new FormCollector();

        $c->addError(array('value'=>'bazz','root'=>'foo','name'=>'bar'));

        $this->assertInternalType('array', $c->getData());
        $this->assertEquals(1, $c->getDataCount());
        $this->assertEquals(array('foo'=>array('bar'=>array('value'=>'bazz','root'=>'foo','name'=>'bar'))), $c->getData());
    }
}
 