<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ResourceWatcher\Tests\Event;

use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\ResourceWatcher\Event\FilesystemEvent;
use Symfony\Component\ResourceWatcher\Resource\TrackedResource;

class FilesystemEventTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructAndGetters()
    {
        $event = new FilesystemEvent(
            $tracked  = new TrackedResource(23, new DirectoryResource(__DIR__)),
            $resource = new FileResource(__FILE__),
            $type     = FilesystemEvent::MODIFY
        );

        $this->assertSame($tracked, $event->getTrackedResource());
        $this->assertSame($resource, $event->getResource());
        $this->assertSame($type, $event->getType());
    }

    public function testIsFileChange()
    {
        $event = new FilesystemEvent(
            $tracked  = new TrackedResource(23, new DirectoryResource(__DIR__.'/../')),
            $resource = new FileResource(__FILE__),
            $type     = FilesystemEvent::MODIFY
        );

        $this->assertTrue($event->isFileChange());
        $this->assertFalse($event->isDirectoryChange());
    }

    public function testIsDirectoryChange()
    {
        $event = new FilesystemEvent(
            $tracked  = new TrackedResource(23, new DirectoryResource(__DIR__.'/../')),
            $resource = new DirectoryResource(__DIR__),
            $type     = FilesystemEvent::MODIFY
        );

        $this->assertFalse($event->isFileChange());
        $this->assertTrue($event->isDirectoryChange());
    }

    public function testType()
    {
        $event = new FilesystemEvent(
            new TrackedResource(23, new DirectoryResource(__DIR__.'/../')),
            new DirectoryResource(__DIR__),
            FilesystemEvent::MODIFY
        );

        $this->assertSame(FilesystemEvent::MODIFY, $event->getType());
        $this->assertSame('modify', $event->getTypeString());

        $event = new FilesystemEvent(
            new TrackedResource(23, new DirectoryResource(__DIR__.'/../')),
            new DirectoryResource(__DIR__),
            FilesystemEvent::DELETE
        );

        $this->assertSame(FilesystemEvent::DELETE, $event->getType());
        $this->assertSame('delete', $event->getTypeString());

        $event = new FilesystemEvent(
            new TrackedResource(23, new DirectoryResource(__DIR__.'/../')),
            new DirectoryResource(__DIR__),
            FilesystemEvent::CREATE
        );

        $this->assertSame(FilesystemEvent::CREATE, $event->getType());
        $this->assertSame('create', $event->getTypeString());
    }
}
