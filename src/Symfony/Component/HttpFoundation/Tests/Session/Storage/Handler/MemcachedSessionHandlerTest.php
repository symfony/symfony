<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Session\Storage\Handler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\MemcachedSessionHandler;

/**
 * @requires extension memcached
 *
 * @group time-sensitive
 */
class MemcachedSessionHandlerTest extends TestCase
{
    private const PREFIX = 'prefix_';
    private const TTL = 1000;

    /**
     * @var MemcachedSessionHandler
     */
    protected $storage;

    protected $memcached;

    protected function setUp(): void
    {
        self::setUp();

        if (version_compare(phpversion('memcached'), '2.2.0', '>=') && version_compare(phpversion('memcached'), '3.0.0b1', '<')) {
            self::markTestSkipped('Tests can only be run with memcached extension 2.1.0 or lower, or 3.0.0b1 or higher');
        }

        $r = new \ReflectionClass(\Memcached::class);
        $methodsToMock = array_map(function ($m) { return $m->name; }, $r->getMethods(\ReflectionMethod::IS_PUBLIC));
        $methodsToMock = array_diff($methodsToMock, ['getDelayed', 'getDelayedByKey']);

        $this->memcached = self::getMockBuilder(\Memcached::class)
            ->disableOriginalConstructor()
            ->setMethods($methodsToMock)
            ->getMock();

        $this->storage = new MemcachedSessionHandler(
            $this->memcached,
            ['prefix' => self::PREFIX, 'expiretime' => self::TTL]
        );
    }

    protected function tearDown(): void
    {
        $this->memcached = null;
        $this->storage = null;
        self::tearDown();
    }

    public function testOpenSession()
    {
        self::assertTrue($this->storage->open('', ''));
    }

    public function testCloseSession()
    {
        $this->memcached
            ->expects(self::once())
            ->method('quit')
            ->willReturn(true)
        ;

        self::assertTrue($this->storage->close());
    }

    public function testReadSession()
    {
        $this->memcached
            ->expects(self::once())
            ->method('get')
            ->with(self::PREFIX.'id')
        ;

        self::assertEquals('', $this->storage->read('id'));
    }

    public function testWriteSession()
    {
        $this->memcached
            ->expects(self::once())
            ->method('set')
            ->with(self::PREFIX.'id', 'data', self::equalTo(self::TTL, 2))
            ->willReturn(true)
        ;

        self::assertTrue($this->storage->write('id', 'data'));
    }

    public function testWriteSessionWithLargeTTL()
    {
        $this->memcached
            ->expects(self::once())
            ->method('set')
            ->with(self::PREFIX.'id', 'data', self::equalTo(time() + self::TTL + 60 * 60 * 24 * 30, 2))
            ->willReturn(true)
        ;

        $storage = new MemcachedSessionHandler(
            $this->memcached,
            ['prefix' => self::PREFIX, 'expiretime' => self::TTL + 60 * 60 * 24 * 30]
        );

        self::assertTrue($storage->write('id', 'data'));
    }

    public function testDestroySession()
    {
        $this->memcached
            ->expects(self::once())
            ->method('delete')
            ->with(self::PREFIX.'id')
            ->willReturn(true)
        ;

        self::assertTrue($this->storage->destroy('id'));
    }

    public function testGcSession()
    {
        self::assertIsInt($this->storage->gc(123));
    }

    /**
     * @dataProvider getOptionFixtures
     */
    public function testSupportedOptions($options, $supported)
    {
        try {
            new MemcachedSessionHandler($this->memcached, $options);
            self::assertTrue($supported);
        } catch (\InvalidArgumentException $e) {
            self::assertFalse($supported);
        }
    }

    public function getOptionFixtures()
    {
        return [
            [['prefix' => 'session'], true],
            [['expiretime' => 100], true],
            [['prefix' => 'session', 'expiretime' => 200], true],
            [['expiretime' => 100, 'foo' => 'bar'], false],
        ];
    }

    public function testGetConnection()
    {
        $method = new \ReflectionMethod($this->storage, 'getMemcached');
        $method->setAccessible(true);

        self::assertInstanceOf(\Memcached::class, $method->invoke($this->storage));
    }
}
