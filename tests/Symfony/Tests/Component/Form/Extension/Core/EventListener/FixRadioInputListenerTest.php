<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Core\EventListener;

use Symfony\Component\Form\Event\FilterDataEvent;
use Symfony\Component\Form\Extension\Core\EventListener\FixRadioInputListener;

class FixRadioInputListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testFixRadio()
    {
        $data = '1';
        $form = $this->getMock('Symfony\Tests\Component\Form\FormInterface');
        $event = new FilterDataEvent($form, $data);

        $filter = new FixRadioInputListener();
        $filter->onBindClientData($event);

        $this->assertEquals(array('1' => true), $event->getData());
    }

    public function testFixZero()
    {
        $data = '0';
        $form = $this->getMock('Symfony\Tests\Component\Form\FormInterface');
        $event = new FilterDataEvent($form, $data);

        $filter = new FixRadioInputListener();
        $filter->onBindClientData($event);

        $this->assertEquals(array('0' => true), $event->getData());
    }

    public function testIgnoreEmptyString()
    {
        $data = '';
        $form = $this->getMock('Symfony\Tests\Component\Form\FormInterface');
        $event = new FilterDataEvent($form, $data);

        $filter = new FixRadioInputListener();
        $filter->onBindClientData($event);

        $this->assertEquals(array(), $event->getData());
    }
}
