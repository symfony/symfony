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
use Symfony\Component\Form\Extension\Core\EventListener\TrimListener;

class TrimListenerTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Symfony\Component\EventDispatcher\EventDispatcher')) {
            $this->markTestSkipped('The "EventDispatcher" component is not available');
        }
    }

    public function testTrim()
    {
        $data = " Foo! ";
        $form = $this->getMock('Symfony\Component\Form\Tests\FormInterface');
        $event = new FormEvent($form, $data);

        $filter = new TrimListener();
        $filter->preBind($event);

        $this->assertEquals('Foo!', $event->getData());
    }

    public function testTrimSkipNonStrings()
    {
        $data = 1234;
        $form = $this->getMock('Symfony\Component\Form\Tests\FormInterface');
        $event = new FormEvent($form, $data);

        $filter = new TrimListener();
        $filter->preBind($event);

        $this->assertSame(1234, $event->getData());
    }
}
