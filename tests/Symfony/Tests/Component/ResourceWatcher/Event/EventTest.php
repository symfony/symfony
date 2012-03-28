<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\ResourceWatcher\Event;

use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\ResourceWatcher\Event\Event;

class EventTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructAndGetters()
    {
        $event = new Event($id = 23, $res = new FileResource(__FILE__), $type = Event::MODIFIED);

        $this->assertEquals($id, $event->getTrackingId());
        $this->assertSame($res, $event->getResource());
        $this->assertSame($type, $event->getType());
        $this->assertNotNull($event->getTime());
    }
}
