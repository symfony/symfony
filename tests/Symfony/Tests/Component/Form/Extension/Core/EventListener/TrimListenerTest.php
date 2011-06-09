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
use Symfony\Component\Form\Extension\Core\EventListener\TrimListener;

class TrimListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testTrim()
    {
        $data = " Foo! ";
        $form = $this->getMock('Symfony\Tests\Component\Form\FormInterface');
        $event = new FilterDataEvent($form, $data);

        $filter = new TrimListener();
        $filter->onBindClientData($event);

        $this->assertEquals('Foo!', $event->getData());
    }

    public function testTrimSkipNonStrings()
    {
        $data = 1234;
        $form = $this->getMock('Symfony\Tests\Component\Form\FormInterface');
        $event = new FilterDataEvent($form, $data);

        $filter = new TrimListener();
        $filter->onBindClientData($event);

        $this->assertSame(1234, $event->getData());
    }
}
