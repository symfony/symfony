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
use Symfony\Component\Form\EventListener\TrimListener;

class TrimListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testTrim()
    {
        $data = " Foo! ";
        $field = $this->getMock('Symfony\Component\Form\FieldInterface');
        $event = new FilterDataEvent($field, $data);

        $filter = new TrimListener();
        $filter->filterBoundClientData($event);

        $this->assertEquals('Foo!', $event->getData());
    }

    public function testTrimSkipNonStrings()
    {
        $data = 1234;
        $field = $this->getMock('Symfony\Component\Form\FieldInterface');
        $event = new FilterDataEvent($field, $data);

        $filter = new TrimListener();
        $filter->filterBoundClientData($event);

        $this->assertSame(1234, $event->getData());
    }
}