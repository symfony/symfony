<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\ResourceWatcher\Tracker;

use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\ResourceWatcher\Tracker\RecursiveIteratorTracker;
use Symfony\Component\ResourceWatcher\Event\Event;

class RecursiveIteratorTrackerTestTest extends \PHPUnit_Framework_TestCase
{
    public function testIsResourceTracked()
    {
        $tracker    = new RecursiveIteratorTracker();
        $resource   = new FileResource(__FILE__);

        $this->assertFalse($tracker->isResourceTracked($resource));

        $checker = $this
            ->getMockBuilder('Symfony\Component\ResourceWatcher\StateChecker\StateCheckerInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $checker
            ->expects($this->once())
            ->method('getResource')
            ->will($this->returnValue($resource));

        $tracker->addStateChecker($checker);

        $this->assertTrue($tracker->isResourceTracked($resource));
    }

    public function testGetResourceTrackingId()
    {
        $tracker = new RecursiveIteratorTracker();
        $file    = new FileResource(__FILE__);
        $dir     = new DirectoryResource(__DIR__);

        $this->assertNotNull($tracker->getResourceTrackingId($file));
        $this->assertNotNull($tracker->getResourceTrackingId($dir));

        $this->assertNotEquals(
            $tracker->getResourceTrackingId($file), $tracker->getResourceTrackingId($dir)
        );
    }

    public function testCheckChanges()
    {
        $tracker    = new RecursiveIteratorTracker();
        $resource   = new FileResource(__FILE__);
        $trackingId = $tracker->getResourceTrackingId($resource);

        $checker = $this
            ->getMockBuilder('Symfony\Component\ResourceWatcher\StateChecker\StateCheckerInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $checker
            ->expects($this->once())
            ->method('getResource')
            ->will($this->returnValue($resource));

        $tracker->addStateChecker($checker);

        $checker
            ->expects($this->once())
            ->method('checkChanges')
            ->will($this->returnValue(array(
                'created'   => array($resource),
                'modified'  => array($resource),
                'deleted'   => array($resource),
            )));

        $this->assertEquals(array(
            new Event($trackingId, $resource, Event::CREATED),
            new Event($trackingId, $resource, Event::MODIFIED),
            new Event($trackingId, $resource, Event::DELETED),
        ), $tracker->checkChanges());
    }
}
