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

interface ImportMapConfigReaderInterface
{
    public function getEntries(): ImportMapEntries;

    public function writeEntries(ImportMapEntries $entries): void;

    public function findRootImportMapEntry(string $moduleName): ?ImportMapEntry;

    public function createRemoteEntry(string $importName, ImportMapType $type, string $version, string $packageModuleSpecifier, bool $isEntrypoint): ImportMapEntry;

    public function convertPathToFilesystemPath(string $path): string;

    public function convertFilesystemPathToPath(string $filesystemPath): ?string;
}
