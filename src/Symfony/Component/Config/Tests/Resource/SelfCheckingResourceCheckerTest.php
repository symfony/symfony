<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Resource;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Config\Resource\SelfCheckingResourceChecker;
use Symfony\Component\Config\Resource\SelfCheckingResourceInterface;
use Symfony\Component\Filesystem\Filesystem;

class SelfCheckingResourceCheckerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/sf-captured-resource-'.bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    #[DataProvider('provideCheckerOrders')]
    public function testCheckerNeverSharesAFreshVerdict(bool $staleFirst)
    {
        $timestamp = time() + 10;
        $path = $this->directory.'/a';
        file_put_contents($path, 'v1');
        $old = new FileResource($path);
        file_put_contents($path, 'v2');
        $new = new FileResource($path);
        $checker = new SelfCheckingResourceChecker();

        self::assertFalse($old->isFresh($timestamp));
        self::assertTrue($new->isFresh($timestamp));
        $resources = $staleFirst ? [$old, $new] : [$new, $old];
        self::assertSame($staleFirst ? [false, false] : [true, false], array_map(static fn ($r) => $checker->isFresh($r, $timestamp), $resources));
    }

    public static function provideCheckerOrders(): iterable
    {
        yield [false];
        yield [true];
    }

    public function testCheckerRepeatsFreshChecksButMemoizesStaleChecks()
    {
        $resource = new class($this->directory) implements SelfCheckingResourceInterface {
            public int $checks = 0;
            public bool $fresh = true;

            public function __construct(private string $id)
            {
            }

            public function __toString(): string
            {
                return $this->id;
            }

            public function isFresh(int $timestamp): bool
            {
                ++$this->checks;

                return $this->fresh;
            }
        };
        $checker = new SelfCheckingResourceChecker();
        self::assertTrue($checker->isFresh($resource, 1));
        self::assertTrue($checker->isFresh($resource, 1));
        self::assertSame(2, $resource->checks);
        $resource->fresh = false;
        self::assertFalse($checker->isFresh($resource, 1));
        self::assertFalse($checker->isFresh($resource, 1));
        self::assertSame(3, $resource->checks);
    }
}
