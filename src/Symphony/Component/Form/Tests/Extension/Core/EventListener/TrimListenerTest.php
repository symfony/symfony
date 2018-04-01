<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Tests\Extension\Core\EventListener;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Form\FormEvent;
use Symphony\Component\Form\Extension\Core\EventListener\TrimListener;

class TrimListenerTest extends TestCase
{
    public function testTrim()
    {
        $data = ' Foo! ';
        $form = $this->getMockBuilder('Symphony\Component\Form\Test\FormInterface')->getMock();
        $event = new FormEvent($form, $data);

        $filter = new TrimListener();
        $filter->preSubmit($event);

        $this->assertEquals('Foo!', $event->getData());
    }

    public function testTrimSkipNonStrings()
    {
        $data = 1234;
        $form = $this->getMockBuilder('Symphony\Component\Form\Test\FormInterface')->getMock();
        $event = new FormEvent($form, $data);

        $filter = new TrimListener();
        $filter->preSubmit($event);

        $this->assertSame(1234, $event->getData());
    }
}
