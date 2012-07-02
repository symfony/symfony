<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ResourceWatcher\Tests\Tracker;

use Symfony\Component\ResourceWatcher\Tracker\InotifyTracker;
use Symfony\Component\ResourceWatcher\Resource\TrackedResource;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\ResourceWatcher\Event\FilesystemEvent;

class InotifyTrackerTest extends TrackerTest
{
    public function setUp()
    {
        if (!function_exists('inotify_init')) {
            $this->markTestSkipped('Inotify is required for this test');
        }

        parent::setUp();
    }

    /**
     * @return TrackerInterface
     */
    protected function getTracker()
    {
        return new InotifyTracker();
    }

    protected function getMinimumInterval()
    {
        return 0;
    }

    /**
     * @expectedException Symfony\Component\ResourceWatcher\Exception\RuntimeException
     */
    public function testEventOverflow()
    {
        $tracker = $this->getMockBuilder('Symfony\Component\ResourceWatcher\Tracker\InotifyTracker')
            ->disableOriginalConstructor()
            ->setMethods(array('readEvents', '__destruct'))
            ->getMock();
        $tracker->expects($this->any())
            ->method('readEvents')
            ->will($this->returnValue(array(array('mask' => IN_Q_OVERFLOW))));

        $tracker->getEvents();
    }

    public function testFileDeletionCreationTriggersModifyEvent()
    {
        $tracker = $this->getTracker();

        touch($foo = $this->tmpDir.'/foo');
        mkdir($dir = $this->tmpDir.'/dir');
        mkdir($subdir = $dir.'/subdir');
        touch($file = $dir.'/file');

        $tracker->track(new TrackedResource('foo', $resource = new FileResource($foo)));
        $tracker->track(new TrackedResource('dir', $resource = new DirectoryResource($dir)));

        unlink($foo);
        touch($foo);
        unlink($file);
        touch($file);

        $events = $tracker->getEvents();
        $this->assertCount(2, $events);

        $this->assertHasResourceEvent($file, FilesystemEvent::MODIFY, $events);
        $this->assertHasResourceEvent($foo, FilesystemEvent::MODIFY, $events);

        rmdir($subdir);
        mkdir($subdir);

        $events = $tracker->getEvents();
        $this->assertCount(0, $events);
    }

    public function testNewResourceDeletionCreationTriggersNoEvents()
    {
        $tracker = $this->getTracker();

        mkdir($dir = $this->tmpDir.'/dir');

        $tracker->track(new TrackedResource('dir', $resource = new DirectoryResource($dir)));

        mkdir($subdir = $dir.'/subdir');
        touch($subfile = $subdir.'/subfile');
        unlink($subfile);
        rmdir($subdir);
        touch($file = $dir.'/file');
        unlink($file);

        $events = $tracker->getEvents();
        $this->assertCount(0, $events);
    }

    public function testDeletedWatchedResourceStillReturnsEvents()
    {
        $tracker = $this->getTracker();

        touch($foo = $this->tmpDir.'/foo');
        mkdir($dir = $this->tmpDir.'/dir');

        $tracker->track(new TrackedResource('foo', $resource = new FileResource($foo)));
        $tracker->track(new TrackedResource('dir', $resource = new DirectoryResource($dir)));

        rmdir($dir);
        unlink($foo);

        $tracker->getEvents();

        touch($foo);
        mkdir($dir);

        $events = $tracker->getEvents();
        $this->assertCount(2, $events);

        $this->assertHasResourceEvent($foo, FilesystemEvent::CREATE, $events);
        $this->assertHasResourceEvent($dir, FilesystemEvent::CREATE, $events);

        touch($foo);
        touch($file = $dir.'/file');

        $events = $tracker->getEvents();
        $this->assertCount(2, $events);

        $this->assertHasResourceEvent($file, FilesystemEvent::CREATE, $events);
        $this->assertHasResourceEvent($foo, FilesystemEvent::MODIFY, $events);
    }

