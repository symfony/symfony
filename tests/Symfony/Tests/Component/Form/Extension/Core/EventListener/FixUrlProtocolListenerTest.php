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
use Symfony\Component\Form\Extension\Core\EventListener\FixUrlProtocolListener;

class FixUrlProtocolListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testFixHttpUrl()
    {
        $data = "www.symfony.com";
        $form = $this->getMock('Symfony\Tests\Component\Form\FormInterface');
        $event = new FilterDataEvent($form, $data);

        $filter = new FixUrlProtocolListener('http');
        $filter->onBindNormData($event);

        $this->assertEquals('http://www.symfony.com', $event->getData());
    }

    public function testSkipKnownUrl()
    {
        $data = "http://www.symfony.com";
        $form = $this->getMock('Symfony\Tests\Component\Form\FormInterface');
        $event = new FilterDataEvent($form, $data);

        $filter = new FixUrlProtocolListener('http');
        $filter->onBindNormData($event);

        $this->assertEquals('http://www.symfony.com', $event->getData());
    }

    public function testSkipOtherProtocol()
    {
        $data = "ftp://www.symfony.com";
        $form = $this->getMock('Symfony\Tests\Component\Form\FormInterface');
        $event = new FilterDataEvent($form, $data);

        $filter = new FixUrlProtocolListener('http');
        $filter->onBindNormData($event);

        $this->assertEquals('ftp://www.symfony.com', $event->getData());
    }
}
