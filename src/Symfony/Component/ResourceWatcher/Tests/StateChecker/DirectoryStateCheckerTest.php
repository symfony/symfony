<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ResourceWatcher\Tests\StateChecker;

use Symfony\Component\ResourceWatcher\Event\FilesystemEvent;
use Symfony\Component\ResourceWatcher\StateChecker\DirectoryStateChecker;
use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Config\Resource\FileResource;

class DirectoryStateCheckerTest extends \PHPUnit_Framework_TestCase
{
    public function testDeepFileChanged()
    {
        $resource = $this->createDirectoryResourceMock();
        $resource
            ->expects($this->any())
            ->method('getFilteredResources')
            ->will($this->returnValue(array(
                $foo = $this->createDirectoryResourceMock()
            )));

        $resource
            ->expects($this->any())
            ->method('getModificationTime')
            ->will($this->returnValue(11));

        $foo
            ->expects($this->any())
            ->method('getFilteredResources')
            ->will($this->returnValue(array(
                $foobar = $this->createFileResourceMock()
            )));
        $foo
            ->expects($this->any())
            ->method('getModificationTime')
            ->will($this->returnValue(22));

        $foobar
            ->expects($this->any())
            ->method('getModificationTime')
            ->will($this->returnValue(33));

        $checker = new DirectoryStateChecker($resource);

        $this->touchResource($resource, true, true);
        $this->touchResource($foo,      true, true);
        $this->touchResource($foobar,   true, false);

        $this->assertEquals(array(
            array('event' => FilesystemEvent::IN_MODIFY, 'resource' => $foobar)
        ), $checker->getChangeset());
    }

    public function testDeepFileDeleted()
    {
        $resource = $this->createDirectoryResourceMock();
        $resource
            ->expects($this->any())
            ->method('getFilteredResources')
            ->will($this->returnValue(array(
                $foo = $this->createDirectoryResourceMock()
            )));
        $resource
            ->expects($this->any())
            ->method('getModificationTime')
            ->will($this->returnValue(11));
        $foo
            ->expects($this->any())
            ->method('getFilteredResources')
            ->will($this->returnValue(array(
                $foobar = $this->createFileResourceMock(array(true, false))
            )));
        $foo
            ->expects($this->any())
            ->method('getModificationTime')
            ->will($this->returnValue(22));
        $foobar
            ->expects($this->any())
            ->method('getModificationTime')
            ->will($this->returnValue(33));

        $checker = new DirectoryStateChecker($resource);

        $this->touchResource($resource, true, true);
        $this->touchResource($foo,      true, true);
        $this->touchResource($foobar,   false);

        $this->assertEquals(array(
            array('event' => FilesystemEvent::IN_DELETE, 'resource' => $foobar)
        ), $checker->getChangeset());
    }

    public function testDeepFileCreated()
    {
        $resource = $this->createDirectoryResourceMock();
        $resource
            ->expects($this->any())
            ->method('getFilteredResources')
            ->will($this->returnValue(array(
                $foo = $this->createDirectoryResourceMock()
            )));
        $resource
            ->expects($this->any())
            ->method('getModificationTime')
            ->will($this->returnValue(11));
        $foo
            ->expects($this->any())
            ->method('getFilteredResources')
            ->will($this->returnValue(array(
                $foobar = $this->createFileResourceMock(array(false, true))
            )));
        $foo
            ->expects($this->any())
            ->method('getModificationTime')
            ->will($this->returnValue(22));
        $foobar
            ->expects($this->any())
            ->method('getModificationTime')
            ->will($this->returnValue(33));

        $checker = new DirectoryStateChecker($resource);

        $this->touchResource($resource, true, true);
        $this->touchResource($foo,      true, true);
        $this->touchResource($foobar,   false);

        $this->assertEquals(array(
            array('event' => FilesystemEvent::IN_DELETE, 'resource' => $foobar)
        ), $checker->getChangeset());
    }

    protected function touchResource(ResourceInterface $resource, $exists = true, $fresh = true)
    {
        if ($exists) {
            $resource
                ->expects($this->any())
                ->method('isFresh')
                ->will($this->returnValue($fresh));
        } else {
            $resource
                ->expects($this->any())
                ->method('exists')
                ->will($this->returnValue(false));
        }
    }

    protected function createDirectoryResourceMock($exists = true)
    {
        $resource = $this->getMockBuilder('Symfony\Component\Config\Resource\DirectoryResource')
            ->disableOriginalConstructor()
            ->getMock();

        $this->setResourceExists($resource, $exists);

        return $resource;
    }

    protected function createFileResourceMock($exists = true)
    {
        $resource = $this->getMockBuilder('Symfony\Component\Config\Resource\FileResource')
            ->disableOriginalConstructor()
            ->getMock();

        $this->setResourceExists($resource, $exists);

        return $resource;
    }

    protected function setResourceExists($resource, $exists)
    {
        if (is_array($exists)) {
            $resource
                ->expects($this->any())
                ->method('exists')
                ->will($this->onConsecutiveCalls($exists));
        } else {
            $resource
                ->expects($this->any())
                ->method('exists')
                ->will($this->returnValue($exists));
        }
    }
}
