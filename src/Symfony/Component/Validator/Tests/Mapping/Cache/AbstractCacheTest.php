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

use Symfony\Component\Validator\Mapping\Cache\CacheInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;

abstract class AbstractCacheTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var CacheInterface
     */
    private $cache;

    public function setUp()
    {
        $this->cache = $this->getCache();
    }

    /**
     * @param string $className
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|ClassMetadata
     */
    private function getMetaDataMock($className = 'Some\Nice\Class')
    {
        $meta = $this->getMockBuilder('Symfony\\Component\\Validator\\Mapping\\ClassMetadata')
            ->disableOriginalConstructor()
            ->setMethods(['getClassName'])
            ->getMock();

        $meta->expects($this->atLeastOnce())
            ->method('getClassName')
            ->will($this->returnValue($className));

        return $meta;
    }

    /**
     * @return CacheInterface
     */
    protected abstract function getCache();

    public function testWrite()
    {
        $meta = $this->getMetaDataMock();

        $this->cache->write($meta);

        $this->assertInstanceOf(
            'Symfony\\Component\\Validator\\Mapping\\ClassMetadata',
            $this->cache->read($meta->getClassName()),
            'write() stores metadata'
        );
    }

    public function testHas()
    {
        $meta = $this->getMetaDataMock();

        $this->assertFalse($this->cache->has($meta->getClassName()), 'has() returns false when there is no entry');

        $this->cache->write($meta);
        $this->assertTrue($this->cache->has($meta->getClassName()), 'has() returns true when the is an entry');
    }

    public function testRead()
    {
        $meta = $this->getMetaDataMock();

        $this->assertFalse($this->cache->read($meta->getClassName()), 'read() returns false when there is no entry');

        $this->cache->write($meta);

        $this->assertInstanceOf(
            'Symfony\\Component\\Validator\\Mapping\\ClassMetadata',
            $this->cache->read($meta->getClassName()),
            'read() returns metadata'
        );
    }
}
