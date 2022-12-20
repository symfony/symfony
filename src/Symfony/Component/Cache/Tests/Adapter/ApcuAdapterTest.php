<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Adapter;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;

class ApcuAdapterTest extends AdapterTestCase
{
    protected $skippedTests = [
        'testExpiration' => 'Testing expiration slows down the test suite',
        'testHasItemReturnsFalseWhenDeferredItemIsExpired' => 'Testing expiration slows down the test suite',
        'testDefaultLifeTime' => 'Testing expiration slows down the test suite',
    ];

    public function createCachePool(int $defaultLifetime = 0): CacheItemPoolInterface
    {
        if (!\function_exists('apcu_fetch') || !filter_var(\ini_get('apc.enabled'), \FILTER_VALIDATE_BOOLEAN)) {
            self::markTestSkipped('APCu extension is required.');
        }
        if ('cli' === \PHP_SAPI && !filter_var(\ini_get('apc.enable_cli'), \FILTER_VALIDATE_BOOLEAN)) {
            if ('testWithCliSapi' !== self::getName()) {
                self::markTestSkipped('apc.enable_cli=1 is required.');
            }
        }
        if ('\\' === \DIRECTORY_SEPARATOR) {
            self::markTestSkipped('Fails transiently on Windows.');
        }

        return new ApcuAdapter(str_replace('\\', '.', __CLASS__), $defaultLifetime);
    }

    public function testUnserializable()
    {
        $pool = $this->createCachePool();

        $item = $pool->getItem('foo');
        $item->set(function () {});

        self::assertFalse($pool->save($item));

        $item = $pool->getItem('foo');
        self::assertFalse($item->isHit());
    }

    public function testVersion()
    {
        $namespace = str_replace('\\', '.', static::class);

        $pool1 = new ApcuAdapter($namespace, 0, 'p1');

        $item = $pool1->getItem('foo');
        self::assertFalse($item->isHit());
        self::assertTrue($pool1->save($item->set('bar')));

        $item = $pool1->getItem('foo');
        self::assertTrue($item->isHit());
        self::assertSame('bar', $item->get());

        $pool2 = new ApcuAdapter($namespace, 0, 'p2');

        $item = $pool2->getItem('foo');
        self::assertFalse($item->isHit());
        self::assertNull($item->get());

        $item = $pool1->getItem('foo');
        self::assertFalse($item->isHit());
        self::assertNull($item->get());
    }

    public function testNamespace()
    {
        $namespace = str_replace('\\', '.', static::class);

        $pool1 = new ApcuAdapter($namespace.'_1', 0, 'p1');

        $item = $pool1->getItem('foo');
        self::assertFalse($item->isHit());
        self::assertTrue($pool1->save($item->set('bar')));

        $item = $pool1->getItem('foo');
        self::assertTrue($item->isHit());
        self::assertSame('bar', $item->get());

        $pool2 = new ApcuAdapter($namespace.'_2', 0, 'p1');

        $item = $pool2->getItem('foo');
        self::assertFalse($item->isHit());
        self::assertNull($item->get());

        $item = $pool1->getItem('foo');
        self::assertTrue($item->isHit());
        self::assertSame('bar', $item->get());
    }

    public function testWithCliSapi()
    {
        try {
            // disable PHPUnit error handler to mimic a production environment
            $isCalled = false;
            set_error_handler(function () use (&$isCalled) {
                $isCalled = true;
            });
            $pool = new ApcuAdapter(str_replace('\\', '.', __CLASS__));
            $pool->setLogger(new NullLogger());

            $item = $pool->getItem('foo');
            $item->isHit();
            $pool->save($item->set('bar'));
            self::assertFalse($isCalled);
        } finally {
            restore_error_handler();
        }
    }

    public function testCacheItemValueRunsThroughMarshaller()
    {
        $namespace = str_replace('\\', '.', static::class);

        $marshaller = self::createMock(MarshallerInterface::class);
        $marshaller->expects(self::once())
            ->method('marshall')
            ->with([$namespace.':foo' => 'bar'])
            ->willReturn([$namespace.':foo' => 'bar_serialized']);

        $marshaller->expects(self::once())
            ->method('unmarshall')
            ->with('bar_serialized')
            ->willReturn('bar');

        $pool = new ApcuAdapter($namespace, 0, 'p1', $marshaller);

        $item = $pool->getItem('foo');
        self::assertFalse($item->isHit());
        self::assertTrue($pool->save($item->set('bar')));

        $item = $pool->getItem('foo');
        self::assertTrue($item->isHit());
        self::assertSame('bar', $item->get());
    }
}
