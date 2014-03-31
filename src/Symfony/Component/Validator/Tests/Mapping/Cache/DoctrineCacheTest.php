<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Mapping\Cache;

use Doctrine\Common\Cache\ArrayCache;
use Symfony\Component\Validator\Mapping\Cache\DoctrineCache;

class DoctrineCacheTest extends \PHPUnit_Framework_TestCase
{
    private $cache;

    public function testWrite()
    {
        $meta = $this->getMockBuilder('Symfony\\Component\\Validator\\Mapping\\ClassMetadata')
            ->disableOriginalConstructor()
            ->setMethods(array('getClassName'))
            ->getMock();

        $meta->expects($this->once())
            ->method('getClassName')
            ->will($this->returnValue('bar'));

        $this->cache->write($meta);

        $this->assertInstanceOf(
            'Symfony\\Component\\Validator\\Mapping\\ClassMetadata',
            $this->cache->read('bar'),
            'write() stores metadata'
        );
    }

    public function testHas()
    {
        $meta = $this->getMockBuilder('Symfony\\Component\\Validator\\Mapping\\ClassMetadata')
            ->disableOriginalConstructor()
            ->setMethods(array('getClassName'))
            ->getMock();

        $meta->expects($this->once())
            ->method('getClassName')
            ->will($this->returnValue('bar'));

        $this->assertFalse($this->cache->has('bar'), 'has() returns false when there is no entry');

        $this->cache->write($meta);
        $this->assertTrue($this->cache->has('bar'), 'has() returns true when the is an entry');
    }

    public function testRead()
    {
        $meta = $this->getMockBuilder('Symfony\\Component\\Validator\\Mapping\\ClassMetadata')
            ->disableOriginalConstructor()
            ->setMethods(array('getClassName'))
            ->getMock();

        $meta->expects($this->once())
            ->method('getClassName')
            ->will($this->returnValue('bar'));

        $this->assertFalse($this->cache->read('bar'), 'read() returns false when there is no entry');

        $this->cache->write($meta);

        $this->assertInstanceOf(
            'Symfony\\Component\\Validator\\Mapping\\ClassMetadata',
            $this->cache->read('bar'),
            'read() returns metadata'
        );
    }

    protected function setUp()
    {
        $this->cache = new DoctrineCache(new ArrayCache);
    }
}
