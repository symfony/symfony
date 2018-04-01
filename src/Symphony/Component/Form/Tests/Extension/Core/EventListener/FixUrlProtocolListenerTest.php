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
use Symphony\Component\Form\Extension\Core\EventListener\FixUrlProtocolListener;

class FixUrlProtocolListenerTest extends TestCase
{
    public function testFixHttpUrl()
    {
        $data = 'www.symphony.com';
        $form = $this->getMockBuilder('Symphony\Component\Form\Test\FormInterface')->getMock();
        $event = new FormEvent($form, $data);

        $filter = new FixUrlProtocolListener('http');
        $filter->onSubmit($event);

        $this->assertEquals('http://www.symphony.com', $event->getData());
    }

    public function testSkipKnownUrl()
    {
        $data = 'http://www.symphony.com';
        $form = $this->getMockBuilder('Symphony\Component\Form\Test\FormInterface')->getMock();
        $event = new FormEvent($form, $data);

        $filter = new FixUrlProtocolListener('http');
        $filter->onSubmit($event);

        $this->assertEquals('http://www.symphony.com', $event->getData());
    }

    public function provideUrlsWithSupportedProtocols()
    {
        return array(
            array('ftp://www.symphony.com'),
            array('chrome-extension://foo'),
            array('h323://foo'),
            array('iris.beep://foo'),
            array('foo+bar://foo'),
        );
    }

    /**
     * @dataProvider provideUrlsWithSupportedProtocols
     */
    public function testSkipOtherProtocol($url)
    {
        $form = $this->getMockBuilder('Symphony\Component\Form\Test\FormInterface')->getMock();
        $event = new FormEvent($form, $url);

        $filter = new FixUrlProtocolListener('http');
        $filter->onSubmit($event);

        $this->assertEquals($url, $event->getData());
    }
}
