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
use Symfony\Component\Form\Extension\Core\EventListener\FixUrlProtocolListener;

class FixUrlProtocolListenerTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Symfony\Component\EventDispatcher\EventDispatcher')) {
            $this->markTestSkipped('The "EventDispatcher" component is not available');
        }
    }

    public function testFixHttpUrl()
    {
        $data = "www.symfony.com";
        $form = $this->getMock('Symfony\Component\Form\Test\FormInterface');
        $event = new FormEvent($form, $data);

        $filter = new FixUrlProtocolListener('http');
        $filter->onSubmit($event);

        $this->assertEquals('http://www.symfony.com', $event->getData());
    }

    public function testSkipKnownUrl()
    {
        $data = "http://www.symfony.com";
        $form = $this->getMock('Symfony\Component\Form\Test\FormInterface');
        $event = new FormEvent($form, $data);

        $filter = new FixUrlProtocolListener('http');
        $filter->onSubmit($event);

        $this->assertEquals('http://www.symfony.com', $event->getData());
    }

    public function testSkipOtherProtocol()
    {
        $data = "ftp://www.symfony.com";
        $form = $this->getMock('Symfony\Component\Form\Test\FormInterface');
        $event = new FormEvent($form, $data);

        $filter = new FixUrlProtocolListener('http');
        $filter->onSubmit($event);

        $this->assertEquals('ftp://www.symfony.com', $event->getData());
    }
}
