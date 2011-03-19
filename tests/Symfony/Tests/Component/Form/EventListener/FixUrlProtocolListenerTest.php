<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\EventListener;

use Symfony\Component\Form\Event\FilterDataEvent;
use Symfony\Component\Form\EventListener\FixUrlProtocolListener;

class FixUrlProtocolListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testFixHttpUrl()
    {
        $data = "www.symfony.com";
        $field = $this->getMock('Symfony\Component\Form\FieldInterface');
        $event = new FilterDataEvent($field, $data);

        $filter = new FixUrlProtocolListener('http');
        $filter->filterBoundNormData($event);

        $this->assertEquals('http://www.symfony.com', $event->getData());
    }

    public function testSkipKnownUrl()
    {
        $data = "http://www.symfony.com";
        $field = $this->getMock('Symfony\Component\Form\FieldInterface');
        $event = new FilterDataEvent($field, $data);

        $filter = new FixUrlProtocolListener('http');
        $filter->filterBoundNormData($event);

        $this->assertEquals('http://www.symfony.com', $event->getData());
    }

    public function testSkipOtherProtocol()
    {
        $data = "ftp://www.symfony.com";
        $field = $this->getMock('Symfony\Component\Form\FieldInterface');
        $event = new FilterDataEvent($field, $data);

        $filter = new FixUrlProtocolListener('http');
        $filter->filterBoundNormData($event);

        $this->assertEquals('ftp://www.symfony.com', $event->getData());
    }
}