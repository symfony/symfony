<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\CacheWarmer;

use Symfony\Component\AssetMapper\AssetMapper;
use Symfony\Component\AssetMapper\CompiledAssetMapperConfigReader;
use Symfony\Component\AssetMapper\ImportMap\ImportMapGenerator;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
final class DebugAssetMapperCacheWarmer implements CacheWarmerInterface
{
    public function __construct(
        private readonly CompiledAssetMapperConfigReader $compiledConfigReader,
        private readonly ImportMapGenerator $importMapGenerator,
    ) {
    }

    public function isOptional(): bool
    {
        return true;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $this->compiledConfigReader->removeConfig(AssetMapper::MANIFEST_FILE_NAME);
        $this->compiledConfigReader->removeConfig(ImportMapGenerator::IMPORT_MAP_CACHE_FILENAME);

        foreach ($this->importMapGenerator->getEntrypointNames() as $entrypointName) {
            $this->compiledConfigReader->removeConfig(\sprintf(ImportMapGenerator::ENTRYPOINT_CACHE_FILENAME_PATTERN, $entrypointName));
        }

        return [];
    }
}
