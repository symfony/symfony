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
 * @experimental
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
final class ImportMapEntry
{
    public function __construct(
        /**
         * The logical path to this asset if local or downloaded.
         */
        public readonly string $importName,
        public readonly ?string $path = null,
        public readonly ?string $url = null,
        public readonly bool $isDownloaded = false,
        public readonly bool $preload = false,
    ) {
    }
}
