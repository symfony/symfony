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

use Symfony\Component\ResourceWatcher\Event\Event;
use Symfony\Component\ResourceWatcher\Tracker\TrackerInterface;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Config\Resource\FileResource;

abstract class TrackerTest extends \PHPUnit_Framework_TestCase
{
    protected $tmpDir;

    public function setUp()
    {
        $this->tmpDir = sys_get_temp_dir().'/sf2_resource_watcher_tests';
        if (is_dir($this->tmpDir)) {
            $this->cleanDir($this->tmpDir);
        }

        mkdir($this->tmpDir);
    }

    public function tearDown()
    {
        $this->cleanDir($this->tmpDir);
    }

    /**
     * @expectedException Symfony\Component\ResourceWatcher\Exception\InvalidArgumentException
     */
    public function testDoesNotTrackMissingFiles()
    {
        $tracker = $this->getTracker();

        $tracker->track(new FileResource(__DIR__.'/missingfile'));
    }

    /**
     * @expectedException Symfony\Component\ResourceWatcher\Exception\InvalidArgumentException
     */
    public function testDoesNotTrackMissingDirectories()
    {
        $tracker = $this->getTracker();

        $tracker->track(new DirectoryResource(__DIR__.'/missingdir'));
    }

    public function testTrackFileChanges()
    {
        $tracker = $this->getTracker();

        touch($file = $this->tmpDir.'/foo');

        $tracker->track($resource = new FileResource($file));

        usleep($this->getMiminumInterval());
        touch($file);

        $events = $tracker->getEvents();
        $this->assertCount(1, $events);
        $this->assertEquals(Event::MODIFIED, $events[0]->getType());

        usleep($this->getMiminumInterval());
        unlink($file);

        $events = $tracker->getEvents();
        $this->assertCount(1, $events);
        $this->assertEquals(Event::DELETED, $events[0]->getType());
    }

    abstract protected function getMiminumInterval();

    /**
     * @return TrackerInterface
     */
    abstract protected function getTracker();

    protected function cleanDir($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $flags = \FilesystemIterator::SKIP_DOTS;
        $iterator = new \RecursiveDirectoryIterator($dir, $flags);
        $iterator = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $file)
        {
            if (is_file($file)) {
                unlink($file);
            }
        }

        rmdir($dir);
    }
}
