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
 * Represents an item that should be in the importmap.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
final class ImportMapEntry
{
    public function __construct(
        public readonly string $importName,
        /**
         * The path to the asset if local or downloaded.
         */
        public readonly ?string $path = null,
        public readonly ?string $url = null,
        public readonly bool $isDownloaded = false,
        public readonly ImportMapType $type = ImportMapType::JS,
        public readonly bool $isEntrypoint = false,
    ) {
    }

    public function isRemote(): bool
    {
        return (bool) $this->url;
    }
}
