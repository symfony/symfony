<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Internal;

/**
 * @internal
 */
trait MappingCacheTrait
{
    private readonly string $cacheDir;
    private readonly MappingCacheGeneratorInterface $generator;

    private function getCacheFile(string $sourceClass, string $targetClass): string
    {
        $cacheKey = hash('xxh128', $sourceClass.'-to-'.$targetClass);

        return $this->cacheDir.'/'.$cacheKey.'.php';
    }

    private function writeCacheFile(string $cacheFile, string $sourceClass, string $targetClass): void
    {
        $code = $this->generator->generate($sourceClass, $targetClass);
        $this->generator->write($cacheFile, $code);
    }
}
