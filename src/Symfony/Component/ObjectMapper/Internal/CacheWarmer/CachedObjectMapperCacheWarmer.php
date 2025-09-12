<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Internal\CacheWarmer;

use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\ObjectMapper\CachedObjectMapper;
use Symfony\Component\ObjectMapper\Exception\MappingException;

/**
 * @author Antoine Bluchet <soyuka@gmail.com>
 *
 * @internal
 */
final class CachedObjectMapperCacheWarmer implements CacheWarmerInterface
{
    /**
     * @param iterable<array{source: class-string, target: class-string}> $mappedAttributes
     */
    public function __construct(
        private readonly iterable $mappedAttributes,
        private readonly CachedObjectMapper $mapper,
    ) {
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $warmedFiles = [];
        foreach ($this->mappedAttributes as ['source' => $sourceClass, 'target' => $targetClass]) {
            try {
                $refl = new \ReflectionClass($sourceClass);
                if ($refl->isAbstract()) {
                    continue;
                }

                $this->mapper->map($refl->newInstanceWithoutConstructor(), $targetClass);
            } catch (\ReflectionException|MappingException) {
                // Ignore classes that can't be reflected or have invalid mappings.
            }
        }

        return $warmedFiles;
    }

    public function isOptional(): bool
    {
        return true;
    }
}
