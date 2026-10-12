<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Attribute;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Attribute\Cache;

class CacheTest extends TestCase
{
    public function testNoTargetByDefault()
    {
        $cache = new Cache(expires: 'tomorrow', smaxage: 3600, vary: ['Accept-Encoding'], lastModified: 'post.getUpdatedAt()', etag: 'post.getId()', maxStale: 60);

        $this->assertSame([], $cache->target);
    }

    public function testTargetIsNormalizedToAList()
    {
        $cache = new Cache(maxage: 3600, public: true, mustRevalidate: true, staleWhileRevalidate: 60, staleIfError: 60, noStore: false, if: 'true', target: 'CDN');

        $this->assertSame(['CDN'], $cache->target);
    }

    public function testDuplicateTargetsAreRemovedCaseInsensitively()
    {
        $this->assertSame(['CDN', 'Fastly'], new Cache(target: ['CDN', 'Fastly', 'cdn', 'Fastly'])->target);
    }

    #[DataProvider('provideInvalidTargets')]
    public function testTargetRejectsEmptyOrNonStringTargets(string|array $target)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIs(\sprintf('The "$target" argument of "%s" must be a non-empty string or a list of non-empty strings.', Cache::class));

        new Cache(maxage: 3600, target: $target);
    }

    public static function provideInvalidTargets(): iterable
    {
        yield [''];
        yield [['CDN', '']];
        yield [['CDN', 3600]];
        yield [[null]];
    }

    #[DataProvider('provideArgumentsNotAllowedWithTarget')]
    public function testTargetRejectsArgumentsThatAreNotCacheDirectivesOfTheTarget(string $argument, mixed $value, string|array $target)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIs(\sprintf('The "$%s" argument of "%s" cannot be used with "$target". Only "$maxage", "$public", "$mustRevalidate", "$staleWhileRevalidate", "$staleIfError" and "$noStore" apply to a targeted cache.', $argument, Cache::class));

        new Cache(...[$argument => $value, 'target' => $target]);
    }

    public static function provideArgumentsNotAllowedWithTarget(): iterable
    {
        foreach (['CDN', ['CDN', 'Fastly']] as $target) {
            yield ['expires', 'tomorrow', $target];
            yield ['smaxage', 3600, $target];
            yield ['vary', ['Accept-Encoding'], $target];
            yield ['lastModified', 'post.getUpdatedAt()', $target];
            yield ['etag', 'post.getId()', $target];
            yield ['maxStale', 60, $target];
        }
    }
}
