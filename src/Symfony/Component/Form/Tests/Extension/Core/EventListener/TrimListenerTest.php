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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\EventListener\TrimListener;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormConfigInterface;
use Symfony\Component\Form\FormEvent;

class TrimListenerTest extends TestCase
{
    public function testTrim()
    {
        $data = ' Foo! ';
        $form = new Form($this->getMockBuilder(FormConfigInterface::class)->getMock());
        $event = new FormEvent($form, $data);

        $filter = new TrimListener();
        $filter->preSubmit($event);

        $this->assertEquals('Foo!', $event->getData());
    }

    public function testTrimSkipNonStrings()
    {
        $data = 1234;
        $form = new Form($this->getMockBuilder(FormConfigInterface::class)->getMock());
        $event = new FormEvent($form, $data);

        $filter = new TrimListener();
        $filter->preSubmit($event);

        $this->assertSame(1234, $event->getData());
    }
}
