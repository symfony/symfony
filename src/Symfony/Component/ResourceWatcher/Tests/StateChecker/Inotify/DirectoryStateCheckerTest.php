<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ResourceWatcher\Tests\StateChecker\Inotify;

use Symfony\Component\ResourceWatcher\Event\FilesystemEvent;
use Symfony\Component\ResourceWatcher\StateChecker\Inotify\CheckerBag;
use Symfony\Component\ResourceWatcher\Tests\StateChecker\Inotify\Fixtures\DirectoryStateCheckerForTest;
use Symfony\Component\Config\Resource\ResourceInterface;

class DirectoryStateCheckerTest extends \PHPUnit_Framework_TestCase
{
    private $bag;
    private $resource;
    private $exists = true;

    public function setUp()
    {
        if (!function_exists('inotify_init')) {
            $this->markTestSkipped('Inotify is required for this test');
        }

        $this->bag      = new CheckerBag('whatever');
        $this->resource = $this->getResource();
    }

    public function testResourceAddedToBag()
    {
        $this->setAddWatchReturns(1);
        $checker = $this->getChecker();

        $this->assertCount(1, $this->bag->get(1));
        $this->assertContains($checker, $this->bag->get(1));
    }

    public function testResourceDeleted()
    {
        $this->setAddWatchReturns(1);
        $this->markResourceNonExistent();
        $checker = $this->getChecker($this->resource);
        $checker->setEvent(IN_DELETE);

        $events = $checker->getChangeset();

        $this->assertHasEvent($this->resource, FilesystemEvent::IN_DELETE, $events);
        $this->assertCount(0, $this->bag->get(1));
        $this->assertNull($checker->getId());
    }

    protected function setAddWatchReturns($id)
    {
        DirectoryStateCheckerForTest::setAddWatchReturns($id);
    }

    protected function getChecker()
    {
        return new DirectoryStateCheckerForTest($this->bag, $this->resource);
    }

    protected function assertHasEvent(ResourceInterface $resource, $event, $events)
    {
        $this->assertContains(array('resource' => $resource, 'event' => $event), $events, sprintf('Cannot find the expected event for the received resource.'));
    }

    protected function getResource()
    {
        $resource = $this
            ->getMockBuilder('Symfony\Component\Config\Resource\DirectoryResource')
            ->disableOriginalConstructor()
            ->getMock();
        $resource
            ->expects($this->any())
            ->method('exists')
            ->will($this->returnCallback(array($this, 'isResourceExists')));
        $resource
            ->expects($this->any())
            ->method('getFilteredResources')
            ->will($this->returnValue(array()));

        return $resource;
    }

    protected function markResourceExistent()
    {
        $this->exists = true;
    }

    protected function markResourceNonExistent()
    {
        $this->exists = false;
    }

    public function isResourceExists()
    {
        return $this->exists;
    }
}