    public function testSymlink()
    {
        $tracker = $this->getTracker();

        mkdir($dir = $this->tmpDir.'/dir');
        touch($file = $dir.'/file');
        @symlink($file, $link = $file.'_link');

        if (!is_link($link)) {
            $this->markTestSkipped('Working "symlink" function is required for this test.');
        }

        $tracker->track(new TrackedResource('dir', $resource = new DirectoryResource($dir)));

        unlink($link);

        $events = $tracker->getEvents();
        $this->assertCount(0, $events);
    }

    public function testTrackSameResourceTwice()
    {
        $tracker = $this->getTracker();

        mkdir($dir = $this->tmpDir.'/dir');
        mkdir($subdir = $dir.'/subdir');
        touch($foo = $this->tmpDir.'/foo');

        $tracker->track(new TrackedResource('dir1', $resource = new DirectoryResource($dir)));
        $tracker->track(new TrackedResource('dir2', $resource = new DirectoryResource($dir)));
        $tracker->track(new TrackedResource('foo1', $resource = new FileResource($foo)));
        $tracker->track(new TrackedResource('foo2', $resource = new FileResource($foo)));

        rename($dir, $dir1 = $dir.'_new');
        rename($foo, $foo1 = $foo.'_new');

        $events = $tracker->getEvents();
        $this->assertCount(6, $events);

        rename($dir1, $dir);
        rename($foo1, $foo);

        $events = $tracker->getEvents();
        $this->assertCount(6, $events);

        touch($file = $dir.'/file');
        file_put_contents($foo, 'content');

        $events = $tracker->getEvents();
        $this->assertCount(4, $events);
        $this->assertHasResourceEvent($file, FilesystemEvent::CREATE, $events);
        $this->assertHasResourceEvent($foo, FilesystemEvent::MODIFY, $events);
    }

    public function testIgnoreAttribEventForDirectories()
    {
        $tracker = $this->getTracker();

        mkdir($dir = $this->tmpDir.'/dir');

        $tracker->track(new TrackedResource('dir', $resource = new DirectoryResource($dir)));

        touch($dir);

        $events = $tracker->getEvents();
        $this->assertCount(0, $events);
    }

    public function testMoveResource()
    {
        $tracker = $this->getTracker();

        mkdir($dir = $this->tmpDir.'/dir');
        touch($file = $dir.'/file');
        mkdir($subdir = $dir.'/subdir');
        touch($subfile = $subdir.'/subfile');

        $tracker->track(new TrackedResource('dir', $resource = new DirectoryResource($dir)));

        rename($file, $file_new = $file.'_new');
        rename($subdir, $subdir_new = $subdir.'_new');

        $events = $tracker->getEvents();
        $this->assertCount(6, $events);
        $this->assertHasResourceEvent($file_new, FilesystemEvent::CREATE, $events);
        $this->assertHasResourceEvent($file, FilesystemEvent::DELETE, $events);
        $this->assertHasResourceEvent($subdir_new, FilesystemEvent::CREATE, $events);
        $this->assertHasResourceEvent($subdir, FilesystemEvent::DELETE, $events);
        $this->assertHasResourceEvent($subdir_new.'/subfile', FilesystemEvent::CREATE, $events);
        $this->assertHasResourceEvent($subfile, FilesystemEvent::DELETE, $events);
    }

