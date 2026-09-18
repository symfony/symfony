<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\CachePoolRefresher;
use Symfony\Component\Cache\RefreshableInterface;
use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;

class CachePoolRefresherTest extends TestCase
{
    public function testItRefreshesEveryPool()
    {
        $one = new ArrayAdapter();
        $two = new ArrayAdapter();
        $refresher = new CachePoolRefresher([$one, $two]);

        $one->get('key', static fn () => 'one');
        $two->get('key', static fn () => 'two');

        $refresher->enableRefresh();

        $this->assertSame('one/refreshed', $one->get('key', static fn () => 'one/refreshed'));
        $this->assertSame('two/refreshed', $two->get('key', static fn () => 'two/refreshed'));

        $refresher->enableRefresh(false);

        $this->assertSame('one/refreshed', $one->get('key', static fn () => 'nope'));
        $this->assertSame('two/refreshed', $two->get('key', static fn () => 'nope'));
    }

    public function testRunWithRefreshRefreshesForTheCallbackOnly()
    {
        $pool = new ArrayAdapter();
        $refresher = new CachePoolRefresher([$pool]);

        $pool->get('key', static fn () => 'old');

        $returned = $refresher->runWithRefresh(static fn () => $pool->get('key', static fn () => 'new'));

        $this->assertSame('new', $returned);
        $this->assertSame('new', $pool->get('key', static fn () => 'nope'));
    }

    public function testRunWithRefreshLeavesRefreshModeEvenWhenItWasAlreadyOn()
    {
        $pool = new ArrayAdapter();
        $refresher = new CachePoolRefresher([$pool]);

        $pool->get('key', static fn () => 'old');
        $refresher->enableRefresh();

        $refresher->runWithRefresh(static fn () => $pool->get('other', static fn () => 'other'));

        $this->assertSame('old', $pool->get('key', static fn () => 'nope'));
    }

    public function testRunWithRefreshLeavesRefreshModeWhenTheCallbackThrows()
    {
        $pool = new ArrayAdapter();
        $refresher = new CachePoolRefresher([$pool]);

        $pool->get('key', static fn () => 'old');

        try {
            $refresher->runWithRefresh(static function () {
                throw new \RuntimeException('boom');
            });
            $this->fail('the exception should bubble up');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertSame('old', $pool->get('key', static fn () => 'nope'));
    }

    public function testItIsRefreshableItself()
    {
        $this->assertInstanceOf(RefreshableInterface::class, new CachePoolRefresher([]));
    }

    public function testItTraversesTheIterableOnEveryCall()
    {
        $pool = new ArrayAdapter();
        $traversals = 0;
        $pools = new RewindableGenerator(static function () use ($pool, &$traversals) {
            ++$traversals;

            yield $pool;
        }, 1);

        $refresher = new CachePoolRefresher($pools);
        $pool->get('key', static fn () => 'old');

        $refresher->enableRefresh();
        $this->assertSame('new', $pool->get('key', static fn () => 'new'));

        $refresher->enableRefresh(false);
        $this->assertSame('new', $pool->get('key', static fn () => 'nope'));

        $this->assertSame(2, $traversals);
    }
}
