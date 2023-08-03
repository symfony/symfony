<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Compiler;

use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\MappedAsset;

/**
 * An asset compiler is responsible for applying any changes to the contents of an asset.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
interface AssetCompilerInterface
{
    public const MISSING_IMPORT_STRICT = 'strict';
    public const MISSING_IMPORT_WARN = 'warn';
    public const MISSING_IMPORT_IGNORE = 'ignore';

    public function supports(MappedAsset $asset): bool;

    /**
     * Applies any changes to the contents of the asset.
     */
    public function compile(string $content, MappedAsset $asset, AssetMapperInterface $assetMapper): string;
}