    public function testMoveParentDirectoryOfWatchedResource()
    {
        $tracker = $this->getTracker();

        mkdir($parent_dir = $this->tmpDir.'/parent_dir');
        mkdir($dir = $parent_dir.'/dir');
        touch($file = $dir.'/file');
        touch($foo = $parent_dir.'/foo');

        $tracker->track(new TrackedResource('dir', $resource = new DirectoryResource($dir)));
        $tracker->track(new TrackedResource('foo', $resource = new FileResource($foo)));

        rename($parent_dir, $parent_dir.'_new');

        $events = $tracker->getEvents();
        $this->assertCount(3, $events);
        $this->assertHasResourceEvent($dir, FilesystemEvent::DELETE, $events);
        $this->assertHasResourceEvent($foo, FilesystemEvent::DELETE, $events);
        $this->assertHasResourceEvent($file, FilesystemEvent::DELETE, $events);

        rename($parent_dir.'_new', $parent_dir);

        $events = $tracker->getEvents();
        $this->assertCount(3, $events);
        $this->assertHasResourceEvent($dir, FilesystemEvent::CREATE, $events);
        $this->assertHasResourceEvent($foo, FilesystemEvent::CREATE, $events);
        $this->assertHasResourceEvent($file, FilesystemEvent::CREATE, $events);

        touch($file);
        touch($foo);

        $events = $tracker->getEvents();
        $this->assertCount(2, $events);
        $this->assertHasResourceEvent($file, FilesystemEvent::MODIFY, $events);
        $this->assertHasResourceEvent($foo, FilesystemEvent::MODIFY, $events);
    }

    public function testMoveResourceBackAndForth()
    {
        $tracker = $this->getTracker();

        mkdir($dir = $this->tmpDir.'/dir');
        touch($file = $dir.'/file');
        mkdir($subdir = $dir.'/subdir');
        touch($subfile = $subdir.'/subfile');
        touch($foo = $this->tmpDir.'/foo');

        $tracker->track(new TrackedResource('dir', $resource = new DirectoryResource($dir)));
        $tracker->track(new TrackedResource('foo', $resource = new FileResource($foo)));

        // top resources
        rename($dir, $dir.'_new');
        rename($dir.'_new', $dir);
        rename($foo, $foo.'_new');
        rename($foo.'_new', $foo);

        $events = $tracker->getEvents();
        $this->assertCount(0, $events);

        // sub resources
        rename($subdir, $subdir.'_new');
        rename($subdir.'_new', $subdir.'_new1');
        touch($subdir.'_new1');
        rename($subdir.'_new1', $subdir);
        rename($file, $file_new = $file.'_new');
        rename($file_new, $file);

        $events = $tracker->getEvents();
        $this->assertCount(0, $events);

        rename($file, $file.'_new');
        touch($file_new);
        rename($file_new, $file);

        $events = $tracker->getEvents();
        $this->assertCount(1, $events);
        $this->assertHasResourceEvent($file, FilesystemEvent::MODIFY, $events);
    }

    public function testMoveAndCreateNewResourceWithIdenticalName()
    {
        $tracker = $this->getTracker();

        mkdir($dir = $this->tmpDir.'/dir');
        touch($file = $dir.'/file');
        mkdir($subdir = $dir.'/subdir');
        touch($subfile = $subdir.'/subfile');

        $tracker->track(new TrackedResource('dir', $resource = new DirectoryResource($dir)));

        rename($dir, $dir.'_new');
        mkdir($dir);

        $events = $tracker->getEvents();
        $this->assertCount(3, $events);

        $this->assertHasResourceEvent($file, FilesystemEvent::DELETE, $events);
        $this->assertHasResourceEvent($subdir, FilesystemEvent::DELETE, $events);
        $this->assertHasResourceEvent($subfile, FilesystemEvent::DELETE, $events);
    }

    public function testMoveAndCreateNewDifferentResourceWithIdenticalName()
    {
        $tracker = $this->getTracker();

        mkdir($dir = $this->tmpDir.'/dir');
        touch($file = $dir.'/file');

        $tracker->track(new TrackedResource('dir', $resource = new DirectoryResource($dir)));

        rename($dir, $dir.'_new');
        touch($dir);

        $events = $tracker->getEvents();
        $this->assertCount(2, $events);

        $this->assertHasResourceEvent($dir, FilesystemEvent::DELETE, $events);
        $this->assertHasResourceEvent($file, FilesystemEvent::DELETE, $events);
    }
}
