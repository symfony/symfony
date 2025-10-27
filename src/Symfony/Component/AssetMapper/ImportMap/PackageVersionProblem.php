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

final readonly class PackageVersionProblem
{
    public function __construct(
        public string $packageName,
        public string $dependencyPackageName,
        public string $requiredVersionConstraint,
        public ?string $installedVersion,
    ) {
    }
}
