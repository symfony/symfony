<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests\CacheWarmer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonStreamer\CacheWarmer\LazyGhostCacheWarmer;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy;

/**
 * @group legacy
 */
class LazyGhostCacheWarmerTest extends TestCase
{
    private string $lazyGhostsDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lazyGhostsDir = \sprintf('%s/symfony_json_streamer_test/lazy_ghost', sys_get_temp_dir());

        if (is_dir($this->lazyGhostsDir)) {
            array_map('unlink', glob($this->lazyGhostsDir.'/*'));
            rmdir($this->lazyGhostsDir);
        }
    }

    public function testWarmUpLazyGhost()
    {
        (new LazyGhostCacheWarmer([ClassicDummy::class], $this->lazyGhostsDir))->warmUp('useless');

        $this->assertSame(
            array_map(fn (string $c): string => \sprintf('%s/%s.php', $this->lazyGhostsDir, hash('xxh128', $c)), [ClassicDummy::class]),
            glob($this->lazyGhostsDir.'/*'),
        );
    }
}
