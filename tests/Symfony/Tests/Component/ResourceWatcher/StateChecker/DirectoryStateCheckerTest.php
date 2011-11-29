<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\ResourceWatcher\StateChecker;

use Symfony\Component\ResourceWatcher\Event\Event;
use Symfony\Component\ResourceWatcher\StateChecker\DirectoryStateChecker;
use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Config\Resource\FileResource;

class DirectoryStateCheckerTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        $this->resource = $this->createDirectoryResourceMock();
        $this->resource
            ->expects($this->any())
            ->method('getFilteredResources')
            ->will($this->returnValue(array()));
        $this->resource
            ->expects($this->any())
            ->method('getModificationTime')
            ->will($this->returnValue(11));

        $this->checker = new DirectoryStateChecker($this->resource);
    }

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
            array('event' => Event::MODIFIED, 'resource' => $foobar)
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
        $this->touchResource($foobar,   false);

        $this->assertEquals(array(
            array('event' => Event::DELETED, 'resource' => $foobar)
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
        $this->touchResource($foobar,   false);

        $this->assertEquals(array(
            array('event' => Event::DELETED, 'resource' => $foobar)
        ), $checker->getChangeset());
    }

    protected function touchResource(ResourceInterface $resource, $exists = true, $fresh = true)
    {
        $resource
            ->expects($this->any())
            ->method('exists')
            ->will($this->returnValue($exists));

        if ($exists) {
            $resource
                ->expects($this->once())
                ->method('isFresh')
                ->will($this->returnValue($fresh));
        }
    }

    protected function createDirectoryResourceMock()
    {
        return $this->getMockBuilder('Symfony\Component\Config\Resource\DirectoryResource')
            ->disableOriginalConstructor()
            ->getMock();
    }

    protected function createFileResourceMock()
    {
        return $this->getMockBuilder('Symfony\Component\Config\Resource\FileResource')
            ->disableOriginalConstructor()
            ->getMock();
    }
}
