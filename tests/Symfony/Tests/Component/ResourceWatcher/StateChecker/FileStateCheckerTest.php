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
use Symfony\Component\ResourceWatcher\StateChecker\FileStateChecker;

class FileStateCheckerTest extends \PHPUnit_Framework_TestCase
{
    protected $resource;

    protected function setUp()
    {
        $this->resource = $this->createFileResourceMock();
        $this->resource
            ->expects($this->any())
            ->method('getModificationTime')
            ->will($this->returnValue(11));

        $this->checker = new FileStateChecker($this->resource);
    }

    public function testGetResource()
    {
        $this->assertSame($this->resource, $this->checker->getResource());
    }

    public function testNoChanges()
    {
        $this->resource
            ->expects($this->once())
            ->method('exists')
            ->will($this->returnValue(true));
        $this->resource
            ->expects($this->once())
            ->method('isFresh')
            ->with(12)
            ->will($this->returnValue(true));

        $this->assertEquals(array(), $this->checker->getChangeset());
    }

    public function testDeleted()
    {
        $this->resource
            ->expects($this->once())
            ->method('exists')
            ->will($this->returnValue(false));

        $this->assertEquals(
            array(array('event' => Event::DELETED, 'resource' => $this->resource)),
            $this->checker->getChangeset()
        );
    }

    public function testModified()
    {
        $this->resource
            ->expects($this->once())
            ->method('exists')
            ->will($this->returnValue(true));
        $this->resource
            ->expects($this->once())
            ->method('isFresh')
            ->with(12)
            ->will($this->returnValue(false));

        $this->assertEquals(
            array(array('event' => Event::MODIFIED, 'resource' => $this->resource)),
            $this->checker->getChangeset()
        );
    }

    public function testConsecutiveChecks()
    {
        $this->resource
            ->expects($this->exactly(2))
            ->method('exists')
            ->will($this->onConsecutiveCalls(true, false));
        $this->resource
            ->expects($this->once())
            ->method('isFresh')
            ->with(12)
            ->will($this->returnValue(false));

        $this->assertEquals(
            array(array('event' => Event::MODIFIED, 'resource' => $this->resource)),
            $this->checker->getChangeset()
        );
        $this->assertEquals(
            array(array('event' => Event::DELETED, 'resource' => $this->resource)),
            $this->checker->getChangeset()
        );
        $this->assertEquals(array(), $this->checker->getChangeset());
    }

    protected function createFileResourceMock()
    {
        return $this->getMockBuilder('Symfony\Component\Config\Resource\FileResource')
            ->disableOriginalConstructor()
            ->getMock();
    }
}
