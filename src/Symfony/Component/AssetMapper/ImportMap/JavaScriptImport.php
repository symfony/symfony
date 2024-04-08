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

/**
 * Represents a module that was imported by a JavaScript file.
 */
final class JavaScriptImport
{
    /**
     * @param $importName               The name of the import needed in the importmap, e.g. "/foo.js" or "react"
     * @param $assetLogicalPath         Logical path to the mapped ass that was imported
     * @param $addImplicitlyToImportMap Whether this import should be added to the importmap automatically
     */
    public function __construct(
        public readonly string $importName,
        public readonly string $assetLogicalPath,
        public readonly string $assetSourcePath,
        public readonly bool $isLazy = false,
        public bool $addImplicitlyToImportMap = false,
    ) {
    }
}
