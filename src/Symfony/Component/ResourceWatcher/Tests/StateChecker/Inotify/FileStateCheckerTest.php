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
use Symfony\Component\ResourceWatcher\Tests\StateChecker\Inotify\Fixtures\FileStateCheckerForTest;

class FileStateCheckerTest extends StateCheckerTest
{
    public function testResourceMovedAndReturnedDifferentWatchId()
    {
        $this->setAddWatchReturns(1);
        $checker = $this->getChecker();
        $checker->setEvent(IN_MOVE_SELF);

        $this->setAddWatchReturns(2);
        $events = $checker->getChangeset();

        $this->assertHasEvent($this->resource, FilesystemEvent::IN_MODIFY, $events);
        $this->assertCount(0, $this->bag->get(1));
        $this->assertCount(1, $this->bag->get(2));
        $this->assertContains($checker, $this->bag->get(2));
    }

    protected function setAddWatchReturns($id)
    {
        FileStateCheckerForTest::setAddWatchReturns($id);
    }

    protected function getChecker()
    {
        return new FileStateCheckerForTest($this->bag, $this->resource);
    }

    protected function getResource()
    {
        $resource = $this
            ->getMockBuilder('Symfony\Component\Config\Resource\FileResource')
            ->disableOriginalConstructor()
            ->getMock();
        $resource
            ->expects($this->any())
            ->method('exists')
            ->will($this->returnCallback(array($this, 'isResourceExists')));

        return $resource;
    }
}
