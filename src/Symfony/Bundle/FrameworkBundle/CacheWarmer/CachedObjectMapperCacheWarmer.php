<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\CacheWarmer;

use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\ObjectMapper\Internal\MappingCacheGenerator;
use Symfony\Component\ObjectMapper\Internal\MappingCacheGeneratorInterface;
use Symfony\Component\ObjectMapper\Internal\MappingCacheTrait;
use Symfony\Component\ObjectMapper\Metadata\ReflectionObjectMapperMetadataFactory;

/**
 * @internal
 */
final class CachedObjectMapperCacheWarmer implements CacheWarmerInterface
{
    use MappingCacheTrait;

    /**
     * @param iterable<array{source: class-string, target: class-string}> $mappedAttributes
     */
    public function __construct(
        private readonly string $cacheDir,
        private readonly iterable $mappedAttributes,
        ?MappingCacheGeneratorInterface $generator = null,
    ) {
        $this->generator = $generator ?? new MappingCacheGenerator(new ReflectionObjectMapperMetadataFactory());
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        if (!$this->mappedAttributes) {
            return [];
        }

        foreach ($this->mappedAttributes as ['source' => $sourceClass, 'target' => $targetClass]) {
            $cacheFile = $this->getCacheFile($sourceClass, $targetClass);

            if (is_file($cacheFile)) {
                continue;
            }

            $this->writeCacheFile($cacheFile, $sourceClass, $targetClass);
        }

        return [];
    }

    public function isOptional(): bool
    {
        return true;
    }
}
