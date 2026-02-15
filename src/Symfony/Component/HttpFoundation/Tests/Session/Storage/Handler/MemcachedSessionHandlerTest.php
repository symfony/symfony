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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\MemcachedSessionHandler;

#[RequiresPhpExtension('memcached')]
#[Group('time-sensitive')]
class MemcachedSessionHandlerTest extends TestCase
{
    private const PREFIX = 'prefix_';
    private const TTL = 1000;

    protected function setUp(): void
    {
        if (version_compare(phpversion('memcached'), '2.2.0', '>=') && version_compare(phpversion('memcached'), '3.0.0b1', '<')) {
            $this->markTestSkipped('Tests can only be run with memcached extension 2.1.0 or lower, or 3.0.0b1 or higher');
        }
    }

    public function testOpenSession()
    {
        $this->assertTrue($this->getSessionHandler()->open('', ''));
    }

    public function testCloseSession()
    {
        $memcached = $this->getMemcachedMock();
        $memcached
            ->expects($this->once())
            ->method('quit')
            ->willReturn(true)
        ;

        $this->assertTrue($this->getSessionHandler($memcached)->close());
    }

    public function testReadSession()
    {
        $memcached = $this->getMemcachedMock();
        $memcached
            ->expects($this->once())
            ->method('get')
            ->with(self::PREFIX.'id')
        ;

        $this->assertEquals('', $this->getSessionHandler($memcached)->read('id'));
    }

    public function testWriteSession()
    {
        $memcached = $this->getMemcachedMock();
        $memcached
            ->expects($this->once())
            ->method('set')
            ->with(self::PREFIX.'id', 'data', $this->equalTo(self::TTL, 2))
            ->willReturn(true)
        ;

        $this->assertTrue($this->getSessionHandler($memcached)->write('id', 'data'));
    }

    public function testWriteSessionWithLargeTTL()
    {
        $memcached = $this->getMemcachedMock();
        $memcached
            ->expects($this->once())
            ->method('set')
            ->with(self::PREFIX.'id', 'data', $this->equalTo(time() + self::TTL + 60 * 60 * 24 * 30, 2))
            ->willReturn(true)
        ;

        $sessionHandler = new MemcachedSessionHandler(
            $memcached,
            ['prefix' => self::PREFIX, 'expiretime' => self::TTL + 60 * 60 * 24 * 30]
        );

        $this->assertTrue($sessionHandler->write('id', 'data'));
    }

    public function testDestroySession()
    {
        $memcached = $this->getMemcachedMock();
        $sessionHandler = $this->getSessionHandler($memcached);
        $sessionHandler->open('', 'sid');
        $memcached
            ->expects($this->once())
            ->method('delete')
            ->with(self::PREFIX.'id')
            ->willReturn(true)
        ;

        $this->assertTrue($sessionHandler->destroy('id'));
    }

    public function testGcSession()
    {
        $this->assertIsInt($this->getSessionHandler()->gc(123));
    }

    #[DataProvider('getOptionFixtures')]
    public function testSupportedOptions($options, $supported)
    {
        $memcached = $this->createStub(\Memcached::class);
        try {
            new MemcachedSessionHandler($memcached, $options);
            $this->assertTrue($supported);
        } catch (\InvalidArgumentException $e) {
            $this->assertFalse($supported);
        }
    }

    public static function getOptionFixtures()
    {
        return [
            [['prefix' => 'session'], true],
            [['expiretime' => 100], true],
            [['prefix' => 'session', 'ttl' => 200], true],
            [['expiretime' => 100, 'foo' => 'bar'], false],
        ];
    }

    public function testGetConnection()
    {
        $memcached = $this->createStub(\Memcached::class);
        $sessionHandler = $this->getSessionHandler($memcached);
        $method = new \ReflectionMethod($sessionHandler, 'getMemcached');

        $this->assertSame($memcached, $method->invoke($sessionHandler));
    }

    private function getMemcachedMock(): MockObject&\Memcached
    {
        $r = new \ReflectionClass(\Memcached::class);
        $methodsToMock = array_map(static fn ($m) => $m->name, $r->getMethods(\ReflectionMethod::IS_PUBLIC));
        $methodsToMock = array_diff($methodsToMock, ['getDelayed', 'getDelayedByKey']);

        return $this->getMockBuilder(\Memcached::class)
            ->disableOriginalConstructor()
            ->onlyMethods($methodsToMock)
            ->getMock();
    }

    private function getSessionHandler(?\Memcached $memcached = null, ?array $options = null): MemcachedSessionHandler
    {
        return new MemcachedSessionHandler($memcached ?? $this->createStub(\Memcached::class), $options ?? ['prefix' => self::PREFIX, 'expiretime' => self::TTL]);
    }
}
