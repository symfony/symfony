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

class PackageUpdateInfo
{
    public const UPDATE_TYPE_DOWNGRADE = 'downgrade';
    public const UPDATE_TYPE_UP_TO_DATE = 'up-to-date';
    public const UPDATE_TYPE_MAJOR = 'major';
    public const UPDATE_TYPE_MINOR = 'minor';
    public const UPDATE_TYPE_PATCH = 'patch';

    public function __construct(
        public readonly string $packageName,
        public readonly string $currentVersion,
        public ?string $latestVersion = null,
        public ?string $updateType = null,
    ) {
    }

    public function hasUpdate(): bool
    {
        return !\in_array($this->updateType, [self::UPDATE_TYPE_DOWNGRADE, self::UPDATE_TYPE_DOWNGRADE, self::UPDATE_TYPE_UP_TO_DATE]);
    }
}
