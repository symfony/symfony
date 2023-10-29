<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\ImportMap;

use Symfony\Component\AssetMapper\MappedAsset;

/**
 * Represents a module that was imported by a JavaScript file.
 */
final class JavaScriptImport
{
    /**
     * @param string      $importName               The name of the import needed in the importmap, e.g. "/foo.js" or "react"
     * @param MappedAsset $asset                    The asset that was imported
     * @param bool        $addImplicitlyToImportMap Whether this import should be added to the importmap automatically
     */
    public function __construct(
        public readonly string $importName,
        public readonly MappedAsset $asset,
        public readonly bool $isLazy = false,
        public bool $addImplicitlyToImportMap = false,
    ) {
    }
}
