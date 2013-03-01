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

use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\Extension\Core\EventListener\FixRadioInputListener;
use Symfony\Component\Form\Extension\Core\ChoiceList\SimpleChoiceList;

class FixRadioInputListenerTest extends \PHPUnit_Framework_TestCase
{
    private $listener;

    protected function setUp()
    {
        if (!class_exists('Symfony\Component\EventDispatcher\EventDispatcher')) {
            $this->markTestSkipped('The "EventDispatcher" component is not available');
        }

        parent::setUp();

        $list = new SimpleChoiceList(array(0 => 'A', 1 => 'B'));
        $this->listener = new FixRadioInputListener($list);
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->listener = null;
    }

    public function testFixRadio()
    {
        $data = '1';
        $form = $this->getMock('Symfony\Component\Form\Tests\FormInterface');
        $event = new FormEvent($form, $data);

        $this->listener->preBind($event);

        $this->assertEquals(array(1 => '1'), $event->getData());
    }

    public function testFixZero()
    {
        $data = '0';
        $form = $this->getMock('Symfony\Component\Form\Tests\FormInterface');
        $event = new FormEvent($form, $data);

        $this->listener->preBind($event);

        $this->assertEquals(array(0 => '0'), $event->getData());
    }

    public function testIgnoreEmptyString()
    {
        $data = '';
        $form = $this->getMock('Symfony\Component\Form\Tests\FormInterface');
        $event = new FormEvent($form, $data);

        $this->listener->preBind($event);

        $this->assertEquals(array(), $event->getData());
    }
}
